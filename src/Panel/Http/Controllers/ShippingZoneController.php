<?php

namespace Lunar\Shipping\Panel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Core\Facades\PriceCalculator;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\State;
use Lunar\Panel\Http\Controllers\Concerns\ResolvesTableExtensions;
use Lunar\Shipping\Models\ShippingExclusionList;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Shipping\Panel\Actions\SaveShippingZone;
use Lunar\Shipping\Panel\Http\Requests\ShippingZoneRequest;

/**
 * The shipping zone screens: a listing, a create dialog (name and type), and an edit page for the zone's
 * coverage, exclusion lists and rates. Prices go to the page in major units and come back the same way.
 */
class ShippingZoneController
{
    use ResolvesTableExtensions;

    public function index(Request $request): Response
    {
        $resolver = $this->resolveTable('shipping-zones.index');
        $columns = [
            ['key' => 'name', 'label' => __('lunar-shipping::panel.zones.column_name'), 'width' => 'minmax(0, 1.4fr)'],
            ['key' => 'type', 'label' => __('lunar-shipping::panel.zones.column_type'), 'width' => '140px'],
            ['key' => 'rates_count', 'label' => __('lunar-shipping::panel.zones.column_rates'), 'width' => '90px', 'align' => 'right'],
        ];

        $zones = ShippingZone::query()
            ->withCount('rates')
            ->tap(fn ($query) => $resolver->applyColumnQueries($query))
            ->tap(fn ($query) => $resolver->applyFilters($query, $request))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (ShippingZone $zone) use ($resolver): array {
                $row = [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'type' => $zone->type,
                    'rates_count' => (int) $zone->getAttribute('rates_count'),
                    'urls' => ['edit' => route('panel.settings.shipping-zones.edit', $zone)],
                    '_actions' => $resolver->resolveRowActionUrls($zone),
                ];

                foreach ($resolver->getColumnKeys() as $key) {
                    $row[$key] = $zone->getAttribute($key);
                }

                return $row;
            });

        return Inertia::render('lunar-shipping::Zones/Index', [
            'zones' => $zones,
            ...$this->tableProps($resolver, $columns, $request),
            'urls' => [
                'index' => route('panel.settings.shipping-zones.index'),
                'store' => route('panel.settings.shipping-zones.store'),
            ],
        ]);
    }

    public function store(ShippingZoneRequest $request, SaveShippingZone $saveShippingZone): RedirectResponse
    {
        $zone = $saveShippingZone->execute(new ShippingZone, $request->safe()->only(['name', 'type']));

        return redirect()
            ->route('panel.settings.shipping-zones.edit', $zone)
            ->with('success', __('lunar-shipping::panel.zones.flash_created'));
    }

    public function edit(ShippingZone $shippingZone): Response
    {
        $shippingZone->load(['countries', 'states', 'postcodes', 'shippingExclusions', 'rates.prices.currency', 'rates.shippingMethod']);
        $countryId = $shippingZone->countries->first()?->id;

        return Inertia::render('lunar-shipping::Zones/Edit', [
            'zone' => [
                'id' => $shippingZone->id,
                'name' => $shippingZone->name,
                'type' => $shippingZone->type,
                'countries' => $shippingZone->countries->pluck('id'),
                'country_id' => in_array($shippingZone->type, ['states', 'postcodes'], true) ? $countryId : null,
                'states' => $shippingZone->states->pluck('id'),
                'postcodes' => $shippingZone->postcodes->pluck('postcode')->join("\n"),
                'exclusion_lists' => $shippingZone->shippingExclusions->pluck('id'),
                'rates' => $shippingZone->rates->map(fn (ShippingRate $rate) => $this->rateForForm($rate))->values(),
            ],
            'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso2']),
            'states' => State::query()->orderBy('name')->get(['id', 'name', 'code', 'country_id']),
            'currencies' => Currency::query()->whereEnabled(true)->orderByDesc('default')->orderBy('code')->get(['id', 'code', 'name', 'decimal_places', 'default']),
            'customerGroups' => CustomerGroup::query()->orderBy('name')->get(['id', 'name']),
            'shippingMethods' => ShippingMethod::query()->orderBy('name')->get()->map(fn (ShippingMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'charge_by' => $method->data['charge_by'] ?? 'cart_total',
                'weight_unit' => $method->weight_unit ?: 'kg',
            ]),
            'exclusionLists' => ShippingExclusionList::query()->orderBy('name')->get(['id', 'name']),
            'pricesIncludeTax' => (bool) config('lunar.pricing.stored_inclusive_of_tax', false),
            'urls' => [
                'index' => route('panel.settings.shipping-zones.index'),
                'update' => route('panel.settings.shipping-zones.update', $shippingZone),
                'destroy' => route('panel.settings.shipping-zones.destroy', $shippingZone),
            ],
        ]);
    }

    public function update(ShippingZoneRequest $request, ShippingZone $shippingZone, SaveShippingZone $saveShippingZone): RedirectResponse
    {
        $saveShippingZone->execute($shippingZone, $request->validated());

        return back()->with('success', __('lunar-shipping::panel.zones.flash_updated'));
    }

    public function destroy(ShippingZone $shippingZone): RedirectResponse
    {
        // One by one, so each rate's own deleting hook removes its prices; the zone's hook bulk-deletes rates.
        $shippingZone->rates()->get()->each->delete();
        $shippingZone->delete();

        return redirect()
            ->route('panel.settings.shipping-zones.index')
            ->with('success', __('lunar-shipping::panel.zones.flash_deleted'));
    }

    /**
     * A rate as the edit form holds it: base prices keyed by currency id, and every other price as a tier.
     *
     * @return array<string, mixed>
     */
    private function rateForForm(ShippingRate $rate): array
    {
        $byWeight = ($rate->shippingMethod?->data['charge_by'] ?? null) === 'weight';
        [$base, $tiers] = $rate->prices->partition(fn (Price $price) => $price->min_quantity == 1 && $price->customer_group_id === null);

        return [
            'id' => $rate->id,
            'shipping_method_id' => $rate->shipping_method_id,
            'enabled' => (bool) $rate->enabled,
            'base_prices' => $base->mapWithKeys(fn (Price $price) => [
                $price->currency_id => PriceCalculator::toMajor($price->price, $price->currency),
            ]),
            'tiers' => $tiers->map(fn (Price $price) => [
                'customer_group_id' => $price->customer_group_id,
                'currency_id' => $price->currency_id,
                'price' => PriceCalculator::toMajor($price->price, $price->currency),
                'min_quantity' => $byWeight ? $price->min_quantity : PriceCalculator::toMajor($price->min_quantity, $price->currency),
            ])->values(),
        ];
    }
}
