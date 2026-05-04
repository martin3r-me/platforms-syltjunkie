<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_entity_scores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->date('captured_at');

            // Visibility
            $table->unsignedSmallInteger('visibility_score')->default(0)->comment('0–100 gewichtet über alle Plattformen');
            $table->decimal('visibility_trend', 5, 2)->nullable()->comment('% Veränderung zum Vormonat');

            // Commercial
            $table->unsignedInteger('organic_value_cents')->default(0)->comment('SEA-Äquivalent über alle URLs');
            $table->unsignedInteger('estimated_monthly_traffic')->default(0);

            // Keyword Stats
            $table->unsignedSmallInteger('direct_keywords_count')->default(0);
            $table->unsignedSmallInteger('brand_keywords_count')->default(0);
            $table->unsignedSmallInteger('top10_keywords_count')->default(0);
            $table->unsignedSmallInteger('total_keywords_count')->default(0);

            // Platform Scores
            $table->unsignedSmallInteger('score_google_organic')->nullable()->comment('0–100');
            $table->unsignedSmallInteger('score_google_maps')->nullable();
            $table->unsignedSmallInteger('score_tripadvisor')->nullable();
            $table->unsignedSmallInteger('score_instagram')->nullable();
            $table->unsignedSmallInteger('platforms_active')->default(0);
            $table->decimal('avg_review_rating', 2, 1)->nullable();
            $table->unsignedInteger('total_review_count')->nullable();

            // Top Opportunity
            $table->string('top_opportunity', 50)->nullable()->comment('z.B. tripadvisor_reviews, google_content');
            $table->unsignedInteger('top_opportunity_value_cents')->nullable();

            $table->timestamps();

            $table->unique(['entity_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_entity_scores');
    }
};
