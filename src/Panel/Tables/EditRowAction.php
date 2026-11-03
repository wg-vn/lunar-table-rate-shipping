<?php

namespace Lunar\Shipping\Panel\Tables;

use Lunar\Panel\Support\Position;
use Lunar\Panel\Tables\TableAction;

/**
 * The Edit entry of a shipping table's row menu, linking to `{resource}.edit` for the row's record.
 */
abstract class EditRowAction extends TableAction
{
    /** Route name prefix of the resource, e.g. `panel.settings.shipping-zones`. */
    abstract protected function routePrefix(): string;

    public function key(): string
    {
        return 'edit';
    }

    public function label(): string
    {
        return __('panel::common.edit');
    }

    public function icon(): ?string
    {
        return 'edit';
    }

    public function position(): Position
    {
        return Position::priority(10);
    }

    public function method(): string
    {
        return 'get';
    }

    public function url(mixed $record = null): ?string
    {
        return $record ? route($this->routePrefix().'.edit', $record) : null;
    }
}
