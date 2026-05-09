<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_channel_post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_post_id')->constrained('sj_channel_posts')->cascadeOnDelete();
            $table->foreignId('sj_image_id')->constrained('sj_images')->cascadeOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->string('role', 30)->default('media');
            $table->timestamps();

            $table->unique(['channel_post_id', 'sj_image_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_channel_post_images');
    }
};
