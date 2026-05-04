<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_content_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_piece_id')->constrained('sj_content_pieces')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('sj_keywords')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false)->comment('Haupt-Keyword dieses Contents');
            $table->unsignedSmallInteger('current_position')->nullable()->comment('Wo rankt unser Content dafür');
            $table->timestamps();

            $table->unique(['content_piece_id', 'keyword_id'], 'sj_content_kw_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_content_keywords');
    }
};
