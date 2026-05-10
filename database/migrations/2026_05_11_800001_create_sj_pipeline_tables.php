<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_pipeline_slots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('name', 255);
            $table->string('color', 20)->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('sj_pipeline_cards', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('slot_id')->nullable();
            $table->string('name', 255);
            $table->string('url', 2048)->nullable();
            $table->unsignedBigInteger('entity_type_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->integer('order')->default(0);
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('converted_entity_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('slot_id')->references('id')->on('sj_pipeline_slots')->onDelete('set null');
            $table->foreign('entity_type_id')->references('id')->on('sj_entity_types')->onDelete('set null');
            $table->foreign('converted_entity_id')->references('id')->on('sj_entities')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_pipeline_cards');
        Schema::dropIfExists('sj_pipeline_slots');
    }
};
