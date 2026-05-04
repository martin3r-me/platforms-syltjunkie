<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_content_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_piece_id')->constrained('sj_content_pieces')->cascadeOnDelete();
            $table->date('captured_at');
            $table->unsignedInteger('pageviews')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedSmallInteger('avg_time_seconds')->nullable();
            $table->decimal('bounce_rate', 4, 1)->nullable()->comment('0.0–100.0');
            $table->unsignedInteger('cta_clicks_total')->default(0);
            $table->timestamps();

            $table->unique(['content_piece_id', 'captured_at'], 'sj_content_perf_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_content_performance');
    }
};
