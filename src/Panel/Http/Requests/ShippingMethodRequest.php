<?php

namespace Lunar\Shipping\Panel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lunar\Core\Facades\Converter;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Shipping\Models\ShippingMethod;

/**
 * Shared by the shipping method store and update endpoints. Store carries the name, code and driver;
 * update carries every field, the weekly schedule (null: always available) and the customer groups the
 * method is offered to, which replace what the method had.
 */
class ShippingMethodRequest extends FormRequest
{
    public const DRIVERS = ['ship-by', 'collection'];

    public const CHARGE_BY = ['cart_total', 'weight'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $method = $this->route('shippingMethod');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique(ShippingMethod::class, 'code')->ignore($method instanceof ShippingMethod ? $method->id : null)],
            'driver' => ['required', Rule::in(self::DRIVERS)],
            'description' => ['sometimes', 'nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
            'stock_available' => ['sometimes', 'boolean'],
            'charge_by' => ['sometimes', Rule::in(self::CHARGE_BY)],
            'weight_unit' => ['sometimes', 'nullable', Rule::in(array_keys(Converter::getMeasurements()['weight'] ?? []))],
            'min_weight' => ['sometimes', 'nullable', 'required_with:weight_unit', 'numeric', 'min:0'],
            'max_weight' => ['sometimes', 'nullable', 'required_with:weight_unit', 'numeric', 'gt:min_weight'],
            'schedule' => ['sometimes', 'nullable', 'array:1,2,3,4,5,6,7'],
            'schedule.*.enabled' => ['required', 'boolean'],
            'schedule.*.from' => ['nullable', 'date_format:H:i'],
            'schedule.*.to' => ['nullable', 'date_format:H:i'],
            'customer_groups' => ['sometimes', 'array'],
            'customer_groups.*.customer_group_id' => ['required', 'distinct', Rule::exists(CustomerGroup::class, 'id')],
            'customer_groups.*.enabled' => ['required', 'boolean'],
            'customer_groups.*.visible' => ['required', 'boolean'],
            'customer_groups.*.starts_at' => ['nullable', 'date'],
            'customer_groups.*.ends_at' => ['nullable', 'date'],
        ];
    }

    /**
     * A day's closing time comes after its opening time, and a customer group's end date after its start
     * date, when both are set.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('schedule', []) as $day => $hours) {
                $from = $hours['from'] ?? null;
                $to = $hours['to'] ?? null;

                if (filled($from) && filled($to) && strcmp((string) $to, (string) $from) <= 0) {
                    $validator->errors()->add("schedule.{$day}.to", __('validation.after', ['attribute' => 'to', 'date' => $from]));
                }
            }

            foreach ((array) $this->input('customer_groups', []) as $index => $group) {
                $startsAt = $group['starts_at'] ?? null;
                $endsAt = $group['ends_at'] ?? null;

                if (filled($startsAt) && filled($endsAt) && strtotime((string) $endsAt) <= strtotime((string) $startsAt)) {
                    $validator->errors()->add("customer_groups.{$index}.ends_at", __('validation.after', ['attribute' => 'ends at', 'date' => $startsAt]));
                }
            }
        }];
    }
}
