<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateUrlSnapshotTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.url_snapshots.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/url_snapshots - Erfasst einen Snapshot für eine Entity-URL (Keywords, Rankings, Traffic). ERFORDERLICH: entity_url_id. Optional: captured_at (Default: heute).';
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
                    'description' => 'ERFORDERLICH: ID der Entity-URL.',
                ],
                'captured_at' => [
                    'type' => 'string',
                    'description' => 'Optional: Stichtag (YYYY-MM-DD). Default: heute.',
                ],
                'keywords' => [
                    'type' => 'array',
                    'description' => 'Optional: Array mit Keyword-Daten. Jedes Element: {keyword, position, search_volume, cpc, competition}.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => ['type' => 'string'],
                            'position' => ['type' => 'integer'],
                            'search_volume' => ['type' => 'integer'],
                            'cpc' => ['type' => 'number'],
                            'competition' => ['type' => 'number'],
                        ],
                    ],
                ],
                'organic_traffic_estimate' => [
                    'type' => 'integer',
                    'description' => 'Optional: Geschätzter organischer Traffic.',
                ],
                'domain_authority' => [
                    'type' => 'integer',
                    'description' => 'Optional: Domain Authority (0-100).',
                ],
                'backlinks_count' => [
                    'type' => 'integer',
                    'description' => 'Optional: Anzahl Backlinks.',
                ],
                'raw_response' => [
                    'type' => 'object',
                    'description' => 'Optional: Original API-Response als JSON.',
                ],
            ],
            'required' => ['entity_url_id'],
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

            if (empty($arguments['entity_url_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_url_id ist erforderlich.');
            }

            $entityUrl = SjEntityUrl::where('team_id', $rootTeamId)->find((int) $arguments['entity_url_id']);
            if (!$entityUrl) {
                return ToolResult::error('NOT_FOUND', 'Entity-URL nicht gefunden.');
            }

            $capturedAt = $arguments['captured_at'] ?? now()->toDateString();

            // Check for duplicate snapshot
            $existing = SjUrlSnapshot::where('entity_url_id', $entityUrl->id)
                ->where('captured_at', $capturedAt)
                ->first();
            if ($existing) {
                return ToolResult::error('DUPLICATE', "Snapshot für diese URL am {$capturedAt} existiert bereits (ID: {$existing->id}).");
            }

            $snapshot = SjUrlSnapshot::create([
                'team_id' => $rootTeamId,
                'entity_url_id' => $entityUrl->id,
                'captured_at' => $capturedAt,
                'keywords' => (isset($arguments['keywords']) && is_array($arguments['keywords'])) ? $arguments['keywords'] : null,
                'organic_traffic_estimate' => $arguments['organic_traffic_estimate'] ?? null,
                'domain_authority' => $arguments['domain_authority'] ?? null,
                'backlinks_count' => $arguments['backlinks_count'] ?? null,
                'raw_response' => (isset($arguments['raw_response']) && is_array($arguments['raw_response'])) ? $arguments['raw_response'] : null,
            ]);

            // Update last_checked_at on the entity URL
            $entityUrl->update(['last_checked_at' => now()]);

            return ToolResult::success([
                'id' => $snapshot->id,
                'uuid' => $snapshot->uuid,
                'entity_url_id' => $snapshot->entity_url_id,
                'url' => $entityUrl->url,
                'captured_at' => $snapshot->captured_at->toDateString(),
                'keywords_count' => is_array($snapshot->keywords) ? count($snapshot->keywords) : 0,
                'organic_traffic_estimate' => $snapshot->organic_traffic_estimate,
                'domain_authority' => $snapshot->domain_authority,
                'backlinks_count' => $snapshot->backlinks_count,
                'message' => 'Snapshot erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'url_snapshots', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
