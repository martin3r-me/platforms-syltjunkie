<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_instagram_media', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('instagram_account_id')
                ->constrained('integrations_instagram_accounts')
                ->cascadeOnDelete();
            $table->string('external_id');
            $table->text('caption')->nullable();
            $table->string('media_type', 50); // IMAGE, VIDEO, CAROUSEL_ALBUM, STORY, REEL
            $table->text('media_url')->nullable();
            $table->text('permalink')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->timestamp('timestamp')->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->boolean('is_story')->default(false);
            $table->boolean('insights_available')->default(true);
            $table->timestamps();

            $table->index('external_id');
            $table->index('timestamp');
            $table->index('is_story');
            $table->unique(['instagram_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_instagram_media');
    }
};
