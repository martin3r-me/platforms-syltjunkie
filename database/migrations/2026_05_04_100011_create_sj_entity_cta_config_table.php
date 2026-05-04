<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_entity_cta_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->string('cta_type', 30);
            $table->string('target_url', 500)->nullable()->comment('Reservierungslink, Booking-URL');
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('tracking_id', 50)->nullable()->comment('Plausible Custom Property');
            $table->timestamps();

            $table->unique(['entity_id', 'cta_type'], 'sj_entity_cta_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_entity_cta_config');
    }
};
