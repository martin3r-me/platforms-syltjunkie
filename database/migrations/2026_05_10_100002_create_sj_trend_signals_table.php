<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_trend_signals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('entity_id')->nullable()->constrained('sj_entities')->nullOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('sj_keywords')->nullOnDelete();
            $table->foreignId('entity_url_id')->nullable()->constrained('sj_entity_urls')->nullOnDelete();
            $table->string('signal_type', 50)->index();
            $table->enum('severity', ['info', 'watch', 'action'])->default('info');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('metric_before', 12, 4)->nullable();
            $table->decimal('metric_after', 12, 4)->nullable();
            $table->decimal('metric_delta', 12, 4)->nullable();
            $table->date('detected_at');
            $table->enum('status', ['new', 'acknowledged', 'resolved'])->default('new');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_trend_signals');
    }
};
