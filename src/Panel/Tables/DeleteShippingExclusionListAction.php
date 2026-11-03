<?php

namespace Lunar\Shipping\Panel\Tables;

class DeleteShippingExclusionListAction extends DeleteRowAction
{
    protected function routePrefix(): string
    {
        return 'panel.settings.shipping-exclusion-lists';
    }
}
