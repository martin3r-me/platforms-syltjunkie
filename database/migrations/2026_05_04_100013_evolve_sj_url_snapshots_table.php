<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_url_snapshots', function (Blueprint $table) {
            // Neue aggregierte Felder
            $table->unsignedSmallInteger('keywords_count')->nullable()->after('keywords')->comment('Anzahl rankender Keywords');
            $table->unsignedInteger('organic_value_cents')->nullable()->after('organic_traffic_estimate')->comment('SEA-Äquivalent in Cent');

            // Platform-Signale (Maps, TripAdvisor, Yelp, Booking)
            $table->unsignedInteger('review_count')->nullable()->after('backlinks_count');
            $table->decimal('average_rating', 2, 1)->nullable()->after('review_count')->comment('1.0–5.0');
            $table->unsignedSmallInteger('platform_rank')->nullable()->after('average_rating')->comment('TA-Ranking, Maps-Position');
        });
    }

    public function down(): void
    {
        Schema::table('sj_url_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'keywords_count',
                'organic_value_cents',
                'review_count',
                'average_rating',
                'platform_rank',
            ]);
        });
    }
};
