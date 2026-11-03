<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Price;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Tests\Shipping\Panel\PanelTestCase;

uses(PanelTestCase::class)->group('shipping', 'shipping-panel');
uses(RefreshDatabase::class);

function methodPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ground',
        'code' => 'GROUND',
        'driver' => 'ship-by',
        'description' => 'Three to five days.',
        'enabled' => true,
        'stock_available' => false,
        'charge_by' => 'cart_total',
        'weight_unit' => null,
        'min_weight' => null,
        'max_weight' => null,
        'schedule' => null,
        'customer_groups' => [],
    ], $overrides);
}

it('refuses staff without the shipping permission', function () {
    $this->actingAsStaff(canManageShipping: false);

    $this->get(route('panel.settings.shipping-methods.index'))->assertForbidden();
});

it('creates a method and opens it for editing', function () {
    $this->actingAsStaff();

    $response = $this->post(route('panel.settings.shipping-methods.store'), ['name' => 'Ground', 'code' => 'GROUND', 'driver' => 'ship-by']);

    $method = ShippingMethod::query()->sole();
    $response->assertRedirect(route('panel.settings.shipping-methods.edit', $method));
    expect($method->only(['name', 'code', 'driver']))->toBe(['name' => 'Ground', 'code' => 'GROUND', 'driver' => 'ship-by']);
});

it('refuses a code that another method uses, or an unknown driver', function () {
    $this->actingAsStaff();
    ShippingMethod::factory()->create(['code' => 'GROUND']);

    $this->post(route('panel.settings.shipping-methods.store'), ['name' => 'Ground', 'code' => 'GROUND', 'driver' => 'teleport'])
        ->assertSessionHasErrors(['code', 'driver']);
});

it('saves settings, schedule and customer groups, keeping other data', function () {
    $this->actingAsStaff();
    $group = CustomerGroup::factory()->create();
    $method = ShippingMethod::factory()->create(['code' => 'GROUND', 'data' => ['charge_by' => 'cart_total', 'custom' => 'kept']]);

    $this->put(route('panel.settings.shipping-methods.update', $method), methodPayload([
        'charge_by' => 'weight',
        'weight_unit' => 'kg',
        'min_weight' => 0,
        'max_weight' => 30,
        'schedule' => ['1' => ['enabled' => true, 'from' => '09:00', 'to' => '17:00'], '7' => ['enabled' => false, 'from' => null, 'to' => null]],
        'customer_groups' => [['customer_group_id' => $group->id, 'enabled' => true, 'visible' => true, 'starts_at' => null, 'ends_at' => null]],
    ]))->assertSessionHasNoErrors();

    $method->refresh();
    expect($method->data['charge_by'])->toBe('weight')
        ->and($method->data['custom'])->toBe('kept')
        ->and($method->data['schedule']['1'])->toBe(['enabled' => true, 'from' => '09:00', 'to' => '17:00'])
        ->and((float) $method->max_weight)->toBe(30.0)
        ->and($method->customerGroups()->sole()->pivot->enabled)->toBeTruthy();
});

it('shows the method with its customer groups', function () {
    $this->actingAsStaff();
    $group = CustomerGroup::factory()->create();
    $method = ShippingMethod::factory()->create(['code' => 'GROUND']);
    $method->customerGroups()->attach($group->id, ['enabled' => true, 'visible' => false]);

    $this->get(route('panel.settings.shipping-methods.edit', $method))->assertInertia(fn (Assert $page) => $page
        ->component('lunar-shipping::Methods/Edit')
        ->where('method.code', 'GROUND')
        ->where('method.customer_groups.0.customer_group_id', $group->id)
        ->where('method.customer_groups.0.visible', false));
});

it('refuses a schedule that ends before it starts', function () {
    $this->actingAsStaff();
    $method = ShippingMethod::factory()->create(['code' => 'GROUND']);

    $this->put(route('panel.settings.shipping-methods.update', $method), methodPayload([
        'schedule' => ['1' => ['enabled' => true, 'from' => '17:00', 'to' => '09:00']],
    ]))->assertSessionHasErrors('schedule.1.to');
});

it('accepts an open-ended day or group, and refuses an end before the start', function () {
    $this->actingAsStaff();
    $group = CustomerGroup::factory()->create();
    $method = ShippingMethod::factory()->create(['code' => 'GROUND']);

    $this->put(route('panel.settings.shipping-methods.update', $method), methodPayload([
        'schedule' => ['1' => ['enabled' => true, 'from' => null, 'to' => '17:00']],
        'customer_groups' => [['customer_group_id' => $group->id, 'enabled' => true, 'visible' => true, 'starts_at' => null, 'ends_at' => '2026-12-31']],
    ]))->assertSessionHasNoErrors();

    $this->put(route('panel.settings.shipping-methods.update', $method), methodPayload([
        'customer_groups' => [['customer_group_id' => $group->id, 'enabled' => true, 'visible' => true, 'starts_at' => '2026-12-31', 'ends_at' => '2026-01-01']],
    ]))->assertSessionHasErrors('customer_groups.0.ends_at');
});

it('deletes a method with its rates and their prices', function () {
    $this->actingAsStaff();
    $method = ShippingMethod::factory()->create();
    $rate = ShippingRate::factory()->create(['shipping_method_id' => $method->id, 'shipping_zone_id' => ShippingZone::factory()]);
    Price::factory()->create(['priceable_type' => $rate->getMorphClass(), 'priceable_id' => $rate->id, 'currency_id' => Currency::getDefault()->id]);

    $this->delete(route('panel.settings.shipping-methods.destroy', $method))
        ->assertRedirect(route('panel.settings.shipping-methods.index'));

    expect(ShippingMethod::query()->count())->toBe(0)
        ->and(Price::query()->count())->toBe(0);
});
