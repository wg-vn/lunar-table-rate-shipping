<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Price;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Tests\Shipping\Panel\PanelTestCase;

uses(PanelTestCase::class)->group('shipping', 'shipping-panel');
uses(RefreshDatabase::class);

function zonePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Mainland US',
        'type' => 'countries',
        'countries' => [],
        'country_id' => null,
        'states' => [],
        'postcodes' => '',
        'exclusion_lists' => [],
        'rates' => [],
    ], $overrides);
}

it('sends guests to the panel login', function () {
    $this->get(route('panel.settings.shipping-zones.index'))->assertRedirect(route('panel.login'));
});

it('refuses staff without the shipping permission', function () {
    $this->actingAsStaff(canManageShipping: false);

    $this->get(route('panel.settings.shipping-zones.index'))->assertForbidden();
});

it('lists shipping zones', function () {
    $this->actingAsStaff();
    ShippingZone::factory()->create(['name' => 'Mainland US', 'type' => 'countries']);

    $this->get(route('panel.settings.shipping-zones.index'))->assertInertia(fn (Assert $page) => $page
        ->component('lunar-shipping::Zones/Index')
        ->where('zones.data.0.name', 'Mainland US')
        ->where('zones.data.0.type', 'countries'));
});

it('creates a zone and opens it for editing', function () {
    $this->actingAsStaff();

    $response = $this->post(route('panel.settings.shipping-zones.store'), ['name' => 'Mainland US', 'type' => 'countries']);

    $zone = ShippingZone::query()->sole();
    $response->assertRedirect(route('panel.settings.shipping-zones.edit', $zone));
    expect($zone->only(['name', 'type']))->toBe(['name' => 'Mainland US', 'type' => 'countries']);
});

it('refuses a zone without a name or with an unknown type', function () {
    $this->actingAsStaff();

    $this->post(route('panel.settings.shipping-zones.store'), ['name' => '', 'type' => 'planets'])
        ->assertSessionHasErrors(['name', 'type']);
});

it('saves a postcode zone with its country and rates, prices in minor units', function () {
    $this->actingAsStaff();
    $currency = Currency::getDefault();
    $country = Country::factory()->create();
    $group = CustomerGroup::factory()->create();
    $method = ShippingMethod::factory()->create(['data' => ['charge_by' => 'cart_total']]);
    $zone = ShippingZone::factory()->create(['type' => 'countries']);

    $this->put(route('panel.settings.shipping-zones.update', $zone), zonePayload([
        'type' => 'postcodes',
        'country_id' => $country->id,
        'postcodes' => "AB1 2CD\nab1*\n\nAB12CD",
        'rates' => [[
            'id' => null,
            'shipping_method_id' => $method->id,
            'enabled' => true,
            'base_prices' => [$currency->id => '9.99'],
            'tiers' => [['customer_group_id' => $group->id, 'currency_id' => $currency->id, 'price' => '0', 'min_quantity' => '100']],
        ]],
    ]))->assertSessionHasNoErrors();

    $zone->refresh();
    expect($zone->type)->toBe('postcodes')
        ->and($zone->countries()->pluck('lunar_countries.id')->all())->toBe([$country->id])
        ->and($zone->postcodes()->pluck('postcode')->sort()->values()->all())->toBe(['AB12CD', 'ab1*']);

    $prices = ShippingRate::query()->sole()->prices()->get();
    expect($prices->firstWhere('customer_group_id', null)->only(['price', 'min_quantity']))->toBe(['price' => 999, 'min_quantity' => 1])
        ->and($prices->firstWhere('customer_group_id', $group->id)->only(['price', 'min_quantity']))->toBe(['price' => 0, 'min_quantity' => 10000]);
});

it('shows a rate back in major units', function () {
    $this->actingAsStaff();
    $currency = Currency::getDefault();
    $method = ShippingMethod::factory()->create(['data' => ['charge_by' => 'cart_total']]);
    $zone = ShippingZone::factory()->create(['type' => 'unrestricted']);
    $rate = ShippingRate::factory()->create(['shipping_zone_id' => $zone->id, 'shipping_method_id' => $method->id]);
    Price::factory()->create(['priceable_type' => $rate->getMorphClass(), 'priceable_id' => $rate->id, 'currency_id' => $currency->id, 'price' => 999, 'min_quantity' => 1]);
    Price::factory()->create(['priceable_type' => $rate->getMorphClass(), 'priceable_id' => $rate->id, 'currency_id' => $currency->id, 'price' => 0, 'min_quantity' => 10000]);

    $this->get(route('panel.settings.shipping-zones.edit', $zone))->assertInertia(fn (Assert $page) => $page
        ->component('lunar-shipping::Zones/Edit')
        ->where("zone.rates.0.base_prices.{$currency->id}", 9.99)
        ->where('zone.rates.0.tiers.0.min_quantity', 100)
        ->where('zone.rates.0.tiers.0.price', 0));
});

it('requires a default-currency price and whole-number weight breaks', function () {
    $this->actingAsStaff();
    $currency = Currency::getDefault();
    $method = ShippingMethod::factory()->create(['data' => ['charge_by' => 'weight']]);
    $zone = ShippingZone::factory()->create(['type' => 'unrestricted']);

    $this->put(route('panel.settings.shipping-zones.update', $zone), zonePayload([
        'type' => 'unrestricted',
        'rates' => [[
            'id' => null,
            'shipping_method_id' => $method->id,
            'enabled' => true,
            'base_prices' => [$currency->id => ''],
            'tiers' => [['customer_group_id' => null, 'currency_id' => $currency->id, 'price' => '5', 'min_quantity' => '2.5']],
        ]],
    ]))->assertSessionHasErrors(["rates.0.base_prices.{$currency->id}", 'rates.0.tiers.0.min_quantity']);

    expect(ShippingRate::query()->count())->toBe(0);
});

it('removes rates left out of an update, with their prices', function () {
    $this->actingAsStaff();
    $zone = ShippingZone::factory()->create(['type' => 'unrestricted']);
    $rate = ShippingRate::factory()->create(['shipping_zone_id' => $zone->id, 'shipping_method_id' => ShippingMethod::factory()]);
    Price::factory()->create(['priceable_type' => $rate->getMorphClass(), 'priceable_id' => $rate->id, 'currency_id' => Currency::getDefault()->id]);

    $this->put(route('panel.settings.shipping-zones.update', $zone), zonePayload(['type' => 'unrestricted']))
        ->assertSessionHasNoErrors();

    expect(ShippingRate::query()->count())->toBe(0)
        ->and(Price::query()->count())->toBe(0);
});

it('deletes a zone with its rates and their prices', function () {
    $this->actingAsStaff();
    $zone = ShippingZone::factory()->create();
    $rate = ShippingRate::factory()->create(['shipping_zone_id' => $zone->id, 'shipping_method_id' => ShippingMethod::factory()]);
    Price::factory()->create(['priceable_type' => $rate->getMorphClass(), 'priceable_id' => $rate->id, 'currency_id' => Currency::getDefault()->id]);

    $this->delete(route('panel.settings.shipping-zones.destroy', $zone))
        ->assertRedirect(route('panel.settings.shipping-zones.index'));

    expect(ShippingZone::query()->count())->toBe(0)
        ->and(Price::query()->count())->toBe(0);
});
