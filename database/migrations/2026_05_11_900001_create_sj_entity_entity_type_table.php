<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_entity_entity_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('entity_type_id');
            $table->boolean('is_primary')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['entity_id', 'entity_type_id']);

            $table->foreign('entity_id')
                ->references('id')->on('sj_entities')
                ->cascadeOnDelete();

            $table->foreign('entity_type_id')
                ->references('id')->on('sj_entity_types')
                ->cascadeOnDelete();
        });

        // Migrate existing entity_type_id data into pivot table
        DB::statement("
            INSERT INTO sj_entity_entity_type (entity_id, entity_type_id, is_primary, `order`, created_at, updated_at)
            SELECT id, entity_type_id, 1, 0, NOW(), NOW()
            FROM sj_entities
            WHERE entity_type_id IS NOT NULL AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_entity_entity_type');
    }
};
