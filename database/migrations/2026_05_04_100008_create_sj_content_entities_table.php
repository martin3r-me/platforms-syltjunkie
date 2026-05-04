<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_content_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_piece_id')->constrained('sj_content_pieces')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_primary')->default(false)->comment('Haupt-Entity bei entity_page');
            $table->string('cta_type', 30)->nullable()->comment('reserve_table, book_room, call, directions, visit_website, buy_ticket');
            $table->string('cta_override_url', 500)->nullable()->comment('Überschreibt Entity-Default');
            $table->timestamps();

            $table->unique(['content_piece_id', 'entity_id'], 'sj_content_entity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_content_entities');
    }
};
