<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_image_entity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sj_image_id')->constrained('sj_images')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['sj_image_id', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_image_entity');
    }
};
