<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_entity_owners', function (Blueprint $table) {
            $table->string('redirect_url', 500)->nullable()->after('from_address');
        });
    }

    public function down(): void
    {
        Schema::table('sj_entity_owners', function (Blueprint $table) {
            $table->dropColumn('redirect_url');
        });
    }
};
