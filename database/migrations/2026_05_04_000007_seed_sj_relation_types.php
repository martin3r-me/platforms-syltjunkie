<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\UuidV7;

return new class extends Migration
{
    public function up(): void
    {
        $teamId = config('syltjunkie.seed_team_id', 9);
        $now = now();

        $types = [
            [
                'code' => 'lokalisiert_in',
                'name' => 'Lokalisiert in',
                'inverse_name' => 'Beherbergt',
                'description' => 'Entity befindet sich in einem Ort/Gebiet (Restaurant → Westerland)',
                'is_directional' => true,
                'is_hierarchical' => true,
            ],
            [
                'code' => 'gehoert_zu',
                'name' => 'Gehört zu',
                'inverse_name' => 'Besitzt',
                'description' => 'Entity gehört zu einer übergeordneten Entity (Spa → Hotel)',
                'is_directional' => true,
                'is_hierarchical' => true,
            ],
            [
                'code' => 'gelistet_auf',
                'name' => 'Gelistet auf',
                'inverse_name' => 'Listet',
                'description' => 'Entity ist auf einer externen Plattform gelistet (Restaurant → TripAdvisor)',
                'is_directional' => true,
                'is_hierarchical' => false,
            ],
            [
                'code' => 'bewertet_auf',
                'name' => 'Bewertet auf',
                'inverse_name' => 'Bewertet',
                'description' => 'Entity hat Bewertungen auf einer Plattform (Hotel → Booking.com)',
                'is_directional' => true,
                'is_hierarchical' => false,
            ],
            [
                'code' => 'betrieben_von',
                'name' => 'Betrieben von',
                'inverse_name' => 'Betreibt',
                'description' => 'Entity wird von einer Person/Organisation betrieben (Restaurant → Gastgeber)',
                'is_directional' => true,
                'is_hierarchical' => false,
            ],
            [
                'code' => 'verwaltet_von',
                'name' => 'Verwaltet von',
                'inverse_name' => 'Verwaltet',
                'description' => 'Entity wird von einer Agentur verwaltet (FeWo → Vermietungsagentur)',
                'is_directional' => true,
                'is_hierarchical' => false,
            ],
            [
                'code' => 'kooperiert_mit',
                'name' => 'Kooperiert mit',
                'inverse_name' => null,
                'description' => 'Zusammenarbeit zwischen Entities (Restaurant ↔ Influencer)',
                'is_directional' => false,
                'is_hierarchical' => false,
            ],
            [
                'code' => 'empfiehlt',
                'name' => 'Empfiehlt',
                'inverse_name' => 'Empfohlen von',
                'description' => 'Entity empfiehlt eine andere Entity (Blog → Restaurant)',
                'is_directional' => true,
                'is_hierarchical' => false,
            ],
            [
                'code' => 'liegt_in_naehe_von',
                'name' => 'Liegt in Nähe von',
                'inverse_name' => null,
                'description' => 'Räumliche Nähe zwischen Entities (Hotel ↔ Strand)',
                'is_directional' => false,
                'is_hierarchical' => false,
            ],
            [
                'code' => 'teil_von',
                'name' => 'Teil von',
                'inverse_name' => 'Umfasst',
                'description' => 'Entity ist Teil einer größeren Entity (Strand → Naturschutzgebiet)',
                'is_directional' => true,
                'is_hierarchical' => true,
            ],
            [
                'code' => 'findet_statt_in',
                'name' => 'Findet statt in',
                'inverse_name' => 'Veranstaltungsort für',
                'description' => 'Event findet an einem Ort/in einer Entity statt (Festival → Strand)',
                'is_directional' => true,
                'is_hierarchical' => false,
            ],
            [
                'code' => 'mitglied_von',
                'name' => 'Mitglied von',
                'inverse_name' => 'Hat Mitglied',
                'description' => 'Entity ist Mitglied einer Organisation (Restaurant → Tourismusverband)',
                'is_directional' => true,
                'is_hierarchical' => false,
            ],
        ];

        foreach ($types as $sortOrder => $type) {
            DB::table('sj_relation_types')->insert(array_merge($type, [
                'uuid' => UuidV7::generate(),
                'team_id' => $teamId,
                'sort_order' => $sortOrder + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        $teamId = config('syltjunkie.seed_team_id', 9);
        DB::table('sj_relation_types')->where('team_id', $teamId)->delete();
    }
};
