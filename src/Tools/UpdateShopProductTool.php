<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjShopProduct;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateShopProductTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.shop_products.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/shop/products/{id} - Aktualisiert ein Shop-Produkt. ERFORDERLICH: product_id. Alle anderen Felder optional.';
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
                'product_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: Produkt-ID.',
                ],
                'name' => ['type' => 'string', 'description' => 'Optional: Neuer Produktname.'],
                'slug' => ['type' => 'string', 'description' => 'Optional: Neuer URL-Slug.'],
                'description' => ['type' => 'string', 'description' => 'Optional: Neue Beschreibung.'],
                'price_cents' => ['type' => 'integer', 'description' => 'Optional: Neuer Preis in Cent.'],
                'compare_at_price_cents' => ['type' => 'integer', 'description' => 'Optional: Neuer Streichpreis. 0 zum Entfernen.'],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: draft, active, archived.',
                    'enum' => ['draft', 'active', 'archived'],
                ],
                'is_featured' => ['type' => 'boolean', 'description' => 'Optional: Featured-Flag.'],
                'stock_quantity' => ['type' => 'integer', 'description' => 'Optional: Neuer Lagerbestand.'],
                'extra_fields' => ['type' => 'object', 'description' => 'Optional: Zusätzliche Felder (merged).'],
            ],
            'required' => ['product_id'],
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

            if (empty($arguments['product_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'product_id ist erforderlich.');
            }

            $product = SjShopProduct::where('team_id', $rootTeamId)->find((int) $arguments['product_id']);
            if (!$product) {
                return ToolResult::error('NOT_FOUND', 'Produkt nicht gefunden.');
            }

            $updatable = ['name', 'slug', 'description', 'price_cents', 'status', 'is_featured', 'stock_quantity'];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $product->{$field} = $arguments[$field];
                }
            }

            if (array_key_exists('compare_at_price_cents', $arguments)) {
                $product->compare_at_price_cents = $arguments['compare_at_price_cents'] ?: null;
            }

            if (array_key_exists('extra_fields', $arguments) && is_array($arguments['extra_fields'])) {
                $existing = $product->extra_fields ?? [];
                $product->extra_fields = array_merge($existing, $arguments['extra_fields']);
            }

            $product->save();

            return ToolResult::success([
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'status' => $product->status,
                'price_cents' => $product->price_cents,
                'formatted_price' => $product->formatted_price,
                'message' => 'Produkt aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'shop', 'products', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
