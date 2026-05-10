<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_image_entity', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('is_primary');
            $table->unsignedInteger('distance_m')->nullable()->after('source');

            $table->index(['entity_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('sj_image_entity', function (Blueprint $table) {
            $table->dropIndex(['entity_id', 'source']);
            $table->dropColumn(['source', 'distance_m']);
        });
    }
};
