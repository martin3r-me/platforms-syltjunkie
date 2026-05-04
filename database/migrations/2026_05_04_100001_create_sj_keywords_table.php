<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_keywords', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('keyword');
            $table->unsignedInteger('search_volume')->nullable();
            $table->unsignedInteger('cpc_cents')->nullable()->comment('CPC in Cent — Geld als Integer');
            $table->decimal('competition', 4, 3)->nullable()->comment('0.000–1.000');
            $table->unsignedTinyInteger('keyword_difficulty')->nullable()->comment('0–100');
            $table->string('search_intent', 30)->nullable()->comment('informational, navigational, transactional, commercial');
            $table->string('topic', 100)->nullable()->comment('Themen-Cluster');
            $table->json('monthly_volumes')->nullable()->comment('12 Monatswerte [Jan..Dez]');
            $table->unsignedTinyInteger('peak_month')->nullable()->comment('1–12');
            $table->decimal('seasonality_index', 3, 2)->nullable()->comment('0=stabil, 1=extrem saisonal');
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_keywords');
    }
};
