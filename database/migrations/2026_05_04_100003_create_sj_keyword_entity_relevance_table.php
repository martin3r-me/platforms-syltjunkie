<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_keyword_entity_relevance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('sj_keywords')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->string('attribution_type', 20)->comment('direct, brand, local, generic');
            $table->decimal('confidence', 3, 2)->default(0.00)->comment('0.00–1.00');
            $table->string('source', 20)->default('auto_ranking')->comment('auto_ranking, auto_serp, auto_brand, manual');
            $table->timestamps();

            $table->unique(['keyword_id', 'entity_id'], 'sj_kw_entity_rel_unique');
            $table->index(['entity_id', 'attribution_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_keyword_entity_relevance');
    }
};
