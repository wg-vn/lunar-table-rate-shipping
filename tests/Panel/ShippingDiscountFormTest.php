<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Panel\PanelManager;
use Lunar\Shipping\DiscountTypes\ShippingDiscount;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Panel\ShippingDiscountForm;
use Lunar\Tests\Shipping\Panel\PanelTestCase;

uses(PanelTestCase::class)->group('shipping', 'shipping-panel');
uses(RefreshDatabase::class);

it('is the panel form of the shipping discount', function () {
    expect(app(PanelManager::class)->discountTypeForms()[ShippingDiscount::class] ?? null)->toBe(ShippingDiscountForm::class);
});

it('stores fixed prices in minor units and shows them back in major units', function () {
    $method = ShippingMethod::factory()->create(['name' => 'Ground']);
    $form = new ShippingDiscountForm;

    $stored = $form->toStorage([
        'methods' => [
            ['shipping_method_id' => (string) $method->id, 'type' => 'fixed', 'percentage' => null, 'prices' => ['USD' => '4.99']],
            ['shipping_method_id' => null, 'type' => 'percentage', 'percentage' => '50', 'prices' => ['USD' => null]],
        ],
        'shipping_methods' => [['id' => $method->id, 'name' => 'Ground']],
    ]);

    expect($stored)->toBe(['methods' => [
        ['shipping_method_id' => $method->id, 'type' => 'fixed', 'prices' => ['USD' => 499]],
        ['shipping_method_id' => null, 'type' => 'percentage', 'percentage' => 50.0],
    ]]);

    $shown = $form->toForm($stored);

    expect($shown['methods'][0]['prices']['USD'])->toBe(4.99)
        ->and($shown['methods'][1]['percentage'])->toBe(50.0)
        ->and($shown['shipping_methods'])->toBe([['id' => $method->id, 'name' => 'Ground']]);
});
