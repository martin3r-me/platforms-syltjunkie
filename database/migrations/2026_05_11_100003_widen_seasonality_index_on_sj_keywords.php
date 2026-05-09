<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_keywords', function (Blueprint $table) {
            $table->decimal('seasonality_index', 6, 2)->nullable()->comment('max/avg ratio, 0=stabil, höher=saisonaler')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sj_keywords', function (Blueprint $table) {
            $table->decimal('seasonality_index', 3, 2)->nullable()->comment('0=stabil, 1=extrem saisonal')->change();
        });
    }
};
