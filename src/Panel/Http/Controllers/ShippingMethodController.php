<?php

namespace Lunar\Shipping\Panel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Core\Facades\Converter;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Panel\Http\Controllers\Concerns\ResolvesTableExtensions;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Panel\Actions\SaveShippingMethod;
use Lunar\Shipping\Panel\Http\Requests\ShippingMethodRequest;

/**
 * The shipping method screens: a listing, a create dialog (name, code, driver), and an edit page for the
 * method's settings, weekly schedule and customer group availability.
 */
class ShippingMethodController
{
    use ResolvesTableExtensions;

    public function index(Request $request): Response
    {
        $resolver = $this->resolveTable('shipping-methods.index');
        $columns = [
            ['key' => 'name', 'label' => __('lunar-shipping::panel.methods.column_name'), 'width' => 'minmax(0, 1.4fr)'],
            ['key' => 'code', 'label' => __('lunar-shipping::panel.methods.column_code'), 'width' => '160px'],
            ['key' => 'driver', 'label' => __('lunar-shipping::panel.methods.column_driver'), 'width' => '140px'],
            ['key' => 'status', 'label' => __('lunar-shipping::panel.methods.column_status'), 'width' => '110px'],
        ];

        $methods = ShippingMethod::query()
            ->tap(fn ($query) => $resolver->applyColumnQueries($query))
            ->tap(fn ($query) => $resolver->applyFilters($query, $request))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (ShippingMethod $method) use ($resolver): array {
                $row = [
                    'id' => $method->id,
                    'name' => $method->name,
                    'code' => $method->code,
                    'driver' => $method->driver,
                    'enabled' => (bool) $method->enabled,
                    'urls' => ['edit' => route('panel.settings.shipping-methods.edit', $method)],
                    '_actions' => $resolver->resolveRowActionUrls($method),
                ];

                foreach ($resolver->getColumnKeys() as $key) {
                    $row[$key] = $method->getAttribute($key);
                }

                return $row;
            });

        return Inertia::render('lunar-shipping::Methods/Index', [
            'methods' => $methods,
            ...$this->tableProps($resolver, $columns, $request),
            'drivers' => ShippingMethodRequest::DRIVERS,
            'urls' => [
                'index' => route('panel.settings.shipping-methods.index'),
                'store' => route('panel.settings.shipping-methods.store'),
            ],
        ]);
    }

    public function store(ShippingMethodRequest $request, SaveShippingMethod $saveShippingMethod): RedirectResponse
    {
        $method = $saveShippingMethod->execute(new ShippingMethod, $request->safe()->only(['name', 'code', 'driver']));

        return redirect()
            ->route('panel.settings.shipping-methods.edit', $method)
            ->with('success', __('lunar-shipping::panel.methods.flash_created'));
    }

    public function edit(ShippingMethod $shippingMethod): Response
    {
        $shippingMethod->load('customerGroups');

        return Inertia::render('lunar-shipping::Methods/Edit', [
            'method' => [
                'id' => $shippingMethod->id,
                'name' => $shippingMethod->name,
                'code' => $shippingMethod->code,
                'driver' => $shippingMethod->driver,
                'description' => $shippingMethod->description,
                'enabled' => (bool) $shippingMethod->enabled,
                'stock_available' => (bool) $shippingMethod->stock_available,
                'charge_by' => $shippingMethod->data['charge_by'] ?? 'cart_total',
                'weight_unit' => $shippingMethod->weight_unit,
                'min_weight' => $shippingMethod->min_weight === null ? null : (float) $shippingMethod->min_weight,
                'max_weight' => $shippingMethod->max_weight === null ? null : (float) $shippingMethod->max_weight,
                'schedule' => $shippingMethod->data['schedule'] ?? null,
                'customer_groups' => $shippingMethod->customerGroups->map(fn (CustomerGroup $group) => [
                    'customer_group_id' => $group->id,
                    'enabled' => (bool) $group->pivot->enabled,
                    'visible' => (bool) $group->pivot->visible,
                    'starts_at' => $group->pivot->starts_at ? substr((string) $group->pivot->starts_at, 0, 10) : null,
                    'ends_at' => $group->pivot->ends_at ? substr((string) $group->pivot->ends_at, 0, 10) : null,
                ])->values(),
            ],
            'drivers' => ShippingMethodRequest::DRIVERS,
            'weightUnits' => array_keys(Converter::getMeasurements()['weight'] ?? []),
            'customerGroups' => CustomerGroup::query()->orderBy('name')->get(['id', 'name']),
            'urls' => [
                'index' => route('panel.settings.shipping-methods.index'),
                'update' => route('panel.settings.shipping-methods.update', $shippingMethod),
                'destroy' => route('panel.settings.shipping-methods.destroy', $shippingMethod),
            ],
        ]);
    }

    public function update(ShippingMethodRequest $request, ShippingMethod $shippingMethod, SaveShippingMethod $saveShippingMethod): RedirectResponse
    {
        $saveShippingMethod->execute($shippingMethod, $request->validated());

        return back()->with('success', __('lunar-shipping::panel.methods.flash_updated'));
    }

    public function destroy(ShippingMethod $shippingMethod): RedirectResponse
    {
        // One by one, so each rate's own deleting hook removes its prices; the method's hook bulk-deletes rates.
        $shippingMethod->shippingRates()->get()->each->delete();
        $shippingMethod->delete();

        return redirect()
            ->route('panel.settings.shipping-methods.index')
            ->with('success', __('lunar-shipping::panel.methods.flash_deleted'));
    }
}
