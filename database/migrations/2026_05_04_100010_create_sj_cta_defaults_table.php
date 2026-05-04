<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_cta_defaults', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('entity_type_id')->constrained('sj_entity_types')->cascadeOnDelete();
            $table->string('cta_type', 30)->comment('reserve_table, book_room, call, directions, visit_website, buy_ticket');
            $table->string('cta_label', 60)->comment('z.B. "Tisch reservieren"');
            $table->string('cta_icon', 50)->nullable()->comment('Heroicon-Name');
            $table->unsignedSmallInteger('priority')->default(0)->comment('Reihenfolge, niedrig = wichtiger');
            $table->timestamps();

            $table->unique(['team_id', 'entity_type_id', 'cta_type'], 'sj_cta_default_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_cta_defaults');
    }
};
