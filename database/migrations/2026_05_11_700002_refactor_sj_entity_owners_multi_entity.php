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

        // Unique-Index droppen (falls noch vorhanden)
        $this->dropIndexIfExists('sj_entity_owners', 'sj_entity_owners_team_id_email_unique');

        // FK droppen (falls noch vorhanden)
        $this->dropForeignIfExists('sj_entity_owners', 'sj_entity_owners_entity_id_foreign');

        Schema::table('sj_entity_owners', function (Blueprint $table) {
            // entity_id NOT NULL setzen
            $table->unsignedBigInteger('entity_id')->nullable(false)->change();

            // FK neu mit CASCADE statt SET NULL
            $table->foreign('entity_id')->references('id')->on('sj_entities')->cascadeOnDelete();

            // Neuer Unique-Index (falls noch nicht da)
            $table->unique(['team_id', 'email', 'entity_id']);
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('sj_entity_owners', 'sj_entity_owners_team_id_email_entity_id_unique');
        $this->dropForeignIfExists('sj_entity_owners', 'sj_entity_owners_entity_id_foreign');

        Schema::table('sj_entity_owners', function (Blueprint $table) {
            $table->unsignedBigInteger('entity_id')->nullable()->change();
            $table->foreign('entity_id')->references('id')->on('sj_entities')->nullOnDelete();
            $table->unique(['team_id', 'email']);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = DB::selectOne(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        if ($exists) {
            Schema::table($table, fn (Blueprint $t) => $t->dropUnique($indexName));
        }
    }

    private function dropForeignIfExists(string $table, string $foreignName): void
    {
        $exists = DB::selectOne(
            "SELECT * FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $foreignName]
        );

        if ($exists) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($foreignName));
        }
    }
};
