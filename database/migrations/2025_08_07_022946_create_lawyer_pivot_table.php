<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up(): void
    {
        // Pivot table for LawyerProfile <-> PracticeArea
        Schema::create('lawyer_practice_area', function (Blueprint $table) {
            $table->foreignId('lawyer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('practice_area_id')->constrained()->onDelete('cascade');
            $table->primary(['lawyer_profile_id', 'practice_area_id']);
        });

        // Pivot table for LawyerProfile <-> Specialization
        Schema::create('lawyer_specialization', function (Blueprint $table) {
            $table->foreignId('lawyer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('specialization_id')->constrained()->onDelete('cascade');
            $table->primary(['lawyer_profile_id', 'specialization_id']);
        });

        // Pivot table for LawyerProfile <-> Language
        Schema::create('lawyer_language', function (Blueprint $table) {
            $table->foreignId('lawyer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('language_id')->constrained()->onDelete('cascade');
            $table->primary(['lawyer_profile_id', 'language_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
          Schema::dropIfExists('lawyer_language');
          Schema::dropIfExists('lawyer_specialization');
          Schema::dropIfExists('lawyer_practice_area');
    }
};
