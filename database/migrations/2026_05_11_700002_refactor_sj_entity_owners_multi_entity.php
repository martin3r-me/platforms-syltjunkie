<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Einträge ohne entity_id entfernen (machen keinen Sinn mehr)
        DB::table('sj_entity_owners')->whereNull('entity_id')->delete();

        Schema::table('sj_entity_owners', function (Blueprint $table) {
            // Alten Unique-Index entfernen
            $table->dropUnique(['team_id', 'email']);

            // Alten FK entfernen (hat SET NULL, verhindert NOT NULL)
            $table->dropForeign(['entity_id']);
        });

        Schema::table('sj_entity_owners', function (Blueprint $table) {
            // entity_id NOT NULL setzen
            $table->unsignedBigInteger('entity_id')->nullable(false)->change();

            // FK neu mit CASCADE statt SET NULL
            $table->foreign('entity_id')->references('id')->on('sj_entities')->cascadeOnDelete();

            // Neuer Unique-Index: eine E-Mail kann mehrere Entities haben
            $table->unique(['team_id', 'email', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sj_entity_owners', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'email', 'entity_id']);
            $table->dropForeign(['entity_id']);
        });

        Schema::table('sj_entity_owners', function (Blueprint $table) {
            $table->unsignedBigInteger('entity_id')->nullable()->change();
            $table->foreign('entity_id')->references('id')->on('sj_entities')->nullOnDelete();
            $table->unique(['team_id', 'email']);
        });
    }
};
