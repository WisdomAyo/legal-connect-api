<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegalDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedPracticeAreas();
        $this->seedSpecializations();
        $this->seedLanguages();
    }

    private function seedPracticeAreas(): void
    {
        $areas = ['Corporate Law', 'Criminal Law', 'Family Law', 'Real Estate Law', 'Intellectual Property', 'Litigation', 'Employment Law', 'Immigration Law', 'Tax Law'];
        foreach ($areas as $area) {
            DB::table('practice_areas')->insertOrIgnore(['name' => $area, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function seedSpecializations(): void
    {
        $specs = ['Mergers & Acquisitions', 'Divorce & Custody', 'DUI Defense', 'Patent Law', 'Contract Negotiation', 'Personal Injury', 'Startup Advisory', 'Estate Planning'];
        foreach ($specs as $spec) {
            DB::table('specializations')->insertOrIgnore(['name' => $spec, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function seedLanguages(): void
    {
        $langs = ['English', 'French', 'Spanish', 'Mandarin', 'Arabic', 'Yoruba', 'Igbo', 'Hausa'];
        foreach ($langs as $lang) {
            DB::table('languages')->insertOrIgnore(['name' => $lang, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
