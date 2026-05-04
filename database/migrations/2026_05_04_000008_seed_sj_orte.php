<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Uid\UuidV7;

return new class extends Migration
{
    public function up(): void
    {
        $teamId = config('syltjunkie.seed_team_id', 9);

        $ortTypeId = DB::table('sj_entity_types')
            ->where('team_id', $teamId)
            ->where('code', 'ort')
            ->value('id');

        if (!$ortTypeId) {
            return;
        }

        $orte = [
            [
                'name' => 'Westerland',
                'description' => 'Hauptort und urbanes Zentrum der Insel Sylt. Größter Ort mit Bahnhof, Einkaufsmeile Friedrichstraße, Strandpromenade und Kurhaus.',
                'latitude' => 54.9079,
                'longitude' => 8.3047,
            ],
            [
                'name' => 'Kampen',
                'description' => 'Prominenten-Hotspot und Luxus-Ort im Norden. Rotes Kliff, Whiskymeile, Strönwai — das \"St. Tropez des Nordens\".',
                'latitude' => 54.9553,
                'longitude' => 8.3436,
            ],
            [
                'name' => 'List',
                'description' => 'Nördlichster Ort Deutschlands. Fährhafen nach Røm/Dänemark, Wanderdünen, Erlebniszentrum Naturgewalten.',
                'latitude' => 55.0153,
                'longitude' => 8.4344,
            ],
            [
                'name' => 'Wenningstedt',
                'description' => 'Familienfreundlicher Badeort zwischen Westerland und Kampen. Denghoog-Hünengrab, breiter Sandstrand.',
                'latitude' => 54.9367,
                'longitude' => 8.3252,
            ],
            [
                'name' => 'Keitum',
                'description' => 'Das \"grüne Herz\" von Sylt. Historischer Kapitänsort am Wattenmeer mit Friesenarchitektur, Altfriesischem Haus und St.-Severin-Kirche.',
                'latitude' => 54.8975,
                'longitude' => 8.3700,
            ],
            [
                'name' => 'Rantum',
                'description' => 'Schmaler Ort an der engsten Stelle der Insel. Rantumbecken (Vogelschutz), Sansibar und Strandleben.',
                'latitude' => 54.8618,
                'longitude' => 8.3018,
            ],
            [
                'name' => 'Hörnum',
                'description' => 'Südspitze der Insel. Leuchtturm, Robben-Fahrten, Hafen. Ruhige Alternative zum Norden.',
                'latitude' => 54.7589,
                'longitude' => 8.2972,
            ],
            [
                'name' => 'Morsum',
                'description' => 'Östlichster Ort mit Morsum-Kliff (Naturdenkmal, 10 Mio. Jahre Erdgeschichte). Erste Anlaufstelle über den Hindenburgdamm.',
                'latitude' => 54.8850,
                'longitude' => 8.4500,
            ],
            [
                'name' => 'Tinnum',
                'description' => 'Zentrale Wohnlage zwischen Westerland und Keitum. Tinnumburg (Ringwall), Tierpark Sylt.',
                'latitude' => 54.8972,
                'longitude' => 8.3389,
            ],
            [
                'name' => 'Archsum',
                'description' => 'Kleinstes Dorf auf Sylt mit nur wenigen hundert Einwohnern. Reetdachidylle und Ruhe im Inselinneren.',
                'latitude' => 54.8800,
                'longitude' => 8.3900,
            ],
            [
                'name' => 'Munkmarsch',
                'description' => 'Ehemaliger Fährhafen am Wattenmeer. Heute exklusive Wohnlage zwischen Keitum und Westerland.',
                'latitude' => 54.9025,
                'longitude' => 8.3500,
            ],
            [
                'name' => 'Braderup',
                'description' => 'Ruhiges Heidedorf östlich von Wenningstedt. Braderuper Heide mit Panoramablick aufs Wattenmeer.',
                'latitude' => 54.9380,
                'longitude' => 8.3500,
            ],
        ];

        $now = now();

        foreach ($orte as $ort) {
            DB::table('sj_entities')->insert([
                'uuid' => (string) new UuidV7(),
                'team_id' => $teamId,
                'entity_type_id' => $ortTypeId,
                'name' => $ort['name'],
                'slug' => Str::slug($ort['name']),
                'description' => $ort['description'],
                'ort' => $ort['name'],
                'latitude' => $ort['latitude'],
                'longitude' => $ort['longitude'],
                'season' => 'year_round',
                'status' => 'aktiv',
                'source' => 'manuell',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $teamId = config('syltjunkie.seed_team_id', 9);
        DB::table('sj_entities')
            ->where('team_id', $teamId)
            ->where('source', 'manuell')
            ->whereIn('slug', [
                'westerland', 'kampen', 'list', 'wenningstedt', 'keitum',
                'rantum', 'hoernum', 'morsum', 'tinnum', 'archsum',
                'munkmarsch', 'braderup',
            ])
            ->delete();
    }
};
