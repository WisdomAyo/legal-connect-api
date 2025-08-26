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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lawyer_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->text('client_notes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled_by_client', 'cancelled_by_lawyer'])->default('pending');
            $table->unsignedInteger('fee')->nullable()->comment('The agreed fee for this specific consultation in kobo/cents');
            $table->timestamps();

            $table->index(['client_id', 'start_time']);
            $table->index(['lawyer_id', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
