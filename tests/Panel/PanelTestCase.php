<?php

namespace Lunar\Tests\Shipping\Panel;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Staff;
use Lunar\Panel\PanelServiceProvider;
use Lunar\Tests\Shipping\TestCase;

/**
 * The shipping tests' base with Lunar's Inertia panel booted, for the add-on's panel screens. Page files
 * are not checked: add-on pages are namespaced (`lunar-shipping::Zones/Index`) and compiled separately.
 */
class PanelTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Currency::factory()->create(['code' => 'USD', 'default' => true, 'enabled' => true, 'decimal_places' => 2, 'exchange_rate' => 1]);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            InertiaServiceProvider::class,
            PanelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }

    /**
     * A signed-in staff member, with the shipping permission unless told otherwise.
     */
    protected function actingAsStaff(bool $canManageShipping = true): Staff
    {
        $staff = Staff::factory()->create(['admin' => false]);

        if ($canManageShipping) {
            $staff->givePermissionTo('shipping:manage');
        }

        $this->actingAs($staff, config('lunar.staff.guard'));

        return $staff;
    }
}
