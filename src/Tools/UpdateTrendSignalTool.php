<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjTrendSignal;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateTrendSignalTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.trend_signals.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/trend-signals/{id} - Aktualisiert den Status eines Trend Signals.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'signal_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des Trend Signals.'],
                'status' => [
                    'type' => 'string',
                    'enum' => ['new', 'acknowledged', 'resolved'],
                    'description' => 'ERFORDERLICH: Neuer Status.',
                ],
            ],
            'required' => ['signal_id', 'status'],
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

            $signal = SjTrendSignal::where('team_id', $rootTeamId)
                ->find($arguments['signal_id'] ?? 0);

            if (!$signal) {
                return ToolResult::error('NOT_FOUND', 'Trend Signal nicht gefunden.');
            }

            $signal->update(['status' => $arguments['status']]);

            return ToolResult::success([
                'id' => $signal->id,
                'status' => $signal->status,
                'message' => 'Trend Signal aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'trends', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
