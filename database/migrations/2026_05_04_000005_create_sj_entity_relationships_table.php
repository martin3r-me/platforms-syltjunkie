<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_entity_relationships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('source_entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->foreignId('target_entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->foreignId('relation_type_id')->constrained('sj_relation_types')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_entity_id', 'relation_type_id']);
            $table->index(['target_entity_id', 'relation_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_entity_relationships');
    }
};
