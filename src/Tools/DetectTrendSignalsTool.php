<?php

namespace Platform\Syltjunkie\Tools;

use Carbon\Carbon;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjTrendSignal;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class DetectTrendSignalsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.trend_signals.DETECT';
    }

    public function getDescription(): string
    {
        return 'Analysiert Entity-Snapshots und Keyword-Rankings auf Trends (Rating-Drops, Review-Velocity, Ranking-Changes, neue Keywords). Erstellt TrendSignal-Einträge.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Nur für diese Entity.'],
                'max_entities' => ['type' => 'integer', 'description' => 'Max Entities zu analysieren. Default 50.'],
                'dry_run' => ['type' => 'boolean', 'description' => 'Wenn true, keine Signals erstellen.'],
            ],
            'required' => [],
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

            $maxEntities = (int) ($arguments['max_entities'] ?? 50);
            $dryRun = (bool) ($arguments['dry_run'] ?? false);
            $today = Carbon::today();

            $query = SjEntity::where('team_id', $rootTeamId)
                ->where('is_active', true)
                ->with(['entityUrls' => fn($q) => $q->where('is_active', true)]);

            if (!empty($arguments['entity_id'])) {
                $query->where('id', $arguments['entity_id']);
            }

            $entities = $query->limit($maxEntities)->get();

            $signalsCreated = 0;
            $signalsSkipped = 0;
            $details = [];

            foreach ($entities as $entity) {
                foreach ($entity->entityUrls as $entityUrl) {
                    // Get last 2 snapshots for comparison
                    $snapshots = SjUrlSnapshot::where('entity_url_id', $entityUrl->id)
                        ->orderByDesc('captured_at')
                        ->limit(2)
                        ->get();

                    if ($snapshots->count() < 2) {
                        continue;
                    }

                    $latest = $snapshots[0];
                    $previous = $snapshots[1];

                    // --- Rating Drop ---
                    if ($latest->average_rating !== null && $previous->average_rating !== null) {
                        $delta = (float) $latest->average_rating - (float) $previous->average_rating;
                        if ($delta < -0.2) {
                            $result = $this->createSignal($rootTeamId, $entity, $entityUrl, null, [
                                'signal_type' => 'rating_drop',
                                'severity' => 'action',
                                'title' => "Rating gefallen: {$previous->average_rating} → {$latest->average_rating}",
                                'metric_before' => (float) $previous->average_rating,
                                'metric_after' => (float) $latest->average_rating,
                                'metric_delta' => $delta,
                                'detected_at' => $today,
                            ], $dryRun);
                            if ($result === 'created') $signalsCreated++;
                            elseif ($result === 'skipped') $signalsSkipped++;
                        }
                    }

                    // --- Review Velocity ---
                    if ($latest->review_count !== null && $previous->review_count !== null) {
                        $reviewDelta = (int) $latest->review_count - (int) $previous->review_count;
                        $daysBetween = max(1, $latest->captured_at->diffInDays($previous->captured_at));
                        $velocity = $reviewDelta / $daysBetween;

                        // Get historical average velocity from all snapshots
                        $allSnapshots = SjUrlSnapshot::where('entity_url_id', $entityUrl->id)
                            ->whereNotNull('review_count')
                            ->orderBy('captured_at')
                            ->pluck('review_count', 'captured_at');

                        $historicalVelocity = 0;
                        if ($allSnapshots->count() >= 3) {
                            $values = $allSnapshots->values();
                            $totalDelta = $values->last() - $values->first();
                            $totalDays = max(1, Carbon::parse($allSnapshots->keys()->first())->diffInDays(Carbon::parse($allSnapshots->keys()->last())));
                            $historicalVelocity = $totalDelta / $totalDays;
                        }

                        if ($historicalVelocity > 0 && $velocity > $historicalVelocity * 2) {
                            $result = $this->createSignal($rootTeamId, $entity, $entityUrl, null, [
                                'signal_type' => 'review_velocity',
                                'severity' => 'watch',
                                'title' => "Review-Velocity {$reviewDelta} neue Reviews in {$daysBetween} Tagen",
                                'description' => "Historischer Durchschnitt: " . round($historicalVelocity, 2) . "/Tag, aktuell: " . round($velocity, 2) . "/Tag",
                                'metric_before' => round($historicalVelocity, 4),
                                'metric_after' => round($velocity, 4),
                                'metric_delta' => round($velocity - $historicalVelocity, 4),
                                'detected_at' => $today,
                            ], $dryRun);
                            if ($result === 'created') $signalsCreated++;
                            elseif ($result === 'skipped') $signalsSkipped++;
                        }
                    }

                    // --- Ranking Changes + New Keywords ---
                    $rankings = SjKeywordRanking::where('entity_url_id', $entityUrl->id)
                        ->with('keyword:id,keyword')
                        ->whereIn('id', function ($q) use ($entityUrl) {
                            $q->selectRaw('MAX(id)')
                                ->from('sj_keyword_rankings')
                                ->where('entity_url_id', $entityUrl->id)
                                ->groupBy('keyword_id');
                        })
                        ->get();

                    foreach ($rankings as $ranking) {
                        // New keyword
                        if ($ranking->previous_position === null && $ranking->position <= 20) {
                            $result = $this->createSignal($rootTeamId, $entity, $entityUrl, $ranking->keyword_id, [
                                'signal_type' => 'new_keyword',
                                'severity' => 'info',
                                'title' => "Neues Keyword: \"{$ranking->keyword?->keyword}\" auf Pos. {$ranking->position}",
                                'metric_after' => $ranking->position,
                                'detected_at' => $today,
                            ], $dryRun);
                            if ($result === 'created') $signalsCreated++;
                            elseif ($result === 'skipped') $signalsSkipped++;
                            continue;
                        }

                        // Ranking change > 10 positions
                        $positionDelta = $ranking->position_delta;
                        if ($positionDelta !== null && abs($positionDelta) > 10) {
                            $isImproved = $positionDelta > 0;
                            $result = $this->createSignal($rootTeamId, $entity, $entityUrl, $ranking->keyword_id, [
                                'signal_type' => 'ranking_change',
                                'severity' => $isImproved ? 'info' : 'action',
                                'title' => ($isImproved ? 'Ranking verbessert' : 'Ranking gefallen') . ": \"{$ranking->keyword?->keyword}\" {$ranking->previous_position} → {$ranking->position}",
                                'metric_before' => $ranking->previous_position,
                                'metric_after' => $ranking->position,
                                'metric_delta' => $positionDelta,
                                'detected_at' => $today,
                            ], $dryRun);
                            if ($result === 'created') $signalsCreated++;
                            elseif ($result === 'skipped') $signalsSkipped++;
                        }
                    }
                }
            }

            return ToolResult::success([
                'entities_analyzed' => $entities->count(),
                'signals_created' => $signalsCreated,
                'signals_skipped_duplicates' => $signalsSkipped,
                'dry_run' => $dryRun,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function createSignal(int $teamId, SjEntity $entity, $entityUrl, ?int $keywordId, array $data, bool $dryRun): string
    {
        // Dedup check
        $exists = SjTrendSignal::where('signal_type', $data['signal_type'])
            ->where('entity_id', $entity->id)
            ->where('keyword_id', $keywordId)
            ->where('detected_at', $data['detected_at'])
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'created';
        }

        SjTrendSignal::create(array_merge($data, [
            'team_id' => $teamId,
            'entity_id' => $entity->id,
            'entity_url_id' => $entityUrl?->id,
            'keyword_id' => $keywordId,
        ]));

        return 'created';
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'trends', 'detection'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
