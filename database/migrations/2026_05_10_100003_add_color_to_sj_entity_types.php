<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sj_entity_types', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('icon');
        });

        // Set default colors based on group code
        $colorMap = [
            'place'          => '#0D9488',
            'business'       => '#EA580C',
            'infrastructure' => '#64748B',
            'event'          => '#DB2777',
            'media'          => '#4F46E5',
            'platform'       => '#059669',
            'person'         => '#7C3AED',
            'organization'   => '#2563EB',
            'nature'         => '#16A34A',
        ];

        foreach ($colorMap as $groupCode => $color) {
            DB::table('sj_entity_types')
                ->whereIn('group_id', function ($q) use ($groupCode) {
                    $q->select('id')
                        ->from('sj_entity_type_groups')
                        ->where('code', $groupCode);
                })
                ->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        Schema::table('sj_entity_types', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
