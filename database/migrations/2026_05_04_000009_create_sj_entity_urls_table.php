<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_entity_urls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('entity_id')->constrained('sj_entities')->cascadeOnDelete();
            $table->string('url');
            $table->enum('platform', ['website', 'google_maps', 'tripadvisor', 'instagram', 'facebook', 'booking', 'yelp', 'other'])->default('website');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'entity_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_entity_urls');
    }
};
