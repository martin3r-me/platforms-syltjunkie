<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_relation_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('code', 50)->index();
            $table->string('name');
            $table->string('inverse_name')->nullable()->comment('e.g. lokalisiert_in → beherbergt');
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_directional')->default(true);
            $table->boolean('is_hierarchical')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_relation_types');
    }
};
