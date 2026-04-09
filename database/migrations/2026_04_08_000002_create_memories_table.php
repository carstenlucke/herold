<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('scope');
            $table->string('category');
            $table->text('content');
            $table->string('source');
            $table->timestamps();
            $table->index(['scope', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
