<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_keyword_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('sj_keywords')->cascadeOnDelete();
            $table->foreignId('competitor_entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->unsignedSmallInteger('competitor_position');
            $table->unsignedInteger('opportunity_value_cents')->default(0)->comment('Entgangener Traffic-Wert in Cent');
            $table->date('captured_at');
            $table->timestamps();

            $table->unique(['entity_id', 'keyword_id', 'competitor_entity_id', 'captured_at'], 'sj_kw_gap_unique');
            $table->index(['entity_id', 'captured_at']);
            $table->index(['competitor_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_keyword_gaps');
    }
};
