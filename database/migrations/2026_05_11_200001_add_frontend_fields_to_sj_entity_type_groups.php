<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_entity_type_groups', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('code');
            $table->string('nav_label')->nullable()->after('name');
            $table->string('singular')->nullable()->after('nav_label');
            $table->string('color', 20)->nullable()->after('icon');
            $table->string('template')->default('default')->after('color');
            $table->boolean('show_on_map')->default(true)->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('sj_entity_type_groups', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'nav_label', 'singular', 'color', 'template', 'show_on_map']);
        });
    }
};
