<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- sj_entities: JSON geometry → native GEOMETRY ---

        // Clean up leftover 'geo' column from a previous failed run
        if (Schema::hasColumn('sj_entities', 'geo')) {
            Schema::table('sj_entities', function ($table) {
                $table->dropColumn('geo');
            });
        }

        DB::statement('ALTER TABLE sj_entities ADD COLUMN geo GEOMETRY SRID 4326 NULL AFTER geometry');

        DB::statement('UPDATE sj_entities SET geo = ST_GeomFromGeoJSON(CAST(geometry AS CHAR), 1, 4326) WHERE geometry IS NOT NULL');

        Schema::table('sj_entities', function ($table) {
            $table->dropColumn('geometry');
        });

        DB::statement('ALTER TABLE sj_entities CHANGE geo geometry GEOMETRY SRID 4326 NULL');

        // --- sj_images: add location POINT ---
        if (!Schema::hasColumn('sj_images', 'location')) {
            DB::statement('ALTER TABLE sj_images ADD COLUMN location POINT SRID 4326 NULL AFTER longitude');
        }

        DB::statement('UPDATE sj_images SET location = ST_SRID(POINT(longitude, latitude), 4326) WHERE latitude IS NOT NULL AND longitude IS NOT NULL');
    }

    public function down(): void
    {
        // --- sj_images: drop location ---
        Schema::table('sj_images', function ($table) {
            $table->dropColumn('location');
        });

        // --- sj_entities: native GEOMETRY → JSON geometry ---

        DB::statement('ALTER TABLE sj_entities ADD COLUMN geo_json JSON NULL AFTER geometry');

        DB::statement('UPDATE sj_entities SET geo_json = ST_AsGeoJSON(geometry) WHERE geometry IS NOT NULL');

        Schema::table('sj_entities', function ($table) {
            $table->dropColumn('geometry');
        });

        DB::statement('ALTER TABLE sj_entities CHANGE geo_json geometry JSON NULL');
    }
};
