<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjShopOrder;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListShopOrdersTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam, HasStandardGetOperations;

    public function getName(): string
    {
        return 'syltjunkie.shop_orders.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/shop/orders - Listet Shop-Bestellungen. Filter nach status. Unterstützt search/sort/limit/offset.';
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
                    'enum' => ['pending', 'paid', 'shipped', 'completed', 'cancelled', 'refunded'],
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

            $query = SjShopOrder::where('team_id', $rootTeamId)->with('items');

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            $this->applyStandardSearch($query, $arguments, ['order_number', 'customer_name', 'customer_email']);
            $this->applyStandardSort($query, $arguments, 'created_at', 'desc');

            return $this->applyStandardPaginationResult($query, $arguments, function ($order) {
                return [
                    'id' => $order->id,
                    'uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'total_cents' => $order->total_cents,
                    'formatted_total' => $order->formatted_total,
                    'item_count' => $order->items->count(),
                    'paid_at' => $order->paid_at?->toIso8601String(),
                    'created_at' => $order->created_at->toIso8601String(),
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
            'tags' => ['syltjunkie', 'shop', 'orders', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
