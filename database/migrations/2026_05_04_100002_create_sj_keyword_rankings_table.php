<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_keyword_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('sj_keywords')->cascadeOnDelete();
            $table->foreignId('entity_url_id')->constrained('sj_entity_urls')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->string('ranked_url', 500)->comment('Tatsächliche URL die rankt');
            $table->date('captured_at');
            $table->string('search_engine', 20)->default('google');
            $table->string('device', 20)->default('desktop');
            $table->json('serp_features')->nullable()->comment('Featured Snippet, Local Pack, etc.');
            $table->timestamps();

            $table->unique(['keyword_id', 'entity_url_id', 'captured_at'], 'sj_kw_rank_unique');
            $table->index(['entity_url_id', 'captured_at']);
            $table->index(['captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_keyword_rankings');
    }
};
