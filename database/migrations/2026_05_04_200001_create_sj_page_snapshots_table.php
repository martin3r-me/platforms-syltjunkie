<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_page_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('entity_url_id')->constrained('sj_entity_urls')->cascadeOnDelete();
            $table->date('captured_at');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('headings')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedInteger('content_length')->nullable();
            $table->unsignedInteger('internal_links_count')->nullable();
            $table->unsignedInteger('external_links_count')->nullable();
            $table->unsignedInteger('image_count')->nullable();
            $table->decimal('load_time', 5, 2)->nullable();
            $table->decimal('onpage_score', 5, 2)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['entity_url_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_page_snapshots');
    }
};
