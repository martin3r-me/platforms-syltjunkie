<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjShopProduct;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListShopProductsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam, HasStandardGetOperations;

    public function getName(): string
    {
        return 'syltjunkie.shop_products.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/shop/products - Listet Shop-Produkte. Filter nach status, product_type, is_featured. Unterstützt search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Status.',
                    'enum' => ['draft', 'active', 'archived'],
                ],
                'product_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Produkttyp.',
                    'enum' => ['physical', 'digital'],
                ],
                'is_featured' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur Featured-Produkte.',
                ],
            ],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $query = SjShopProduct::where('team_id', $rootTeamId)
                ->with(['variants', 'dimensions.values']);

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (!empty($arguments['product_type'])) {
                $query->where('product_type', $arguments['product_type']);
            }
            if (isset($arguments['is_featured'])) {
                $query->where('is_featured', (bool) $arguments['is_featured']);
            }

            $this->applyStandardSearch($query, $arguments, ['name', 'slug', 'description']);
            $this->applyStandardSort($query, $arguments, 'sort_order', 'asc');

            return $this->applyStandardPaginationResult($query, $arguments, function ($product) {
                return [
                    'id' => $product->id,
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'product_type' => $product->product_type,
                    'price_cents' => $product->price_cents,
                    'formatted_price' => $product->formatted_price,
                    'compare_at_price_cents' => $product->compare_at_price_cents,
                    'status' => $product->status,
                    'is_featured' => $product->is_featured,
                    'stock_quantity' => $product->stock_quantity,
                    'variant_count' => $product->variants->count(),
                    'dimension_count' => $product->dimensions->count(),
                    'extra_fields' => $product->extra_fields,
                ];
            });
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['syltjunkie', 'shop', 'products', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
