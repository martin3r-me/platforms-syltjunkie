<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_entity_urls', function (Blueprint $table) {
            $table->string('google_place_id')->nullable()->after('is_active');
            $table->index('google_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('sj_entity_urls', function (Blueprint $table) {
            $table->dropIndex(['google_place_id']);
            $table->dropColumn('google_place_id');
        });
    }
};
