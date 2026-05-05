<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\DTOs\DataForSeo\OnPageResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjPageChange;
use Platform\Syltjunkie\Models\SjPageSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class FetchPageSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.page_snapshots.FETCH';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/page_snapshots/fetch - Crawlt Entity-URLs via DataForSEO OnPageInstant (~$0.15/URL). '
            . 'Erfasst Title, Description, Headings, Word Count, Load Time, OnPage Score. '
            . 'Erkennt Änderungen gegenüber dem letzten Snapshot und speichert diese als page_changes.';
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
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur URLs dieser Entity crawlen.',
                ],
                'entity_url_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Einzelne Entity-URL-ID.',
                ],
                'max_urls' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Anzahl URLs. Default: 10. Max: 50.',
                    'default' => 10,
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur anzeigen was passieren würde. Default: false.',
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

            $maxUrls = min((int) ($arguments['max_urls'] ?? 10), 50);
            $dryRun = (bool) ($arguments['dry_run'] ?? false);

            // URLs ermitteln: nur website-Platform, aktiv
            $q = SjEntityUrl::query()
                ->where('team_id', $rootTeamId)
                ->where('is_active', true)
                ->where('platform', 'website')
                ->with('entity:id,name,slug,ort,entity_type_id');

            if (!empty($arguments['entity_url_id'])) {
                $q->where('id', (int) $arguments['entity_url_id']);
            } elseif (!empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }

            $urls = $q->limit($maxUrls)->get();

            if ($urls->isEmpty()) {
                return ToolResult::success([
                    'message' => 'Keine Website-URLs zum Verarbeiten gefunden.',
                    'processed' => 0,
                ]);
            }

            if ($dryRun) {
                $urlSummary = $urls->map(fn($u) => [
                    'id' => $u->id,
                    'url' => $u->url,
                    'entity_name' => $u->entity?->name,
                    'last_checked_at' => $u->last_checked_at?->toIso8601String(),
                ])->toArray();

                return ToolResult::success([
                    'dry_run' => true,
                    'total_urls' => $urls->count(),
                    'urls' => $urlSummary,
                    'estimated_cost_cents' => $urls->count() * 15,
                ]);
            }

            $api = app(DataForSeoApiService::class);
            $results = [];
            $apiCallsMade = 0;
            $totalChanges = 0;
            $today = now()->toDateString();

            foreach ($urls as $entityUrl) {
                try {
                    $onPageResults = $api->getOnPageInstant($context->user, $entityUrl->url);
                    $apiCallsMade++;

                    if (empty($onPageResults)) {
                        $results[] = [
                            'entity_url_id' => $entityUrl->id,
                            'url' => $entityUrl->url,
                            'entity_name' => $entityUrl->entity?->name,
                            'status' => 'no_data',
                            'message' => 'Keine On-Page Daten verfügbar.',
                        ];
                        continue;
                    }

                    /** @var OnPageResult $pageData */
                    $pageData = $onPageResults[0];

                    // Content-Hash berechnen
                    $contentHash = hash('sha256',
                        ($pageData->title ?? '')
                        . implode('', $pageData->h1)
                        . implode('', $pageData->h2)
                        . ($pageData->wordCount ?? '')
                    );

                    // Snapshot speichern (updateOrCreate auf entity_url_id + captured_at)
                    $snapshot = SjPageSnapshot::updateOrCreate(
                        ['entity_url_id' => $entityUrl->id, 'captured_at' => $today],
                        [
                            'team_id' => $rootTeamId,
                            'status_code' => $pageData->statusCode,
                            'title' => $pageData->title,
                            'meta_description' => $pageData->description,
                            'headings' => [
                                'h1' => $pageData->h1,
                                'h2' => $pageData->h2,
                                'h3' => $pageData->h3,
                            ],
                            'word_count' => $pageData->wordCount,
                            'content_length' => $pageData->contentLength,
                            'internal_links_count' => $pageData->internalLinks,
                            'external_links_count' => $pageData->externalLinks,
                            'image_count' => $pageData->images,
                            'load_time' => $pageData->loadTime !== null ? round($pageData->loadTime / 1000, 2) : null,
                            'onpage_score' => $pageData->onpageScore,
                            'content_hash' => $contentHash,
                            'raw_response' => $pageData->toArray(),
                        ]
                    );

                    // Vorherigen Snapshot laden (letzter vor heute)
                    $previousSnapshot = SjPageSnapshot::where('entity_url_id', $entityUrl->id)
                        ->where('captured_at', '<', $today)
                        ->orderByDesc('captured_at')
                        ->first();

                    $changes = [];
                    if ($previousSnapshot) {
                        $changes = $this->detectChanges($rootTeamId, $entityUrl->id, $today, $snapshot, $previousSnapshot);
                        $totalChanges += count($changes);
                    }

                    // last_checked_at updaten
                    $entityUrl->update(['last_checked_at' => now()]);

                    $results[] = [
                        'entity_url_id' => $entityUrl->id,
                        'url' => $entityUrl->url,
                        'entity_name' => $entityUrl->entity?->name,
                        'status' => 'ok',
                        'snapshot_id' => $snapshot->id,
                        'status_code' => $pageData->statusCode,
                        'title' => $pageData->title,
                        'onpage_score' => $pageData->onpageScore,
                        'word_count' => $pageData->wordCount,
                        'load_time' => $pageData->loadTime,
                        'content_hash' => $contentHash,
                        'changes_detected' => count($changes),
                        'changes' => array_map(fn($c) => [
                            'type' => $c['change_type'],
                            'severity' => $c['severity'],
                        ], $changes),
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'entity_url_id' => $entityUrl->id,
                        'url' => $entityUrl->url,
                        'entity_name' => $entityUrl->entity?->name,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return ToolResult::success([
                'processed' => count($results),
                'api_calls_made' => $apiCallsMade,
                'total_changes_detected' => $totalChanges,
                'estimated_cost_cents' => $apiCallsMade * 15,
                'urls' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Vergleicht aktuellen Snapshot mit dem vorherigen und erstellt SjPageChange-Einträge.
     *
     * @return array[] Liste der erkannten Änderungen
     */
    protected function detectChanges(
        int $teamId,
        int $entityUrlId,
        string $today,
        SjPageSnapshot $current,
        SjPageSnapshot $previous,
    ): array {
        $changes = [];

        // Title
        if ($current->title !== $previous->title) {
            $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'title', 'major', $previous->title, $current->title);
        }

        // Meta Description
        if ($current->meta_description !== $previous->meta_description) {
            $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'meta_description', 'moderate', $previous->meta_description, $current->meta_description);
        }

        // H1
        $prevH1 = $previous->headings['h1'] ?? [];
        $currH1 = $current->headings['h1'] ?? [];
        if ($prevH1 !== $currH1) {
            $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'h1', 'major', implode(' | ', $prevH1), implode(' | ', $currH1));
        }

        // H2 added/removed
        $prevH2 = $previous->headings['h2'] ?? [];
        $currH2 = $current->headings['h2'] ?? [];
        $addedH2 = array_values(array_diff($currH2, $prevH2));
        $removedH2 = array_values(array_diff($prevH2, $currH2));

        if (!empty($addedH2)) {
            $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'h2_added', 'moderate', null, implode(' | ', $addedH2), count($addedH2), [
                'added' => $addedH2,
            ]);
        }
        if (!empty($removedH2)) {
            $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'h2_removed', 'moderate', implode(' | ', $removedH2), null, -count($removedH2), [
                'removed' => $removedH2,
            ]);
        }

        // Word Count (Δ > 20%)
        if ($current->word_count && $previous->word_count && $previous->word_count > 0) {
            $delta = $current->word_count - $previous->word_count;
            $pct = abs($delta) / $previous->word_count;
            if ($pct > 0.20) {
                $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'word_count', 'moderate', (string) $previous->word_count, (string) $current->word_count, $delta);
            }
        }

        // Status Code
        if ($current->status_code !== $previous->status_code) {
            $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'status_code', 'major', (string) $previous->status_code, (string) $current->status_code);
        }

        // Load Time (Δ > 50%)
        if ($current->load_time && $previous->load_time && $previous->load_time > 0) {
            $delta = $current->load_time - $previous->load_time;
            $pct = abs($delta) / $previous->load_time;
            if ($pct > 0.50) {
                $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'load_time', 'minor', (string) $previous->load_time, (string) $current->load_time, (int) round($delta * 100));
            }
        }

        // OnPage Score (Δ > 10 Punkte)
        if ($current->onpage_score !== null && $previous->onpage_score !== null) {
            $delta = $current->onpage_score - $previous->onpage_score;
            if (abs($delta) > 10) {
                $changes[] = $this->createChange($teamId, $entityUrlId, $today, 'onpage_score', 'moderate', (string) $previous->onpage_score, (string) $current->onpage_score, (int) round($delta));
            }
        }

        return $changes;
    }

    protected function createChange(
        int $teamId,
        int $entityUrlId,
        string $detectedAt,
        string $changeType,
        string $severity,
        ?string $oldValue,
        ?string $newValue,
        ?int $delta = null,
        ?array $context = null,
    ): array {
        SjPageChange::create([
            'team_id' => $teamId,
            'entity_url_id' => $entityUrlId,
            'detected_at' => $detectedAt,
            'change_type' => $changeType,
            'severity' => $severity,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'delta' => $delta,
            'context' => $context,
        ]);

        return [
            'change_type' => $changeType,
            'severity' => $severity,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'delta' => $delta,
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'page_snapshots', 'fetch', 'dataforseo', 'on_page', 'change_detection'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['external_api', 'costs'],
        ];
    }
}
