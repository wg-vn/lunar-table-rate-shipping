<?php

use Illuminate\Support\Facades\Schema;
use Lunar\Core\Database\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up()
    {
        // The staff guard both Lunar panels authorise against (`lunar.staff.guard`).
        $guard = config('lunar.staff.guard', 'staff');

        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['permissions'])) {
            return;
        }

        Permission::firstOrCreate([
            'name' => 'shipping:manage',
            'guard_name' => $guard,
        ]);
    }

    public function down()
    {
        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['permissions'])) {
            return;
        }

        Permission::query()->where('name', 'shipping:manage')->delete();
    }
};
