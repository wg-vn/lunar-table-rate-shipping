<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Tests\Shipping\TestCase;
use Spatie\Permission\Models\Permission;

uses(TestCase::class)->group('shipping');
uses(RefreshDatabase::class);

it('creates the shipping permission for the staff guard', function () {
    expect(Permission::query()->where('name', 'shipping:manage')->sole()->guard_name)
        ->toBe(config('lunar.staff.guard'));
});
