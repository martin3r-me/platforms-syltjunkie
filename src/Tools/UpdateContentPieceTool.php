<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjContentPiece;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateContentPieceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.content_pieces.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/content-pieces/{id} - Aktualisiert ein Content Piece. ERFORDERLICH: content_piece_id. Alle anderen Felder optional.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_piece_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Content-Piece-ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'title' => ['type' => 'string', 'description' => 'Optional: Neuer Titel.'],
                'slug' => ['type' => 'string', 'description' => 'Optional: Neuer Slug.'],
                'status' => ['type' => 'string', 'enum' => ['brief', 'draft', 'review', 'published', 'archived'], 'description' => 'Optional: Neuer Status.'],
                'content_type' => ['type' => 'string', 'enum' => ['guide', 'listicle', 'review', 'event', 'news', 'landing_page'], 'description' => 'Optional: Neuer Content-Typ.'],
                'brief_notes' => ['type' => 'string', 'description' => 'Optional: Brief/Notizen.'],
                'body_markdown' => ['type' => 'string', 'description' => 'Optional: Content Body als Markdown.'],
                'excerpt' => ['type' => 'string', 'description' => 'Optional: Kurztext/Teaser.'],
                'seo_title' => ['type' => 'string', 'description' => 'Optional: SEO-Titel.'],
                'seo_description' => ['type' => 'string', 'description' => 'Optional: SEO-Beschreibung.'],
                'published_url' => ['type' => 'string', 'description' => 'Optional: URL des veröffentlichten Contents.'],
                'cover_image_id' => ['type' => ['integer', 'null'], 'description' => 'Optional: Cover-Bild ID (null zum Entfernen).'],
                'keyword_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Keyword-IDs (ersetzt bestehende). Erstes = Primary.',
                ],
                'entity_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Entity-IDs (ersetzt bestehende). Erstes = Primary.',
                ],
            ],
            'required' => ['content_piece_id'],
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

            $piece = SjContentPiece::where('team_id', $rootTeamId)
                ->find($arguments['content_piece_id'] ?? 0);

            if (!$piece) {
                return ToolResult::error('NOT_FOUND', 'Content Piece nicht gefunden.');
            }

            $updatable = ['title', 'slug', 'status', 'content_type', 'brief_notes', 'body_markdown', 'excerpt', 'seo_title', 'seo_description', 'published_url', 'cover_image_id'];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $piece->{$field} = $arguments[$field];
                }
            }

            // Auto-set published_at when status changes to published
            if (array_key_exists('status', $arguments) && $arguments['status'] === 'published' && !$piece->published_at) {
                $piece->published_at = now();
            }

            $piece->save();

            // Sync keywords if provided
            if (array_key_exists('keyword_ids', $arguments) && is_array($arguments['keyword_ids'])) {
                $keywordSync = [];
                foreach ($arguments['keyword_ids'] as $i => $kwId) {
                    $keywordSync[(int) $kwId] = ['is_primary' => $i === 0];
                }
                $piece->keywords()->sync($keywordSync);
            }

            // Sync entities if provided
            if (array_key_exists('entity_ids', $arguments) && is_array($arguments['entity_ids'])) {
                $entitySync = [];
                foreach ($arguments['entity_ids'] as $i => $entId) {
                    $entitySync[(int) $entId] = ['display_order' => $i, 'is_primary' => $i === 0];
                }
                $piece->entities()->sync($entitySync);
            }

            return ToolResult::success([
                'id' => $piece->id,
                'title' => $piece->title,
                'slug' => $piece->slug,
                'status' => $piece->status,
                'message' => 'Content Piece aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'content', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
