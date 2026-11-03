<?php

namespace Lunar\Shipping\Panel\Tables;

use Lunar\Panel\Tables\TableExtension;

/**
 * Row actions of the shipping methods table, registered through the panel's table extension seam so
 * other add-ons can add to or reorder them.
 */
class ShippingMethodsTableExtension extends TableExtension
{
    /** @return list<class-string> */
    public function actions(): array
    {
        return [
            EditShippingMethodAction::class,
            DeleteShippingMethodAction::class,
        ];
    }
}
