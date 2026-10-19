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
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->unsignedBigInteger('mfa_last_used_step')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mfa_secret',
                'mfa_recovery_codes',
                'mfa_enabled_at',
                'mfa_last_used_step',
            ]);
        });
    }
};
