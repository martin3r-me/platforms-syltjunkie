<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_facebook_posts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('facebook_page_id')
                ->constrained('integrations_facebook_pages')
                ->cascadeOnDelete();
            $table->string('external_id');
            $table->text('message')->nullable();
            $table->text('media_url')->nullable();
            $table->text('permalink_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->integer('share_count')->default(0);
            $table->timestamps();

            $table->index('external_id');
            $table->index('published_at');
            $table->unique(['facebook_page_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_facebook_posts');
    }
};
