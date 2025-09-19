<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // The order is important here.
        // Roles must exist before we can assign them to users.
        // Locations and Legal Data should exist before users try to select them.
        $this->call([
            RoleSeeder::class,
            LocationSeeder::class,
            LegalDataSeeder::class,
            UserSeeder::class, // Run this last to create test users
        ]);
    }
}
