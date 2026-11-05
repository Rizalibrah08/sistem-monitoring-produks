<?php

namespace Database\Seeders;

use App\StaffRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (StaffRole::cases() as $staffRole) {
                foreach ($staffRole->permissions() as $permission) {
                    Permission::findOrCreate($permission, 'web');
                }

                Role::findOrCreate($staffRole->value, 'web')->syncPermissions($staffRole->permissions());
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
