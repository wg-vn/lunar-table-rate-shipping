<?php

namespace Lunar\Shipping;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Facades\Discounts;
use Lunar\Core\Facades\ModelManifest;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\Product;
use Lunar\Core\Modifiers\ShippingModifiers;
use Lunar\Panel\Facades\Panel;
use Lunar\Panel\PanelManager;
use Lunar\Shipping\DiscountTypes\ShippingDiscount;
use Lunar\Shipping\Interfaces\ShippingMethodManagerInterface;
use Lunar\Shipping\Managers\PostcodeManager;
use Lunar\Shipping\Managers\ShippingManager;
use Lunar\Shipping\Models\ShippingExclusion;
use Lunar\Shipping\Models\ShippingExclusionList;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Shipping\Models\ShippingZonePostcode;
use Lunar\Shipping\Observers\OrderObserver;
use Lunar\Shipping\Panel\ShippingDiscountForm;
use Lunar\Shipping\Panel\ShippingSection;
use Lunar\Shipping\Resolvers\PostcodeResolver;

class ShippingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shipping-tables.php', 'lunar.shipping-tables');

        $this->app->singleton(PostcodeManager::class, function () {
            $manager = new PostcodeManager;
            $manager->addResolver(PostcodeResolver::class);

            return $manager;
        });
    }

    public function boot(ShippingModifiers $shippingModifiers)
    {
        if (! config('lunar.shipping-tables.enabled')) {
            return;
        }

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunarpanel.shipping');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunar-shipping');

        if (! config('lunar.database.disable_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $shippingModifiers->add(
            ShippingModifier::class,
        );

        Discounts::addType(ShippingDiscount::class);

        $this->registerPanel();

        Order::observe(OrderObserver::class);

        Order::resolveRelationUsing('shippingZone', function ($orderModel) {
            $prefix = config('lunar.database.table_prefix');

            return $orderModel->belongsToMany(
                ShippingZone::class,
                "{$prefix}order_shipping_zone"
            )->withTimestamps();
        });

        CustomerGroup::resolveRelationUsing('shippingMethods', function ($customerGroup) {
            $prefix = config('lunar.database.table_prefix');

            return $customerGroup->belongsToMany(
                ShippingMethod::class,
                "{$prefix}customer_group_shipping_method"
            )->withTimestamps();
        });

        Product::resolveRelationUsing('shippingExclusions', function ($product) {
            return $product->morphMany(ShippingExclusion::class, 'purchasable');
        });

        $this->app->bind(ShippingMethodManagerInterface::class, function ($app) {
            return $app->make(ShippingManager::class);
        });

        ModelManifest::addDirectory(
            __DIR__.'/Models'
        );

        Relation::morphMap([
            'shipping_exclusion' => ShippingExclusion::class,
            'shipping_exclusion_list' => ShippingExclusionList::class,
            'shipping_method' => ShippingMethod::class,
            'shipping_rate' => ShippingRate::class,
            'shipping_zone' => ShippingZone::class,
            'shipping_zone_postcode' => ShippingZonePostcode::class,
        ]);
    }

    /**
     * The shipping screens in Lunar's Inertia panel, their compiled Vue bundle (`build/`, published or
     * linked to `public/vendor/lunar-panel/lunar-shipping`) and the shipping discount's form.
     */
    protected function registerPanel(): void
    {
        Panel::section(new ShippingSection);

        $this->app->make(PanelManager::class)
            ->vite('lunar-shipping', [
                'input' => 'resources/js/addon.ts',
                'hotFile' => null,
                'buildDirectory' => 'vendor/lunar-panel/lunar-shipping',
                '__buildSourcePath' => dirname(__DIR__).'/build',
            ])
            ->discountTypeForm(ShippingDiscount::class, ShippingDiscountForm::class);
    }
}
