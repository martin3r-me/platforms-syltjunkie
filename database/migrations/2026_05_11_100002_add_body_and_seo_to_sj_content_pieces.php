<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_content_pieces', function (Blueprint $table) {
            $table->longText('body_markdown')->nullable()->after('brief_notes');
            $table->text('excerpt')->nullable()->after('body_markdown');
            $table->foreignId('cover_image_id')->nullable()->after('excerpt')
                ->constrained('sj_images')->nullOnDelete();
            $table->string('seo_title')->nullable()->after('cover_image_id');
            $table->string('seo_description')->nullable()->after('seo_title');
        });

        Schema::create('sj_content_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_piece_id')->constrained('sj_content_pieces')->cascadeOnDelete();
            $table->foreignId('image_id')->constrained('sj_images')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('role', 30)->default('gallery'); // hero, inline, gallery
            $table->timestamps();

            $table->unique(['content_piece_id', 'image_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_content_images');

        Schema::table('sj_content_pieces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_image_id');
            $table->dropColumn(['body_markdown', 'excerpt', 'seo_title', 'seo_description']);
        });
    }
};
