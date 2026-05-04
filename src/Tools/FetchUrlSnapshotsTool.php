<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class FetchUrlSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.url_snapshots.FETCH';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/url_snapshots/fetch - Ruft Keyword-Rankings, Traffic und Domain Authority für Entity-URLs via DataForSEO ab und erstellt Snapshots. Nutzt getRankedKeywords() pro Domain. Kostet ~$0.10 pro URL. Filter: entity_url_id (einzeln) oder entity_id (alle URLs einer Entity) oder alle URLs ohne aktuellen Snapshot.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'entity_url_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Einzelne Entity-URL-ID.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Alle URLs einer Entity.',
                ],
                'platform' => [
                    'type' => 'string',
                    'description' => 'Optional: Nur URLs dieser Platform. Default: website (nur eigene Domains haben sinnvolle Ranked-Keywords).',
                    'enum' => ['website', 'google_maps', 'tripadvisor', 'instagram', 'facebook', 'booking', 'yelp', 'other', 'all'],
                    'default' => 'website',
                ],
                'max_urls' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Anzahl URLs pro Aufruf. Default: 5. Max: 20.',
                    'default' => 5,
                ],
                'keywords_limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max Keywords pro URL. Default: 50. Max: 200.',
                    'default' => 50,
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur anzeigen welche URLs verarbeitet werden. Default: false.',
                    'default' => false,
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $maxUrls = min((int) ($arguments['max_urls'] ?? 5), 20);
            $keywordsLimit = min((int) ($arguments['keywords_limit'] ?? 50), 200);
            $dryRun = (bool) ($arguments['dry_run'] ?? false);
            $platformFilter = $arguments['platform'] ?? 'website';

            // URLs ermitteln
            $q = SjEntityUrl::query()
                ->where('team_id', $rootTeamId)
                ->where('is_active', true)
                ->with('entity:id,name,slug');

            if (!empty($arguments['entity_url_id'])) {
                $q->where('id', (int) $arguments['entity_url_id']);
            } elseif (!empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }

            if ($platformFilter !== 'all') {
                $q->where('platform', $platformFilter);
            }

            $urls = $q->limit($maxUrls)->get();

            if ($urls->isEmpty()) {
                return ToolResult::success([
                    'message' => 'Keine URLs zum Verarbeiten gefunden.',
                    'processed' => 0,
                    'snapshots_created' => 0,
                ]);
            }

            if ($dryRun) {
                return ToolResult::success([
                    'dry_run' => true,
                    'urls_to_process' => $urls->map(fn($u) => [
                        'id' => $u->id,
                        'url' => $u->url,
                        'platform' => $u->platform,
                        'entity_name' => $u->entity?->name,
                    ])->toArray(),
                    'estimated_cost_cents' => $urls->count() * 10,
                ]);
            }

            $api = app(DataForSeoApiService::class);
            $results = [];
            $snapshotsCreated = 0;
            $today = now()->toDateString();

            foreach ($urls as $entityUrl) {
                $urlResult = [
                    'entity_url_id' => $entityUrl->id,
                    'url' => $entityUrl->url,
                    'platform' => $entityUrl->platform,
                    'entity_name' => $entityUrl->entity?->name,
                ];

                // Prüfe ob heute schon ein vollständiger Snapshot existiert (nicht nur SERP-Discovery)
                $existingSnapshot = SjUrlSnapshot::where('entity_url_id', $entityUrl->id)
                    ->where('captured_at', $today)
                    ->whereNotNull('organic_traffic_estimate')
                    ->first();

                if ($existingSnapshot) {
                    $urlResult['skipped'] = 'Snapshot für heute existiert bereits.';
                    $urlResult['snapshot_id'] = $existingSnapshot->id;
                    $results[] = $urlResult;
                    continue;
                }

                try {
                    // Domain aus URL extrahieren
                    $domain = parse_url($entityUrl->url, PHP_URL_HOST);
                    if (!$domain) {
                        $urlResult['error'] = 'Ungültige URL — Domain nicht extrahierbar.';
                        $results[] = $urlResult;
                        continue;
                    }

                    // Ranked Keywords für die Domain abrufen
                    $rankedResults = $api->getRankedKeywords(
                        $context->user,
                        $domain,
                        null,
                        null,
                        $keywordsLimit
                    );

                    // Keywords aufbereiten
                    $keywords = [];
                    $totalTraffic = 0;
                    foreach ($rankedResults as $rk) {
                        $keywords[] = [
                            'keyword' => $rk->keyword,
                            'position' => $rk->position,
                            'search_volume' => $rk->searchVolume,
                            'cpc' => $rk->cpc,
                            'competition' => $rk->competition,
                        ];

                        // Grobe Traffic-Schätzung: SV * CTR basierend auf Position
                        if ($rk->searchVolume && $rk->position) {
                            $ctr = $this->estimateCtr($rk->position);
                            $totalTraffic += (int) round($rk->searchVolume * $ctr);
                        }
                    }

                    // Bestehenden Discovery-Snapshot für heute updaten oder neuen erstellen
                    $snapshot = SjUrlSnapshot::where('entity_url_id', $entityUrl->id)
                        ->where('captured_at', $today)
                        ->first();

                    if ($snapshot) {
                        // Bestehenden Discovery-Snapshot mit vollen Daten anreichern
                        $existingKeywords = $snapshot->keywords ?? [];
                        $mergedKeywords = $this->mergeKeywords($existingKeywords, $keywords);

                        $snapshot->update([
                            'keywords' => $mergedKeywords,
                            'organic_traffic_estimate' => $totalTraffic ?: null,
                            'raw_response' => array_merge($snapshot->raw_response ?? [], [
                                'ranked_keywords' => array_map(fn($r) => $r->toArray(), $rankedResults),
                            ]),
                        ]);
                    } else {
                        $snapshot = SjUrlSnapshot::create([
                            'team_id' => $rootTeamId,
                            'entity_url_id' => $entityUrl->id,
                            'captured_at' => $today,
                            'keywords' => $keywords,
                            'organic_traffic_estimate' => $totalTraffic ?: null,
                            'raw_response' => [
                                'source' => 'ranked_keywords',
                                'domain' => $domain,
                                'ranked_keywords' => array_map(fn($r) => $r->toArray(), $rankedResults),
                            ],
                        ]);
                    }

                    $entityUrl->update(['last_checked_at' => now()]);

                    $urlResult['snapshot_id'] = $snapshot->id;
                    $urlResult['keywords_count'] = count($keywords);
                    $urlResult['organic_traffic_estimate'] = $totalTraffic;
                    $snapshotsCreated++;

                } catch (\Throwable $e) {
                    $urlResult['error'] = $e->getMessage();
                }

                $results[] = $urlResult;
            }

            return ToolResult::success([
                'processed' => count($results),
                'snapshots_created' => $snapshotsCreated,
                'estimated_cost_cents' => $snapshotsCreated * 10,
                'urls' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Schätzt die Click-Through-Rate basierend auf der SERP-Position.
     */
    protected function estimateCtr(int $position): float
    {
        return match (true) {
            $position === 1 => 0.28,
            $position === 2 => 0.15,
            $position === 3 => 0.11,
            $position <= 5 => 0.06,
            $position <= 10 => 0.03,
            $position <= 20 => 0.01,
            default => 0.005,
        };
    }

    /**
     * Merged SERP-Discovery-Keywords mit Ranked-Keywords (Discovery-Daten bleiben erhalten).
     */
    protected function mergeKeywords(array $existing, array $ranked): array
    {
        $byKeyword = [];

        // Ranked Keywords zuerst (vollständigere Daten)
        foreach ($ranked as $kw) {
            $byKeyword[strtolower($kw['keyword'])] = $kw;
        }

        // Discovery-Keywords nur hinzufügen wenn nicht schon durch Ranked abgedeckt
        foreach ($existing as $kw) {
            $key = strtolower($kw['keyword']);
            if (!isset($byKeyword[$key])) {
                $byKeyword[$key] = $kw;
            }
        }

        return array_values($byKeyword);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'url_snapshots', 'fetch', 'dataforseo', 'keywords'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['external_api', 'costs'],
        ];
    }
}
