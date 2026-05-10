<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id');
            $table->string('blockable_type');
            $table->unsignedBigInteger('blockable_id');
            $table->string('block_type');
            $table->json('content');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['blockable_type', 'blockable_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_content_blocks');
    }
};
