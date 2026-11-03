<?php

namespace Lunar\Shipping\Panel\Tables;

class EditShippingExclusionListAction extends EditRowAction
{
    protected function routePrefix(): string
    {
        return 'panel.settings.shipping-exclusion-lists';
    }
}
