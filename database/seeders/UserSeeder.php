<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create an Admin user
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'phone_number' => '08000000000',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::Admin->value);

        // Create a verified Lawyer user (for testing onboarding)
        $lawyer = User::create([
            'first_name' => 'John',
            'last_name' => 'Lawyer',
            'email' => 'lawyer@example.com',
            'phone_number' => '08011111111',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $lawyer->assignRole(Role::Lawyer->value);
        $lawyer->lawyerProfile()->create(['status' => 'pending_onboarding']);

        // Create a Client user
        $client = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Client',
            'email' => 'client@example.com',
            'phone_number' => '08022222222',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $client->assignRole(Role::Client->value);
    }
}
