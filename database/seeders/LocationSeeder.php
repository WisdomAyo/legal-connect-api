<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // You can expand this with a full list from a package or file later.
        // For now, we'll add a sample country, state, and city.

        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Nigeria',
            'country_code' => 'NG',
            'dialing_code' => '+234',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stateId = DB::table('states')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Lagos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cities')->insert([
            'state_id' => $stateId,
            'name' => 'Ikeja',
            'state_code' => 'LA',
            'country_id' => $countryId,
            'country_code' => 'NG',
            'flag' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
