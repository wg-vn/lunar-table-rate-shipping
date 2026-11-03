<?php

namespace Lunar\Shipping\Panel\Tables;

class DeleteShippingMethodAction extends DeleteRowAction
{
    protected function routePrefix(): string
    {
        return 'panel.settings.shipping-methods';
    }
}
