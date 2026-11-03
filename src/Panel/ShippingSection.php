<?php

namespace Lunar\Shipping\Panel;

use Closure;
use Illuminate\Support\Facades\Route;
use Lunar\Panel\Navigation\NavigationItem;
use Lunar\Panel\Navigation\NavigationRegistry;
use Lunar\Panel\Sections\Section;
use Lunar\Shipping\Panel\Http\Controllers\ShippingExclusionListController;
use Lunar\Shipping\Panel\Http\Controllers\ShippingMethodController;
use Lunar\Shipping\Panel\Http\Controllers\ShippingZoneController;
use Lunar\Shipping\Panel\Tables\ShippingExclusionListsTableExtension;
use Lunar\Shipping\Panel\Tables\ShippingMethodsTableExtension;
use Lunar\Shipping\Panel\Tables\ShippingZonesTableExtension;

/**
 * The table rate shipping screens in Lunar's Inertia panel: shipping zones (coverage and rates), shipping
 * methods and exclusion lists, under a Shipping group in Settings. Every route and navigation item is
 * gated by the `shipping:manage` permission this package's migration creates.
 */
class ShippingSection extends Section
{
    public const PERMISSION = 'shipping:manage';

    public function key(): string
    {
        return 'shipping';
    }

    /** @return list<string> */
    public function langNamespaces(): array
    {
        return ['lunar-shipping'];
    }

    /** @return array<string, class-string> */
    public function tableExtensions(): array
    {
        return [
            'shipping-zones.index' => ShippingZonesTableExtension::class,
            'shipping-methods.index' => ShippingMethodsTableExtension::class,
            'shipping-exclusion-lists.index' => ShippingExclusionListsTableExtension::class,
        ];
    }

    public function settingsNavigation(NavigationRegistry $registry): void
    {
        $registry->group('shipping', __('lunar-shipping::panel.nav.group'), priority: 35);

        foreach (['zones' => 10, 'methods' => 20, 'exclusion_lists' => 30] as $item => $priority) {
            $registry->addItem('shipping', new NavigationItem(
                key: 'shipping-'.str_replace('_', '-', $item),
                label: __("lunar-shipping::panel.nav.{$item}"),
                route: 'panel.settings.shipping-'.str_replace('_', '-', $item).'.index',
                permission: self::PERMISSION,
                priority: $priority,
            ));
        }
    }

    public function routes(): ?Closure
    {
        return function (): void {
            Route::prefix('settings/shipping')
                ->middleware('can:'.self::PERMISSION)
                ->group(function (): void {
                    Route::prefix('zones')->name('panel.settings.shipping-zones.')->controller(ShippingZoneController::class)
                        ->group(function (): void {
                            Route::get('/', 'index')->name('index');
                            Route::post('/', 'store')->name('store');
                            Route::get('/{shippingZone}/edit', 'edit')->name('edit');
                            Route::put('/{shippingZone}', 'update')->name('update');
                            Route::delete('/{shippingZone}', 'destroy')->name('destroy');
                        });

                    Route::prefix('methods')->name('panel.settings.shipping-methods.')->controller(ShippingMethodController::class)
                        ->group(function (): void {
                            Route::get('/', 'index')->name('index');
                            Route::post('/', 'store')->name('store');
                            Route::get('/{shippingMethod}/edit', 'edit')->name('edit');
                            Route::put('/{shippingMethod}', 'update')->name('update');
                            Route::delete('/{shippingMethod}', 'destroy')->name('destroy');
                        });

                    Route::prefix('exclusion-lists')->name('panel.settings.shipping-exclusion-lists.')->controller(ShippingExclusionListController::class)
                        ->group(function (): void {
                            Route::get('/', 'index')->name('index');
                            Route::post('/', 'store')->name('store');
                            Route::get('/products', 'searchProducts')->name('products');
                            Route::get('/{shippingExclusionList}/edit', 'edit')->name('edit');
                            Route::put('/{shippingExclusionList}', 'update')->name('update');
                            Route::delete('/{shippingExclusionList}', 'destroy')->name('destroy');
                        });
                });
        };
    }
}
