<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_channel_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('channel_id')->constrained('sj_channels')->cascadeOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained('sj_entities')->nullOnDelete();
            $table->foreignId('content_piece_id')->nullable()->constrained('sj_content_pieces')->nullOnDelete();
            $table->string('post_type', 50)->default('image');
            $table->string('status', 30)->default('draft');
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_post_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['channel_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_channel_posts');
    }
};
