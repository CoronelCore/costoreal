<?php

namespace App\Modules\Pricing\Data;

/** Presentation normalization also supports numeric policies saved by older clients. */
final class PricingData
{
    public static function policy(array $policy): array
    {
        return ['method' => $policy['method'], 'target' => (string) $policy['target'],
            'commission' => (string) ($policy['commission'] ?? '0'), 'quantum' => (int) ($policy['quantum'] ?? 1),
            'product_ids' => array_values(array_map('intval', $policy['product_ids']))];
    }

    public static function preview(array $result): array
    {
        return ['revision' => (int) $result['revision'], 'policy' => self::policy($result['policy']),
            'lines' => array_map(fn ($line) => ['id' => (int) $line['id'], 'name' => $line['name'], 'quantity' => (int) $line['quantity'],
                'cost' => (string) $line['cost'], 'previous_price' => $line['previous_price'] === null ? null : (string) $line['previous_price'],
                'price' => (string) $line['price'], 'revenue' => (string) $line['revenue'],
                'commission_amount' => (string) $line['commission_amount'], 'profit_after_commission' => (string) $line['profit_after_commission'],
                'margin_after_commission' => (string) $line['margin_after_commission'], 'markup_after_commission' => (string) $line['markup_after_commission']], $result['lines']),
            'totals' => array_map(fn ($value) => (string) $value, $result['totals'])];
    }
}
