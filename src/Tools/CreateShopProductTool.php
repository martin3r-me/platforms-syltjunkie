<?php

namespace Platform\Syltjunkie\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjShopProduct;
use Platform\Syltjunkie\Models\SjShopProductDimension;
use Platform\Syltjunkie\Models\SjShopProductDimensionValue;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateShopProductTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.shop_products.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/shop/products - Erstellt ein Shop-Produkt (physical oder digital). ERFORDERLICH: name, price_cents. Optional: dimensions mit values für Varianten-Matrix (z.B. Größe/Farbe). Varianten werden automatisch als Kreuzprodukt generiert.';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Produktname (z.B. "Syltjunkie T-Shirt").',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Optional: URL-Slug. Wird automatisch aus name generiert.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Produktbeschreibung (Markdown).',
                ],
                'product_type' => [
                    'type' => 'string',
                    'description' => 'Optional: physical oder digital. Default: physical.',
                    'enum' => ['physical', 'digital'],
                    'default' => 'physical',
                ],
                'price_cents' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: Preis in Cent (z.B. 2990 = 29,90€).',
                ],
                'compare_at_price_cents' => [
                    'type' => 'integer',
                    'description' => 'Optional: Streichpreis in Cent.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: draft, active, archived. Default: draft.',
                    'enum' => ['draft', 'active', 'archived'],
                    'default' => 'draft',
                ],
                'is_featured' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Auf Startseite zeigen. Default: false.',
                ],
                'stock_quantity' => [
                    'type' => 'integer',
                    'description' => 'Optional: Lagerbestand (nur physical). NULL = unbegrenzt.',
                ],
                'sku_prefix' => [
                    'type' => 'string',
                    'description' => 'Optional: Präfix für Artikelnummern (z.B. "SJ-TS"). Default: generiert aus Name.',
                ],
                'extra_fields' => [
                    'type' => 'object',
                    'description' => 'Optional: Zusätzliche Felder als JSON (z.B. {"material": "100% Baumwolle", "gewicht_g": 200}).',
                ],
                'dimensions' => [
                    'type' => 'array',
                    'description' => 'Optional: Dimensionen mit Werten für Varianten-Matrix. Beispiel: [{"name": "Größe", "values": ["S", "M", "L", "XL"]}, {"name": "Farbe", "values": ["Weiß", "Schwarz", "Navy"]}]. Varianten werden automatisch als Kreuzprodukt generiert.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Dimension-Name (z.B. "Größe", "Farbe").'],
                            'values' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Werte für diese Dimension.',
                            ],
                        ],
                        'required' => ['name', 'values'],
                    ],
                ],
            ],
            'required' => ['name', 'price_cents'],
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

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }
            if (!isset($arguments['price_cents']) || !is_numeric($arguments['price_cents'])) {
                return ToolResult::error('VALIDATION_ERROR', 'price_cents ist erforderlich (Integer, z.B. 2990 = 29,90€).');
            }

            $productType = $arguments['product_type'] ?? 'physical';

            $product = SjShopProduct::create([
                'team_id' => $rootTeamId,
                'name' => $name,
                'slug' => !empty($arguments['slug']) ? $arguments['slug'] : null,
                'description' => ($arguments['description'] ?? null) ?: null,
                'product_type' => $productType,
                'price_cents' => (int) $arguments['price_cents'],
                'compare_at_price_cents' => !empty($arguments['compare_at_price_cents']) ? (int) $arguments['compare_at_price_cents'] : null,
                'status' => $arguments['status'] ?? 'draft',
                'is_featured' => (bool) ($arguments['is_featured'] ?? false),
                'stock_quantity' => $productType === 'physical' ? ($arguments['stock_quantity'] ?? null) : null,
                'extra_fields' => (isset($arguments['extra_fields']) && is_array($arguments['extra_fields'])) ? $arguments['extra_fields'] : null,
            ]);

            // Create dimensions and variants if provided
            $variantData = [];
            if (!empty($arguments['dimensions']) && is_array($arguments['dimensions']) && $productType === 'physical') {
                $dimensions = [];
                foreach ($arguments['dimensions'] as $sortOrder => $dim) {
                    $dimension = $product->dimensions()->create([
                        'name' => $dim['name'],
                        'sort_order' => $sortOrder,
                    ]);

                    $valueModels = [];
                    foreach (($dim['values'] ?? []) as $vSortOrder => $value) {
                        $valueModels[] = $dimension->values()->create([
                            'value' => $value,
                            'sort_order' => $vSortOrder,
                        ]);
                    }
                    $dimensions[] = ['dimension' => $dimension, 'values' => $valueModels];
                }

                // Generate variant combinations
                $valueSets = collect($dimensions)
                    ->filter(fn ($d) => !empty($d['values']))
                    ->map(fn ($d) => collect($d['values'])->map(fn ($v) => ['id' => $v->id, 'value' => $v->value, 'dimension_name' => $d['dimension']->name])->toArray())
                    ->values()
                    ->toArray();

                $combinations = $this->generateCombinations($valueSets);
                $skuPrefix = !empty($arguments['sku_prefix'])
                    ? $arguments['sku_prefix']
                    : Str::upper(Str::substr(Str::slug($name, ''), 0, 6));

                foreach ($combinations as $index => $combo) {
                    $labelParts = [];
                    $options = [];
                    $valueIds = [];
                    foreach ($combo as $item) {
                        $labelParts[] = $item['value'];
                        $options[$item['dimension_name']] = $item['value'];
                        $valueIds[] = $item['id'];
                    }
                    $label = implode(' / ', $labelParts);
                    $sku = sprintf('%s-%03d', $skuPrefix, $index + 1);

                    $variant = $product->variants()->create([
                        'label' => $label,
                        'sku' => $sku,
                        'price_cents' => null,
                        'stock_quantity' => 0,
                        'sort_order' => $index,
                        'options' => $options,
                        'is_active' => true,
                    ]);

                    $variant->dimensionValues()->attach($valueIds);

                    $variantData[] = [
                        'id' => $variant->id,
                        'label' => $label,
                        'sku' => $sku,
                        'options' => $options,
                    ];
                }
            }

            return ToolResult::success([
                'id' => $product->id,
                'uuid' => $product->uuid,
                'name' => $product->name,
                'slug' => $product->slug,
                'product_type' => $product->product_type,
                'price_cents' => $product->price_cents,
                'formatted_price' => $product->formatted_price,
                'status' => $product->status,
                'variant_count' => count($variantData),
                'variants' => $variantData,
                'message' => 'Produkt erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function generateCombinations(array $arrays): array
    {
        if (empty($arrays)) return [];

        $result = [[]];
        foreach ($arrays as $array) {
            $append = [];
            foreach ($result as $product) {
                foreach ($array as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }
            $result = $append;
        }

        return $result;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'shop', 'products', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
