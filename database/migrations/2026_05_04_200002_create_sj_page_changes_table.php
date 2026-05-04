<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_page_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id');
            $table->foreignId('entity_url_id')->constrained('sj_entity_urls')->cascadeOnDelete();
            $table->date('detected_at');
            $table->string('change_type');
            $table->enum('severity', ['minor', 'moderate', 'major']);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->integer('delta')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['entity_url_id', 'detected_at']);
            $table->index(['team_id', 'detected_at', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_page_changes');
    }
};
