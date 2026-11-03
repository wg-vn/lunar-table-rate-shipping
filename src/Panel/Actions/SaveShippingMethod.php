<?php

namespace Lunar\Shipping\Panel\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lunar\Shipping\Models\ShippingMethod;

/**
 * Saves a shipping method from the panel. `charge_by` and the weekly `schedule` live in the method's
 * `data` (the drivers and `ShippingMethod::isAvailable()` read them there); other keys in `data` are
 * kept. Customer groups, when given, replace the method's.
 */
class SaveShippingMethod
{
    /**
     * @param  array<string, mixed>  $data  validated `ShippingMethodRequest` input
     */
    public function execute(ShippingMethod $method, array $data): ShippingMethod
    {
        return DB::transaction(function () use ($method, $data): ShippingMethod {
            $method->fill(Arr::only($data, [
                'name', 'code', 'driver', 'description', 'enabled', 'stock_available', 'weight_unit', 'min_weight', 'max_weight',
            ]));

            $methodData = (array) ($method->data?->getArrayCopy() ?? []);

            if (array_key_exists('charge_by', $data)) {
                $methodData['charge_by'] = $data['charge_by'];
            }

            if (array_key_exists('schedule', $data)) {
                $methodData['schedule'] = $data['schedule'];
            }

            $method->data = $methodData;
            $method->save();

            if (array_key_exists('customer_groups', $data)) {
                $method->customerGroups()->sync(collect($data['customer_groups'])->mapWithKeys(fn (array $group) => [
                    $group['customer_group_id'] => Arr::only($group, ['enabled', 'visible', 'starts_at', 'ends_at']),
                ]));
            }

            return $method;
        });
    }
}
