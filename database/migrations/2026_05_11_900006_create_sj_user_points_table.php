<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_user_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('sj_user_id')->constrained('sj_users')->cascadeOnDelete();
            $table->string('action', 50);
            $table->integer('points');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sj_user_id', 'action']);
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_user_points');
    }
};
