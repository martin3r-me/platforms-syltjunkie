<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_cta_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->unsignedBigInteger('content_piece_id')->nullable();
            $table->string('cta_type', 30);
            $table->date('event_date');
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->foreign('content_piece_id')->references('id')->on('sj_content_pieces')->nullOnDelete();
            $table->unique(['entity_id', 'content_piece_id', 'cta_type', 'event_date'], 'sj_cta_event_unique');
            $table->index(['entity_id', 'event_date']);
            $table->index(['content_piece_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_cta_events');
    }
};
