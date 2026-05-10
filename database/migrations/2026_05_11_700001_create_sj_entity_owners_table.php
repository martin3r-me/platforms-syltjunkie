<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_entity_owners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('email');
            $table->string('name')->nullable();
            $table->enum('status', ['pending', 'approved', 'blocked'])->default('pending');
            $table->string('token', 100)->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'email']);
            $table->foreign('entity_id')->references('id')->on('sj_entities')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_entity_owners');
    }
};
