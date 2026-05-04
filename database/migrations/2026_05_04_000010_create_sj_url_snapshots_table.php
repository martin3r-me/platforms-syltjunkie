<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_url_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('entity_url_id')->constrained('sj_entity_urls')->cascadeOnDelete();
            $table->date('captured_at');
            $table->json('keywords')->nullable();
            $table->unsignedInteger('organic_traffic_estimate')->nullable();
            $table->unsignedSmallInteger('domain_authority')->nullable();
            $table->unsignedInteger('backlinks_count')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['entity_url_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_url_snapshots');
    }
};
