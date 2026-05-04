<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\UuidV7;

return new class extends Migration
{
    public function up(): void
    {
        // We need a team_id. Seeded data uses team_id from config or defaults to 9 (BHG.DIGITAL).
        $teamId = config('syltjunkie.seed_team_id', 9);

        $groups = [
            [
                'code' => 'place',
                'name' => 'Orte & Geografie',
                'description' => 'Ortschaften, Strände, Dünen, Aussichtspunkte und geografische Merkmale der Insel Sylt',
                'icon' => 'map-pin',
                'sort_order' => 1,
                'types' => [
                    ['code' => 'ort', 'name' => 'Ort', 'description' => 'Ortschaft auf Sylt (Westerland, Kampen, List, ...)', 'icon' => 'map'],
                    ['code' => 'strand', 'name' => 'Strand', 'description' => 'Strandabschnitt', 'icon' => 'sun'],
                    ['code' => 'duene', 'name' => 'Düne', 'description' => 'Dünenlandschaft oder Dünengebiet', 'icon' => 'cloud'],
                    ['code' => 'aussichtspunkt', 'name' => 'Aussichtspunkt', 'description' => 'Aussichtspunkt oder Panoramaplatz', 'icon' => 'eye'],
                ],
            ],
            [
                'code' => 'business',
                'name' => 'Geschäfte & Gastronomie',
                'description' => 'Restaurants, Hotels, Shops und alle gewerblichen Anbieter auf Sylt — das Kerncluster der Plattform',
                'icon' => 'building-storefront',
                'sort_order' => 2,
                'types' => [
                    ['code' => 'restaurant', 'name' => 'Restaurant', 'icon' => 'fire', 'extra_field_schema' => [
                        'cuisine' => ['type' => 'multi_select', 'label' => 'Küche', 'options' => ['deutsch', 'italienisch', 'asiatisch', 'französisch', 'seafood', 'international', 'regional', 'vegan', 'fusion']],
                        'price_class' => ['type' => 'select', 'label' => 'Preisklasse', 'options' => ['€', '€€', '€€€', '€€€€']],
                        'michelin_stars' => ['type' => 'integer', 'label' => 'Michelin-Sterne', 'min' => 0, 'max' => 3],
                        'capacity' => ['type' => 'integer', 'label' => 'Sitzplätze'],
                        'reservation_required' => ['type' => 'boolean', 'label' => 'Reservierung erforderlich'],
                        'opening_hours' => ['type' => 'text', 'label' => 'Öffnungszeiten'],
                    ]],
                    ['code' => 'cafe', 'name' => 'Café', 'icon' => 'fire', 'extra_field_schema' => [
                        'specialty' => ['type' => 'multi_select', 'label' => 'Spezialität', 'options' => ['kaffee', 'kuchen', 'frühstück', 'brunch', 'eis']],
                        'capacity' => ['type' => 'integer', 'label' => 'Sitzplätze'],
                        'opening_hours' => ['type' => 'text', 'label' => 'Öffnungszeiten'],
                    ]],
                    ['code' => 'bar', 'name' => 'Bar', 'icon' => 'fire'],
                    ['code' => 'baeckerei', 'name' => 'Bäckerei', 'icon' => 'fire'],
                    ['code' => 'eisdiele', 'name' => 'Eisdiele', 'icon' => 'fire'],
                    ['code' => 'hotel', 'name' => 'Hotel', 'icon' => 'building-office', 'extra_field_schema' => [
                        'stars' => ['type' => 'integer', 'label' => 'Sterne', 'min' => 1, 'max' => 5],
                        'rooms_count' => ['type' => 'integer', 'label' => 'Zimmeranzahl'],
                        'category' => ['type' => 'select', 'label' => 'Kategorie', 'options' => ['boutique', 'resort', 'pension', 'aparthotel', 'strandhotel']],
                        'has_spa' => ['type' => 'boolean', 'label' => 'Spa vorhanden'],
                        'beach_distance_m' => ['type' => 'integer', 'label' => 'Strandentfernung (m)'],
                        'breakfast_included' => ['type' => 'boolean', 'label' => 'Frühstück inklusive'],
                    ]],
                    ['code' => 'pension', 'name' => 'Pension', 'icon' => 'home'],
                    ['code' => 'fewo', 'name' => 'Ferienwohnung', 'icon' => 'home', 'extra_field_schema' => [
                        'sleeps' => ['type' => 'integer', 'label' => 'Schlafplätze'],
                        'location_type' => ['type' => 'select', 'label' => 'Lagetyp', 'options' => ['strandnah', 'ortskern', 'ländlich', 'dünenrand']],
                        'rental_platform_primary' => ['type' => 'string', 'label' => 'Primäre Buchungsplattform'],
                    ]],
                    ['code' => 'boutique', 'name' => 'Boutique', 'icon' => 'shopping-bag'],
                    ['code' => 'concept_store', 'name' => 'Concept Store', 'icon' => 'shopping-bag'],
                    ['code' => 'manufaktur', 'name' => 'Manufaktur', 'icon' => 'wrench-screwdriver'],
                    ['code' => 'galerie', 'name' => 'Galerie', 'icon' => 'photo'],
                    ['code' => 'surfschule', 'name' => 'Surfschule', 'icon' => 'academic-cap'],
                    ['code' => 'reitstall', 'name' => 'Reitstall', 'icon' => 'academic-cap'],
                    ['code' => 'golfclub', 'name' => 'Golfclub', 'icon' => 'trophy'],
                    ['code' => 'spa_wellness', 'name' => 'Spa / Wellness', 'icon' => 'sparkles'],
                    ['code' => 'vermietungsagentur', 'name' => 'Vermietungsagentur', 'icon' => 'key'],
                    ['code' => 'makler', 'name' => 'Makler', 'icon' => 'building-office-2'],
                    ['code' => 'beach_bar', 'name' => 'Beach Bar', 'icon' => 'sun'],
                    ['code' => 'friseur', 'name' => 'Friseur', 'icon' => 'scissors'],
                ],
            ],
            [
                'code' => 'infrastructure',
                'name' => 'Infrastruktur',
                'description' => 'Krankenhäuser, Apotheken, Schulen, Bahnhöfe, Fähren und öffentliche Einrichtungen',
                'icon' => 'building-library',
                'sort_order' => 3,
                'types' => [
                    ['code' => 'krankenhaus', 'name' => 'Krankenhaus', 'icon' => 'heart'],
                    ['code' => 'apotheke', 'name' => 'Apotheke', 'icon' => 'heart'],
                    ['code' => 'schule', 'name' => 'Schule', 'icon' => 'academic-cap'],
                    ['code' => 'bahnhof', 'name' => 'Bahnhof', 'icon' => 'truck'],
                    ['code' => 'flughafen', 'name' => 'Flughafen', 'icon' => 'paper-airplane'],
                    ['code' => 'faehre', 'name' => 'Fähre', 'icon' => 'paper-airplane'],
                ],
            ],
            [
                'code' => 'event',
                'name' => 'Events & Veranstaltungen',
                'description' => 'Konzerte, Festivals, Märkte und saisonale Veranstaltungen auf Sylt',
                'icon' => 'calendar-days',
                'sort_order' => 4,
                'types' => [
                    ['code' => 'konzert', 'name' => 'Konzert', 'icon' => 'musical-note'],
                    ['code' => 'festival', 'name' => 'Festival', 'icon' => 'star'],
                    ['code' => 'markt', 'name' => 'Markt', 'icon' => 'shopping-cart'],
                    ['code' => 'saisonal_event', 'name' => 'Saisonales Event', 'icon' => 'calendar'],
                ],
            ],
            [
                'code' => 'media',
                'name' => 'Medien & Influencer',
                'description' => 'Influencer, Blogs, Magazine, Podcasts und Social-Media-Accounts mit Sylt-Bezug',
                'icon' => 'camera',
                'sort_order' => 5,
                'types' => [
                    ['code' => 'influencer', 'name' => 'Influencer', 'icon' => 'user-circle', 'extra_field_schema' => [
                        'platforms' => ['type' => 'multi_select', 'label' => 'Plattformen', 'options' => ['instagram', 'tiktok', 'youtube', 'blog', 'podcast']],
                        'follower_count_total' => ['type' => 'integer', 'label' => 'Follower (gesamt)'],
                        'topics' => ['type' => 'multi_select', 'label' => 'Themen', 'options' => ['food', 'travel', 'lifestyle', 'luxury', 'nature', 'family', 'sport']],
                    ]],
                    ['code' => 'blog', 'name' => 'Blog', 'icon' => 'document-text'],
                    ['code' => 'magazin', 'name' => 'Magazin', 'icon' => 'newspaper'],
                    ['code' => 'podcast', 'name' => 'Podcast', 'icon' => 'microphone'],
                    ['code' => 'youtube_channel', 'name' => 'YouTube-Channel', 'icon' => 'play'],
                    ['code' => 'tiktok_account', 'name' => 'TikTok-Account', 'icon' => 'play'],
                ],
            ],
            [
                'code' => 'platform',
                'name' => 'Plattformen',
                'description' => 'Externe Plattformen auf denen Sylt-Entities gelistet, bewertet oder buchbar sind',
                'icon' => 'globe-alt',
                'sort_order' => 6,
                'types' => [
                    ['code' => 'listing_platform', 'name' => 'Listing-Plattform', 'description' => 'Google Business, TripAdvisor, Yelp, Foursquare', 'icon' => 'list-bullet'],
                    ['code' => 'booking_platform', 'name' => 'Booking-Plattform', 'description' => 'Booking.com, Airbnb, HRS, FeWo-direkt, Holidaycheck', 'icon' => 'calendar-days'],
                    ['code' => 'social_platform', 'name' => 'Social-Plattform', 'description' => 'Instagram, Facebook, TikTok, YouTube', 'icon' => 'chat-bubble-left-right'],
                    ['code' => 'reservation_platform', 'name' => 'Reservierungs-Plattform', 'description' => 'OpenTable, Quandoo, TheFork', 'icon' => 'clock'],
                ],
            ],
            [
                'code' => 'person',
                'name' => 'Personen',
                'description' => 'Insel-Persönlichkeiten, Gastgeber, Künstler und bekannte Sylter',
                'icon' => 'user',
                'sort_order' => 7,
                'types' => [
                    ['code' => 'insel_persoenlichkeit', 'name' => 'Insel-Persönlichkeit', 'icon' => 'user-circle'],
                    ['code' => 'gastgeber', 'name' => 'Gastgeber', 'icon' => 'user'],
                    ['code' => 'kuenstler', 'name' => 'Künstler', 'icon' => 'paint-brush'],
                ],
            ],
            [
                'code' => 'organization',
                'name' => 'Organisationen',
                'description' => 'Vereine, Stiftungen, Kirchen und institutionelle Organisationen auf Sylt',
                'icon' => 'building-library',
                'sort_order' => 8,
                'types' => [
                    ['code' => 'verein', 'name' => 'Verein', 'icon' => 'users'],
                    ['code' => 'stiftung', 'name' => 'Stiftung', 'icon' => 'building-library'],
                    ['code' => 'kirche', 'name' => 'Kirche', 'icon' => 'building-library'],
                    ['code' => 'tourismusverband', 'name' => 'Tourismusverband', 'icon' => 'flag'],
                ],
            ],
            [
                'code' => 'nature',
                'name' => 'Natur & Schutzgebiete',
                'description' => 'Wattenmeer, Naturschutzgebiete, Wanderwege und natürliche Wahrzeichen',
                'icon' => 'globe-europe-africa',
                'sort_order' => 9,
                'types' => [
                    ['code' => 'naturschutzgebiet', 'name' => 'Naturschutzgebiet', 'icon' => 'shield-check'],
                    ['code' => 'wattenmeer', 'name' => 'Wattenmeer-Abschnitt', 'icon' => 'globe-europe-africa'],
                    ['code' => 'wanderweg', 'name' => 'Wanderweg', 'icon' => 'map'],
                    ['code' => 'radweg', 'name' => 'Radweg', 'icon' => 'map'],
                ],
            ],
        ];

        $now = now();

        foreach ($groups as $groupData) {
            $types = $groupData['types'];
            unset($groupData['types']);

            $groupId = DB::table('sj_entity_type_groups')->insertGetId(array_merge($groupData, [
                'uuid' => UuidV7::generate(),
                'team_id' => $teamId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            foreach ($types as $sortOrder => $typeData) {
                $extraFieldSchema = $typeData['extra_field_schema'] ?? null;
                unset($typeData['extra_field_schema']);

                DB::table('sj_entity_types')->insert(array_merge($typeData, [
                    'uuid' => UuidV7::generate(),
                    'team_id' => $teamId,
                    'group_id' => $groupId,
                    'extra_field_schema' => $extraFieldSchema ? json_encode($extraFieldSchema) : null,
                    'sort_order' => $sortOrder + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        $teamId = config('syltjunkie.seed_team_id', 9);
        DB::table('sj_entity_types')->where('team_id', $teamId)->delete();
        DB::table('sj_entity_type_groups')->where('team_id', $teamId)->delete();
    }
};
