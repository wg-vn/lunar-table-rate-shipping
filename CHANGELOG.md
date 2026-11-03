# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Versions follow
upstream Lunar's with one more segment; see the Releasing section of the README.

## [2.0.0-alpha.6.2] - 2026-11-03

### Changed
- `lunarphp/core` and `lunarphp/panel` are required as `>=2.0.0-alpha.6 <2.0.0-alpha.7` instead of the
  exact `2.0.0-alpha.6`, which `composer validate --strict` refuses. Both still resolve to the
  `2.0.0-alpha.6` line only.

### Added
- GitHub Actions, as in `wg-vn/lunar-core`: tests on PHP 8.4 and 8.5 with Laravel 12 and 13, a code
  style check, dependency review on pull requests, and a release workflow that notifies Packagist and
  creates a GitHub Release from this changelog when a `v*` tag is pushed.

## [2.0.0-alpha.6.1] - 2026-11-03

First release of the fork, on upstream `lunarphp/table-rate-shipping` `2.0.0-alpha.6`.

### Added
- Settings > Shipping screens in Lunar's Inertia panel (`lunarphp/panel`), behind the
  `shipping:manage` permission: shipping zones (coverage by country, states or postcodes, exclusion
  lists, rates with a price per currency and price breaks by minimum spend or weight, optionally per
  customer group), shipping methods (driver, charge by cart total or weight, weight limits, weekly
  schedule, customer group availability) and exclusion lists.
- `ShippingDiscountForm`, the shipping discount's form in the panel's discount editor.
- The compiled screens in `build/`, published with `vendor:publish --tag=lunar-shipping-panel-assets`.
- The upstream tests, run from this repository on Orchestra Testbench, and tests for the screens.

### Changed
- Requires `lunarphp/core` and `lunarphp/panel` from the `2.0.0-alpha.6` line instead of `lunarphp/admin` and
  `filament/filament`, and replaces `lunarphp/table-rate-shipping`.
- The `shipping:manage` permission migration takes its guard from `lunar.staff.guard`; upstream read
  it from the Filament panel and skipped the permission without one.

### Fixed
- The `ShippingExclusion`, `ShippingExclusionList` and `ShippingZonePostcode` factories are in
  `Lunar\Shipping\Database\Factories`, the namespace the package autoloads; upstream's
  `Lunar\Shipping\Factories` only resolved inside the monorepo.

### Removed
- The Filament resources, `ShippingPlugin` and the shipping discount's Filament schema.
