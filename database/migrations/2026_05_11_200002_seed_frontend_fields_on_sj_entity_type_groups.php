<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groups = [
            'place' => [
                'prefix' => 'orte',
                'nav_label' => 'Orte',
                'singular' => 'Ort',
                'color' => '#2563EB',
                'template' => 'place',
                'show_on_map' => true,
            ],
            'business' => [
                'prefix' => 'unternehmen',
                'nav_label' => 'Geschäfte',
                'singular' => 'Geschäft',
                'color' => '#D97706',
                'template' => 'business',
                'show_on_map' => true,
            ],
            'infrastructure' => [
                'prefix' => 'infrastruktur',
                'nav_label' => 'Infrastruktur',
                'singular' => 'Einrichtung',
                'color' => '#6B7280',
                'template' => 'place',
                'show_on_map' => true,
            ],
            'event' => [
                'prefix' => 'events',
                'nav_label' => 'Events',
                'singular' => 'Event',
                'color' => '#7C3AED',
                'template' => 'event',
                'show_on_map' => true,
            ],
            'media' => [
                'prefix' => 'medien',
                'nav_label' => 'Medien',
                'singular' => 'Medienkanal',
                'color' => '#EC4899',
                'template' => 'default',
                'show_on_map' => false,
            ],
            'platform' => [
                'prefix' => 'plattformen',
                'nav_label' => 'Plattformen',
                'singular' => 'Plattform',
                'color' => '#0EA5E9',
                'template' => 'default',
                'show_on_map' => false,
            ],
            'person' => [
                'prefix' => 'personen',
                'nav_label' => 'Personen',
                'singular' => 'Person',
                'color' => '#F59E0B',
                'template' => 'person',
                'show_on_map' => false,
            ],
            'organization' => [
                'prefix' => 'organisationen',
                'nav_label' => 'Organisationen',
                'singular' => 'Organisation',
                'color' => '#10B981',
                'template' => 'default',
                'show_on_map' => true,
            ],
            'nature' => [
                'prefix' => 'natur',
                'nav_label' => 'Natur',
                'singular' => 'Naturgebiet',
                'color' => '#059669',
                'template' => 'place',
                'show_on_map' => true,
            ],
        ];

        foreach ($groups as $code => $fields) {
            DB::table('sj_entity_type_groups')
                ->where('code', $code)
                ->update($fields);
        }
    }

    public function down(): void
    {
        DB::table('sj_entity_type_groups')
            ->update([
                'prefix' => null,
                'nav_label' => null,
                'singular' => null,
                'color' => null,
                'template' => 'default',
                'show_on_map' => true,
            ]);
    }
};
