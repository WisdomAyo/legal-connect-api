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
        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('step_name', 50); // personal_info, professional_info, etc.
            $table->json('step_data')->nullable(); // Store step-specific data
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_skipped')->default(false);
            $table->string('skip_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Ensure unique step per user
            $table->unique(['user_id', 'step_name']);
            $table->index(['user_id', 'is_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_steps');
    }
};
