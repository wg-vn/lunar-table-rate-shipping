<?php

namespace Lunar\Shipping\Panel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\State;
use Lunar\Shipping\Models\ShippingExclusionList;
use Lunar\Shipping\Models\ShippingMethod;

/**
 * Shared by the shipping zone store and update endpoints. Store carries the zone's name and type; update
 * also carries its coverage, exclusion lists and rates, which replace what the zone had. Prices and
 * minimum spends arrive in major units (19.99); weight tiers in whole units of the method's weight unit.
 */
class ShippingZoneRequest extends FormRequest
{
    public const TYPES = ['unrestricted', 'countries', 'states', 'postcodes'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'countries' => ['sometimes', 'array'],
            'countries.*' => [Rule::exists(Country::class, 'id')],
            'country_id' => ['nullable', Rule::requiredIf(fn () => $this->has('rates') && in_array($this->input('type'), ['states', 'postcodes'], true)), Rule::exists(Country::class, 'id')],
            'states' => ['sometimes', 'array'],
            'states.*' => [Rule::exists(State::class, 'id')],
            'postcodes' => ['sometimes', 'nullable', 'string'],
            'exclusion_lists' => ['sometimes', 'array'],
            'exclusion_lists.*' => [Rule::exists(ShippingExclusionList::class, 'id')],
            'rates' => ['sometimes', 'array'],
            'rates.*.id' => ['nullable', 'integer'],
            'rates.*.shipping_method_id' => ['required', Rule::exists(ShippingMethod::class, 'id')],
            'rates.*.enabled' => ['required', 'boolean'],
            'rates.*.base_prices' => ['present', 'array'],
            'rates.*.base_prices.*' => ['nullable', 'numeric', 'min:0'],
            'rates.*.tiers' => ['present', 'array'],
            'rates.*.tiers.*.customer_group_id' => ['nullable', Rule::exists(CustomerGroup::class, 'id')],
            'rates.*.tiers.*.currency_id' => ['required', Rule::exists(Currency::class, 'id')],
            'rates.*.tiers.*.price' => ['required', 'numeric', 'min:0'],
            'rates.*.tiers.*.min_quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * A rate needs a base price in the default currency, and a weight-based method's tiers are whole numbers
     * (Lunar stores weight tiers as integers, so decimals are refused rather than truncated).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $defaultCurrencyId = Currency::getDefault()?->id;
            $weightMethods = ShippingMethod::query()->get()
                ->filter(fn (ShippingMethod $method) => ($method->data['charge_by'] ?? null) === 'weight')
                ->pluck('id');

            foreach ((array) $this->input('rates', []) as $index => $rate) {
                if (blank($rate['base_prices'][$defaultCurrencyId] ?? null)) {
                    $validator->errors()->add("rates.{$index}.base_prices.{$defaultCurrencyId}", __('lunar-shipping::panel.zones.base_price_required'));
                }

                if (! $weightMethods->contains((int) ($rate['shipping_method_id'] ?? 0))) {
                    continue;
                }

                foreach ((array) ($rate['tiers'] ?? []) as $tierIndex => $tier) {
                    if (filter_var($tier['min_quantity'] ?? null, FILTER_VALIDATE_INT) === false) {
                        $validator->errors()->add("rates.{$index}.tiers.{$tierIndex}.min_quantity", __('lunar-shipping::panel.zones.weight_tier_integer'));
                    }
                }
            }
        }];
    }
}
