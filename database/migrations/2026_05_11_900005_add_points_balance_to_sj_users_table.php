<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_users', function (Blueprint $table) {
            $table->unsignedInteger('points_balance')->default(0)->after('last_login_at');
            $table->string('current_level', 30)->default('tagesgast')->after('points_balance');
        });
    }

    public function down(): void
    {
        Schema::table('sj_users', function (Blueprint $table) {
            $table->dropColumn(['points_balance', 'current_level']);
        });
    }
};
