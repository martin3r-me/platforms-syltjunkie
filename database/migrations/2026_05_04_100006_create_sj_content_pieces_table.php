<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_content_pieces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('title');
            $table->string('slug');
            $table->string('content_type', 30)->comment('entity_page, listing_page, guide, seasonal_guide, event');
            $table->string('status', 20)->default('brief')->comment('brief, draft, review, published, archived');
            $table->text('brief_notes')->nullable()->comment('Redaktionelle Notizen / AI-Brief');
            $table->string('published_url', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('target_traffic_estimate')->nullable()->comment('Erwarteter monatlicher Traffic');
            $table->unsignedInteger('target_value_cents')->nullable()->comment('Erwarteter Wert basierend auf Keywords');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_content_pieces');
    }
};
