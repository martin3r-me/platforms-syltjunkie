<?php

namespace Platform\Syltjunkie\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjContentPiece;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateContentPieceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.content_pieces.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/content-pieces - Erstellt ein Content Piece (Blog, Guide, Listicle, ...). ERFORDERLICH: title, content_type. Optional: keyword_ids, entity_ids, body_markdown.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'title' => ['type' => 'string', 'description' => 'ERFORDERLICH: Titel des Content Pieces.'],
                'content_type' => ['type' => 'string', 'enum' => ['guide', 'listicle', 'review', 'event', 'news', 'landing_page'], 'description' => 'ERFORDERLICH: Content-Typ.'],
                'slug' => ['type' => 'string', 'description' => 'Optional: URL-Slug. Wird aus Titel generiert wenn leer.'],
                'status' => ['type' => 'string', 'enum' => ['brief', 'draft', 'review', 'published'], 'description' => 'Optional: Status. Default: brief.'],
                'brief_notes' => ['type' => 'string', 'description' => 'Optional: Brief/Notizen zum Content.'],
                'body_markdown' => ['type' => 'string', 'description' => 'Optional: Content Body als Markdown.'],
                'excerpt' => ['type' => 'string', 'description' => 'Optional: Kurztext/Teaser.'],
                'seo_title' => ['type' => 'string', 'description' => 'Optional: SEO-Titel (max 60 Zeichen).'],
                'seo_description' => ['type' => 'string', 'description' => 'Optional: SEO-Beschreibung (max 160 Zeichen).'],
                'keyword_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Keyword-IDs. Erstes Keyword wird als Primary markiert.',
                ],
                'entity_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Entity-IDs die mit dem Content verknüpft werden.',
                ],
                'cover_image_id' => ['type' => 'integer', 'description' => 'Optional: ID eines SjImage als Cover.'],
            ],
            'required' => ['title', 'content_type'],
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

            $title = trim((string) ($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }
            if (empty($arguments['content_type'])) {
                return ToolResult::error('VALIDATION_ERROR', 'content_type ist erforderlich.');
            }

            $piece = SjContentPiece::create([
                'team_id' => $rootTeamId,
                'title' => $title,
                'slug' => !empty($arguments['slug']) ? $arguments['slug'] : Str::slug($title),
                'content_type' => $arguments['content_type'],
                'status' => $arguments['status'] ?? 'brief',
                'brief_notes' => ($arguments['brief_notes'] ?? null) ?: null,
                'body_markdown' => ($arguments['body_markdown'] ?? null) ?: null,
                'excerpt' => ($arguments['excerpt'] ?? null) ?: null,
                'seo_title' => ($arguments['seo_title'] ?? null) ?: null,
                'seo_description' => ($arguments['seo_description'] ?? null) ?: null,
                'cover_image_id' => $arguments['cover_image_id'] ?? null,
            ]);

            // Attach keywords
            if (!empty($arguments['keyword_ids']) && is_array($arguments['keyword_ids'])) {
                $keywordSync = [];
                foreach ($arguments['keyword_ids'] as $i => $kwId) {
                    $keywordSync[(int) $kwId] = ['is_primary' => $i === 0];
                }
                $piece->keywords()->attach($keywordSync);
            }

            // Attach entities
            if (!empty($arguments['entity_ids']) && is_array($arguments['entity_ids'])) {
                $entitySync = [];
                foreach ($arguments['entity_ids'] as $i => $entId) {
                    $entitySync[(int) $entId] = ['display_order' => $i, 'is_primary' => $i === 0];
                }
                $piece->entities()->attach($entitySync);
            }

            return ToolResult::success([
                'id' => $piece->id,
                'uuid' => $piece->uuid,
                'title' => $piece->title,
                'slug' => $piece->slug,
                'status' => $piece->status,
                'content_type' => $piece->content_type,
                'message' => 'Content Piece erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'content', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
