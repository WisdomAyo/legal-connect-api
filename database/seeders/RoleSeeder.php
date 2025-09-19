<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role; // Your Role Enum

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create the 3 core roles using the Enum
        Role::firstOrCreate(['name' => RoleEnum::Admin->value]);
        Role::firstOrCreate(['name' => RoleEnum::Lawyer->value]);
        Role::firstOrCreate(['name' => RoleEnum::Client->value]);
    }
}
