<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_keywords', function (Blueprint $table) {
            $table->json('google_trends_data')->nullable()->after('seasonality_index');
            $table->unsignedTinyInteger('trends_average_interest')->nullable()->after('google_trends_data');
            $table->unsignedTinyInteger('trends_peak_interest')->nullable()->after('trends_average_interest');
            $table->timestamp('trends_fetched_at')->nullable()->after('trends_peak_interest');
        });
    }

    public function down(): void
    {
        Schema::table('sj_keywords', function (Blueprint $table) {
            $table->dropColumn([
                'google_trends_data',
                'trends_average_interest',
                'trends_peak_interest',
                'trends_fetched_at',
            ]);
        });
    }
};
