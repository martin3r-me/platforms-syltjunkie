<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjShopProduct;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateShopVariantsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.shop_variants.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/shop/variants - Aktualisiert Varianten eines Produkts (Preis, Stock, SKU, Aktiv-Status). ERFORDERLICH: product_id, variants (Array mit variant_id und Feldern). Beispiel: variants: [{"variant_id": 1, "price_cents": 2995, "stock_quantity": 50}]';
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
                'variants' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array von Varianten-Updates.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'variant_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Varianten-ID.'],
                            'sku' => ['type' => 'string', 'description' => 'Optional: Neue Artikelnummer.'],
                            'price_cents' => ['type' => 'integer', 'description' => 'Optional: Varianten-Preis in Cent. Überschreibt Produkt-Preis.'],
                            'stock_quantity' => ['type' => 'integer', 'description' => 'Optional: Lagerbestand.'],
                            'is_active' => ['type' => 'boolean', 'description' => 'Optional: Aktiv/Inaktiv.'],
                        ],
                        'required' => ['variant_id'],
                    ],
                ],
            ],
            'required' => ['product_id', 'variants'],
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
            if (empty($arguments['variants']) || !is_array($arguments['variants'])) {
                return ToolResult::error('VALIDATION_ERROR', 'variants Array ist erforderlich.');
            }

            $product = SjShopProduct::where('team_id', $rootTeamId)->find((int) $arguments['product_id']);
            if (!$product) {
                return ToolResult::error('NOT_FOUND', 'Produkt nicht gefunden.');
            }

            $updated = [];
            foreach ($arguments['variants'] as $variantData) {
                if (empty($variantData['variant_id'])) continue;

                $variant = $product->variants()->find((int) $variantData['variant_id']);
                if (!$variant) continue;

                $fields = [];
                if (array_key_exists('sku', $variantData)) {
                    $variant->sku = $variantData['sku'] ?: null;
                    $fields[] = 'sku';
                }
                if (array_key_exists('price_cents', $variantData)) {
                    $variant->price_cents = $variantData['price_cents'] ?: null;
                    $fields[] = 'price_cents';
                }
                if (array_key_exists('stock_quantity', $variantData)) {
                    $variant->stock_quantity = (int) $variantData['stock_quantity'];
                    $fields[] = 'stock_quantity';
                }
                if (array_key_exists('is_active', $variantData)) {
                    $variant->is_active = (bool) $variantData['is_active'];
                    $fields[] = 'is_active';
                }

                $variant->save();

                $updated[] = [
                    'variant_id' => $variant->id,
                    'label' => $variant->label,
                    'sku' => $variant->sku,
                    'price_cents' => $variant->price_cents,
                    'effective_price_cents' => $variant->effective_price_cents,
                    'stock_quantity' => $variant->stock_quantity,
                    'is_active' => $variant->is_active,
                    'updated_fields' => $fields,
                ];
            }

            return ToolResult::success([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'updated_count' => count($updated),
                'variants' => $updated,
                'message' => count($updated) . ' Variante(n) aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'shop', 'variants', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
