<?php

namespace Lunar\Tests\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Lunar\Core\LunarServiceProvider;
use Lunar\Nestedset\NestedSetServiceProvider;
use Lunar\Shipping\ShippingServiceProvider;
use Lunar\Tests\Shipping\Stubs\User;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

use function Orchestra\Testbench\after_resolving;
use function Orchestra\Testbench\default_migration_path;

/**
 * Testbench base for the shipping tests, on a SQLite file per process (the lunarphp/lunar monorepo's
 * shared test base, reduced to SQLite).
 */
class TestCase extends BaseTestCase
{
    private static bool $databasePrepared = false;

    protected function setUp(): void
    {
        parent::setUp();
        // additional setup
        Config::set('providers.users.model', User::class);
        Config::set('lunar.urls.generator', null);
        activity()->disableLogging();

    }

    protected function getPackageProviders($app)
    {
        return [
            LunarServiceProvider::class,
            MediaLibraryServiceProvider::class,
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            NestedSetServiceProvider::class,
            ShippingServiceProvider::class,
            BlinkServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        Model::preventLazyLoading();

        $path = sys_get_temp_dir().'/lunar-shipping-test-'.(env('TEST_TOKEN') ?: getmypid()).'.sqlite';

        // A clean file once per process, so a stale schema from an earlier run never meets the migrations.
        if (! static::$databasePrepared) {
            @unlink($path);
            touch($path);
            static::$databasePrepared = true;
        }

        $app['config']->set('database.connections.testing.database', $path);
    }

    // Laravel's own migrations run on the same migrator, so RefreshDatabase keeps its per-process cache.
    protected function defineDatabaseMigrations(): void
    {
        after_resolving($this->app, 'migrator', static function ($migrator) {
            $migrator->path(default_migration_path());
        });
    }
}
