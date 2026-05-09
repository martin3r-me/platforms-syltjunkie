<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_instagram_account_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_account_id')
                ->constrained('integrations_instagram_accounts')
                ->cascadeOnDelete();
            $table->date('insight_date');

            // Account-Details (Snapshot)
            $table->string('current_name')->nullable();
            $table->string('current_username')->nullable();
            $table->text('current_biography')->nullable();
            $table->text('current_profile_picture_url')->nullable();
            $table->text('current_website')->nullable();
            $table->integer('current_followers')->nullable();
            $table->integer('current_follows')->nullable();

            // Tägliche Metriken
            $table->integer('follower_count')->nullable();
            $table->integer('impressions')->nullable();
            $table->integer('reach')->nullable();

            // Total-Value-Metriken
            $table->integer('accounts_engaged')->nullable();
            $table->integer('total_interactions')->nullable();
            $table->integer('likes')->nullable();
            $table->integer('comments')->nullable();
            $table->integer('shares')->nullable();
            $table->integer('saves')->nullable();
            $table->integer('replies')->nullable();

            // Weitere Metriken
            $table->integer('profile_views')->nullable();
            $table->integer('website_clicks')->nullable();
            $table->integer('email_contacts')->nullable();
            $table->integer('phone_call_clicks')->nullable();
            $table->integer('get_directions_clicks')->nullable();

            $table->timestamps();

            $table->unique(['instagram_account_id', 'insight_date'], 'sj_account_insights_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_instagram_account_insights');
    }
};
