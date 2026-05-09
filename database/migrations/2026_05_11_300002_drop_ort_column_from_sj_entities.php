<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_entities', function (Blueprint $table) {
            $table->dropIndex(['ort']);
            $table->dropColumn('ort');
        });
    }

    public function down(): void
    {
        Schema::table('sj_entities', function (Blueprint $table) {
            $table->string('ort')->nullable()->after('longitude');
            $table->index('ort');
        });
    }
};
