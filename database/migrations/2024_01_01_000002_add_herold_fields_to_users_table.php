<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_key_hash')->nullable()->after('password');
            $table->text('totp_secret')->nullable()->after('api_key_hash');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['api_key_hash', 'totp_secret', 'totp_confirmed_at']);
        });
    }
};
