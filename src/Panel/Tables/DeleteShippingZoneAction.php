<?php

namespace Lunar\Shipping\Panel\Tables;

class DeleteShippingZoneAction extends DeleteRowAction
{
    protected function routePrefix(): string
    {
        return 'panel.settings.shipping-zones';
    }
}
