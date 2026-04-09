<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type');
            $table->string('status');
            $table->string('audio_path')->nullable();
            $table->text('transcript')->nullable();
            $table->string('processed_title')->nullable();
            $table->text('processed_body')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('github_issue_number')->nullable();
            $table->string('github_issue_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_notes');
    }
};
