<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('entity_type_id')->constrained('sj_entity_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ort', 100)->nullable()->index()->comment('Westerland, Kampen, List, etc.');
            $table->enum('season', ['year_round', 'sommer', 'winter', 'event'])->default('year_round');
            $table->enum('status', ['aktiv', 'saisonal_geschlossen', 'dauerhaft_geschlossen'])->default('aktiv');
            $table->enum('source', ['manuell', 'crowdsourcing', 'import_google', 'import_instagram', 'self_service'])->default('manuell');
            $table->json('extra_fields')->nullable()->comment('Type-specific fields matching entity_type.extra_field_schema');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_entities');
    }
};
