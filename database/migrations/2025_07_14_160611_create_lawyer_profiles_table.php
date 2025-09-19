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
        Schema::create('lawyer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nba_enrollment_number')->unique()->nullable()->comment('Bar number');
            $table->string('year_of_call')->nullable();
            $table->string('law_school')->nullable();
            $table->year('graduation_year')->nullable();
            $table->text('bio')->nullable()->comment('Professional bio');
            $table->string('office_address')->nullable();
            $table->unsignedInteger('hourly_rate')->nullable()->comment('Rate in the smallest currency unit (e.g., kobo, cents)');
            $table->unsignedInteger('consultation_fee')->nullable()->comment('Fee in the smallest currency unit');
            $table->json('availability')->nullable()->comment('Stores lawyer\'s available time slots');
            $table->string('bar_certificate_path')->nullable();
            $table->string('cv_path')->nullable();

            $table->enum('status', [
                'pending_onboarding', // Initial state after signup
                'pending_review',     // After lawyer submits the onboarding form
                'verified',           // Approved by admin
                'rejected',            // Rejected by admin
                'in_progress' ,         // In Progress with onboarding
                'suspended'           // Suspended by admin
            ])->default('pending_onboarding');
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lawyer_profiles');
    }
};
