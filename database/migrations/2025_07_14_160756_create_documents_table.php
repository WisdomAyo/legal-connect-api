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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable'); 
            $table->foreignId('uploader_id')->constrained('users')->onDelete('cascade');
            $table->string('file_path')->comment('Path to the file on S3 or other disk');
            $table->string('original_filename');
            $table->unsignedInteger('file_size')->comment('Size in bytes');
            $table->string('mime_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
