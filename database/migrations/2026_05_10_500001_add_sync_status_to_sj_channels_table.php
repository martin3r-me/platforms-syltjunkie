<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_channels', function (Blueprint $table) {
            $table->string('sync_status', 30)->default('idle')->after('config');
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->timestamp('last_synced_at')->nullable()->after('sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('sj_channels', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'sync_error', 'last_synced_at']);
        });
    }
};
