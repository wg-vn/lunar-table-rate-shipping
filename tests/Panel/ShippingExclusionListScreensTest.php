<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Core\Models\Product;
use Lunar\Shipping\Models\ShippingExclusionList;
use Lunar\Tests\Shipping\Panel\PanelTestCase;

uses(PanelTestCase::class)->group('shipping', 'shipping-panel');
uses(RefreshDatabase::class);

it('refuses staff without the shipping permission, product search included', function () {
    $this->actingAsStaff(canManageShipping: false);

    $this->get(route('panel.settings.shipping-exclusion-lists.index'))->assertForbidden();
    $this->getJson(route('panel.settings.shipping-exclusion-lists.products', ['q' => 'phone']))->assertForbidden();
});

it('creates a list and refuses a duplicate name', function () {
    $this->actingAsStaff();

    $this->post(route('panel.settings.shipping-exclusion-lists.store'), ['name' => 'Oversized'])
        ->assertRedirect(route('panel.settings.shipping-exclusion-lists.edit', ShippingExclusionList::query()->sole()));

    $this->post(route('panel.settings.shipping-exclusion-lists.store'), ['name' => 'Oversized'])
        ->assertSessionHasErrors('name');
});

it('replaces the products on a list', function () {
    $this->actingAsStaff();
    [$kept, $removed, $added] = Product::factory()->count(3)->create();
    $list = ShippingExclusionList::factory()->create();
    foreach ([$kept, $removed] as $product) {
        $list->exclusions()->create(['purchasable_type' => $product->getMorphClass(), 'purchasable_id' => $product->id]);
    }

    $this->put(route('panel.settings.shipping-exclusion-lists.update', $list), ['name' => $list->name, 'products' => [$kept->id, $added->id]])
        ->assertSessionHasNoErrors();

    expect($list->exclusions()->pluck('purchasable_id')->sort()->values()->all())->toBe([$kept->id, $added->id]);

    $this->get(route('panel.settings.shipping-exclusion-lists.edit', $list))->assertInertia(fn (Assert $page) => $page
        ->component('lunar-shipping::ExclusionLists/Edit')
        ->has('list.products', 2));
});

it('finds products by name for the picker', function () {
    $this->actingAsStaff();
    Product::factory()->create(['name' => collect(['en' => 'Desk Phone'])]);
    Product::factory()->create(['name' => collect(['en' => 'Headset'])]);

    $this->getJson(route('panel.settings.shipping-exclusion-lists.products', ['q' => 'Desk']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Desk Phone');
});
