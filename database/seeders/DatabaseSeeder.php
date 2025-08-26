<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\Role;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Models\Permission;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
     public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create the 3 core roles
        SpatieRole::create(['name' => Role::Admin->value]);
        SpatieRole::create(['name' => Role::Lawyer->value]);
        SpatieRole::create(['name' => Role::Client->value]);

        // You can add permissions here later if needed
        // Permission::create(['name' => 'edit articles']);
        // SpatieRole::findByName(Role::Admin->value)->givePermissionTo('edit articles');
    }
}
