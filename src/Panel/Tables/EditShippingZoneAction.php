<?php

namespace Lunar\Shipping\Panel\Tables;

class EditShippingZoneAction extends EditRowAction
{
    protected function routePrefix(): string
    {
        return 'panel.settings.shipping-zones';
    }
}
