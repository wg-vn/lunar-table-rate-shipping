<?php

namespace Lunar\Shipping\Panel;

use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Lunar\Core\Facades\PriceCalculator;
use Lunar\Core\Models\Currency;
use Lunar\Panel\Contracts\DiscountTypeForm;
use Lunar\Shipping\Models\ShippingMethod;

/**
 * The panel form of `ShippingDiscount`: rules per shipping method (or for every method, without one), each
 * a percentage off or a fixed shipping price per currency. Stored prices are minor units keyed by currency
 * code. The form also carries the method choices under `shipping_methods`, which storage drops, because
 * the discount editor passes a type form nothing but its data and the currencies.
 */
class ShippingDiscountForm implements DiscountTypeForm
{
    public function component(): string
    {
        return 'lunar-shipping::ShippingDiscountForm';
    }

    /** @return list<string> */
    public function targetBuckets(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function toForm(array $data): array
    {
        $currencies = $this->currencies();

        return [
            'methods' => collect($data['methods'] ?? [])->map(fn (array $rule) => [
                'shipping_method_id' => $rule['shipping_method_id'] ?? null,
                'type' => $rule['type'] ?? 'fixed',
                'percentage' => $rule['percentage'] ?? null,
                'prices' => $currencies->mapWithKeys(fn (Currency $currency) => [
                    $currency->code => isset($rule['prices'][$currency->code])
                        ? PriceCalculator::toMajor((int) $rule['prices'][$currency->code], $currency)
                        : null,
                ])->all(),
            ])->values()->all(),
            'shipping_methods' => ShippingMethod::query()->orderBy('name')->get(['id', 'name'])->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function toStorage(array $data): array
    {
        $currencies = $this->currencies();

        return [
            'methods' => collect($data['methods'] ?? [])->map(fn (array $rule) => array_filter([
                'shipping_method_id' => filled($rule['shipping_method_id'] ?? null) ? (int) $rule['shipping_method_id'] : null,
                'type' => $rule['type'],
                'percentage' => $rule['type'] === 'percentage' ? (float) $rule['percentage'] : null,
                'prices' => $rule['type'] === 'fixed'
                    ? $currencies
                        ->filter(fn (Currency $currency) => filled($rule['prices'][$currency->code] ?? null))
                        ->mapWithKeys(fn (Currency $currency) => [
                            $currency->code => PriceCalculator::toMinor($rule['prices'][$currency->code], $currency),
                        ])->all()
                    : null,
            ], fn ($value, $key) => $key === 'shipping_method_id' || $value !== null, ARRAY_FILTER_USE_BOTH))->values()->all(),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'methods' => ['required', 'array', 'min:1'],
            'methods.*.shipping_method_id' => ['nullable', Rule::exists(ShippingMethod::class, 'id')],
            'methods.*.type' => ['required', Rule::in(['fixed', 'percentage'])],
            'methods.*.percentage' => ['nullable', 'required_if:methods.*.type,percentage', 'numeric', 'between:0,100'],
            'methods.*.prices' => ['nullable', 'array'],
            'methods.*.prices.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function summary(array $data, ?Currency $currency): ?string
    {
        $count = count($data['methods'] ?? []);

        return $count ? trans_choice('lunar-shipping::panel.discount.summary', $count, ['count' => $count]) : null;
    }

    /** @return Collection<int, Currency> */
    private function currencies(): Collection
    {
        return Currency::query()->whereEnabled(true)->orderByDesc('default')->orderBy('code')->get();
    }
}
