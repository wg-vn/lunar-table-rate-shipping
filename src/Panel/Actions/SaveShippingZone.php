<?php

namespace Lunar\Shipping\Panel\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Facades\PriceCalculator;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\State;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;

/**
 * Saves a shipping zone from the panel: its name and type, and, when given, its coverage, exclusion lists
 * and rates, each replacing what the zone had. Coverage follows the type the way the zone resolver reads
 * it: a states or postcodes zone keeps its one country in `countries`, and every other list is emptied.
 */
class SaveShippingZone
{
    /**
     * @param  array<string, mixed>  $data  validated `ShippingZoneRequest` input
     */
    public function execute(ShippingZone $zone, array $data): ShippingZone
    {
        return DB::transaction(function () use ($zone, $data): ShippingZone {
            $zone->fill(Arr::only($data, ['name', 'type']))->save();

            if (array_key_exists('rates', $data)) {
                $this->syncCoverage($zone, $data);
                $zone->shippingExclusions()->sync($data['exclusion_lists'] ?? []);
                $this->syncRates($zone, $data['rates']);
            }

            return $zone;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncCoverage(ShippingZone $zone, array $data): void
    {
        $countryId = $data['country_id'] ?? null;

        $zone->countries()->sync(match ($zone->type) {
            'countries' => $data['countries'] ?? [],
            'states', 'postcodes' => [$countryId],
            default => [],
        });

        $zone->states()->sync($zone->type === 'states'
            ? State::query()->whereKey($data['states'] ?? [])->where('country_id', $countryId)->pluck('id')
            : []);

        $zone->postcodes()->delete();

        if ($zone->type === 'postcodes') {
            $zone->postcodes()->createMany(
                collect(preg_split('/\R/', (string) ($data['postcodes'] ?? '')))
                    ->map(fn (string $postcode) => str_replace(' ', '', $postcode))
                    ->filter()
                    ->unique()
                    ->map(fn (string $postcode) => ['postcode' => $postcode])
                    ->values()
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     */
    private function syncRates(ShippingZone $zone, array $rates): void
    {
        $existing = $zone->rates()->get()->keyBy('id');
        $keptIds = collect($rates)->pluck('id')->filter()->map(fn ($id) => (int) $id);

        $existing->reject(fn (ShippingRate $rate) => $keptIds->contains($rate->id))
            ->each(fn (ShippingRate $rate) => $rate->delete());

        $currencies = Currency::query()->get()->keyBy('id');

        foreach ($rates as $input) {
            $rate = $existing->get((int) ($input['id'] ?? 0)) ?? new ShippingRate(['shipping_zone_id' => $zone->id]);
            $rate->fill([
                'shipping_method_id' => $input['shipping_method_id'],
                'enabled' => (bool) $input['enabled'],
            ])->save();

            $this->replacePrices($rate->load('shippingMethod'), $input, $currencies);
        }
    }

    /**
     * Base prices (no customer group, minimum 1) per currency, then the tiers. A tier's minimum is a spend
     * in the tier's currency for cart-total methods, stored in minor units, or a whole weight otherwise.
     *
     * @param  array<string, mixed>  $input
     * @param  Collection<int, Currency>  $currencies
     */
    private function replacePrices(ShippingRate $rate, array $input, $currencies): void
    {
        $rate->prices()->delete();

        foreach ($input['base_prices'] as $currencyId => $price) {
            $currency = $currencies->get((int) $currencyId);

            if ($currency === null || blank($price)) {
                continue;
            }

            $rate->prices()->create([
                'currency_id' => $currency->id,
                'customer_group_id' => null,
                'min_quantity' => 1,
                'price' => PriceCalculator::toMinor($price, $currency),
            ]);
        }

        $byWeight = ($rate->shippingMethod->data['charge_by'] ?? null) === 'weight';

        foreach ($input['tiers'] as $tier) {
            $currency = $currencies->get((int) $tier['currency_id']);

            $rate->prices()->create([
                'currency_id' => $currency->id,
                'customer_group_id' => $tier['customer_group_id'] ?? null,
                'min_quantity' => $byWeight ? (int) $tier['min_quantity'] : PriceCalculator::toMinor($tier['min_quantity'], $currency),
                'price' => PriceCalculator::toMinor($tier['price'], $currency),
            ]);
        }
    }
}
