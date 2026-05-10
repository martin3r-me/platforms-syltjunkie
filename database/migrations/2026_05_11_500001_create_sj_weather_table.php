<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_weather', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->date('date');
            $table->enum('record_type', ['current', 'forecast']);

            $table->decimal('temperature_min', 5, 1)->nullable();
            $table->decimal('temperature_max', 5, 1)->nullable();
            $table->decimal('temperature_avg', 5, 1)->nullable();
            $table->decimal('precipitation_mm', 6, 1)->nullable();
            $table->decimal('wind_speed_avg', 5, 1)->nullable();
            $table->decimal('wind_gust_max', 5, 1)->nullable();
            $table->unsignedSmallInteger('wind_direction')->nullable();
            $table->unsignedTinyInteger('cloud_cover_avg')->nullable();
            $table->decimal('pressure_msl', 6, 1)->nullable();
            $table->decimal('sunshine_hours', 4, 1)->nullable();
            $table->unsignedInteger('visibility_avg')->nullable();
            $table->unsignedTinyInteger('relative_humidity_avg')->nullable();
            $table->string('condition', 50)->nullable();
            $table->string('icon', 50)->nullable();

            $table->json('hourly_data')->nullable();
            $table->string('dwd_station_id', 20)->nullable();

            $table->timestamps();

            $table->unique(['entity_id', 'date', 'record_type']);
            $table->index(['team_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_weather');
    }
};
