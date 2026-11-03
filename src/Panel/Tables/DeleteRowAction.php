<?php

namespace Lunar\Shipping\Panel\Tables;

use Lunar\Panel\Support\Position;
use Lunar\Panel\Tables\TableAction;

/**
 * The Delete entry of a shipping table's row menu, asking for confirmation before it deletes the row's record.
 */
abstract class DeleteRowAction extends TableAction
{
    /** Route name prefix of the resource, e.g. `panel.settings.shipping-zones`. */
    abstract protected function routePrefix(): string;

    public function key(): string
    {
        return 'delete';
    }

    public function label(): string
    {
        return __('panel::common.delete');
    }

    public function icon(): ?string
    {
        return 'trash';
    }

    public function position(): Position
    {
        return Position::priority(90);
    }

    public function method(): string
    {
        return 'delete';
    }

    public function confirmationMessage(): ?string
    {
        return __('lunar-shipping::panel.common.confirm_delete');
    }

    public function url(mixed $record = null): ?string
    {
        return $record ? route($this->routePrefix().'.destroy', $record) : null;
    }
}
