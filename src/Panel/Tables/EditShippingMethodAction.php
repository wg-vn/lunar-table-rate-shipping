<?php

namespace Lunar\Shipping\Panel\Tables;

class EditShippingMethodAction extends EditRowAction
{
    protected function routePrefix(): string
    {
        return 'panel.settings.shipping-methods';
    }
}
