# wg-vn/lunar-table-rate-shipping

Lunar's table rate shipping add-on for the Inertia panel (`lunarphp/panel` 2.x), forked from
[`lunarphp/table-rate-shipping`](https://github.com/lunarphp/table-rate-shipping). Upstream's 2.x add-on
requires `lunarphp/admin` and Filament, which a store running Lunar's Inertia panel does not have; this
fork drops them and adds the shipping screens to the Inertia panel. Not affiliated with LunarPHP Ltd.

The PHP namespace stays `Lunar\Shipping\`, and the package declares `replace: lunarphp/table-rate-shipping`.
For what table rate shipping does (zones, methods, rates, exclusion lists, the shipping modifier and
discount type), see the [Lunar documentation](https://docs.lunarphp.com/).

## How it differs from upstream

| | Upstream `lunarphp/table-rate-shipping` 2.x | `wg-vn/lunar-table-rate-shipping` |
| --- | --- | --- |
| Requires | `lunarphp/admin`, `filament/filament` ^5 | `lunarphp/core` and `lunarphp/panel` (same exact version) |
| Admin screens | Filament resources (`ShippingPlugin`) | Settings > Shipping in the Inertia panel: zones (coverage, exclusion lists, rates and price breaks), methods (driver, weight limits, weekly schedule, customer groups), exclusion lists |
| Shipping discount form | Filament schema on `ShippingDiscount` | `ShippingDiscountForm`, the panel's `DiscountTypeForm` |
| `shipping:manage` permission guard | read from the Filament panel (skipped without it) | `lunar.staff.guard` |
| `ShippingExclusion`, `ShippingExclusionList`, `ShippingZonePostcode` factories | namespace `Lunar\Shipping\Factories`, which the package does not autoload | `Lunar\Shipping\Database\Factories` |
| Tests | in the monorepo (`tests/shipping`) | in this repository, on Orchestra Testbench, plus tests for the panel screens |

## Installation

```bash
composer require wg-vn/lunar-table-rate-shipping
php artisan migrate
php artisan vendor:publish --tag=lunar-shipping-panel-assets --force
```

The last command copies the compiled screens (`build/`) to `public/vendor/lunar-panel/lunar-shipping`;
run it again after every update (`--tag=panel-all-assets` publishes the panel and every add-on). In local
development `php artisan lunar:panel:link` symlinks `build/` instead. The add-on is on unless
`LUNAR_SHIPPING_TABLES_ENABLED=false`.

Staff need the `shipping:manage` permission (or Lunar's admin flag) to see Settings > Shipping.

## Development

```bash
composer install
vendor/bin/pest
pnpm install
pnpm build          # writes build/, which is committed so host apps need no Node
```

The screens are Vue pages in `resources/js`, compiled with `@lunarphp/panel-vite-plugin` into one bundle
that uses the panel's own Vue, Inertia and components. Their strings are `resources/lang/en/panel.php`,
served to the panel under the `lunar-shipping` namespace.

## Syncing with upstream

This repository's history starts at upstream's `2.0.0-alpha.6` tag of `lunarphp/table-rate-shipping`
(the read-only split of the monorepo's `packages/table-rate-shipping`), then a commit adding upstream's
tests from the monorepo, then this fork's changes. To take a later upstream release, for example
`2.0.0-alpha.7`:

```bash
git remote add upstream https://github.com/lunarphp/table-rate-shipping.git   # once
git fetch upstream --tags
git merge 2.0.0-alpha.7
```

Conflicts appear only where this fork changed the same files (mostly `src/Filament`, which is gone here,
and `ShippingDiscount`). Upstream's tests live in the monorepo, so bring their changes over separately:

```bash
git -C ../lunar fetch upstream --tags
git -C ../lunar diff 2.0.0-alpha.6 2.0.0-alpha.7 -- tests/shipping \
  | git apply -p3 --directory=tests --3way
```

Then match `lunarphp/core` and `lunarphp/panel` in `composer.json` to the new release, read upstream's
changelog for migrations that changed in place, run the tests, and rebuild `build/` if the panel's npm
packages (`@lunarphp/panel`, `@lunarphp/panel-vite-plugin`) changed.
