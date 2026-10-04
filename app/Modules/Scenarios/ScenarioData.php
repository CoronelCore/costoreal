<?php

namespace App\Modules\Scenarios;

use App\Models\CostScenario;
use App\Modules\Pricing\Data\PricingData;

/** Read normalization never rewrites immutable scenario inputs, results or hashes. */
final class ScenarioData
{
    public static function summary(CostScenario $scenario): array
    {
        return ['id' => (int) $scenario->id, 'name' => $scenario->name, 'base_revision' => (int) $scenario->base_revision,
            'applied_revision' => $scenario->applied_revision, 'applied_at' => $scenario->applied_at?->toISOString(),
            'created_at' => $scenario->created_at->toISOString(), 'actor' => $scenario->actor?->only(['id', 'name'])];
    }

    public static function detail(CostScenario $scenario): array
    {
        return [...self::summary($scenario), 'input' => self::input($scenario->input), 'result' => self::result($scenario->result)];
    }

    public static function input(array $input): array
    {
        $normalized = [];
        foreach (['reference_rate', 'expense_variation_percent'] as $field) {
            if (isset($input[$field])) {
                $normalized[$field] = (string) $input[$field];
            }
        }
        if (array_key_exists('products', $input)) {
            $normalized['products'] = array_values(array_map(fn ($row) => self::change($row, ['cantidad'], ['costo_unitario', 'weight', 'volume', 'precio_venta']), $input['products']));
        }
        if (array_key_exists('expenses', $input)) {
            $normalized['expenses'] = array_values(array_map(function ($row) {
                $result = self::change($row, ['target_product_id'], ['monto']);
                if (isset($row['allocation_method'])) {
                    $result['allocation_method'] = $row['allocation_method'];
                }

                return $result;
            }, $input['expenses']));
        }
        if (array_key_exists('pricing', $input)) {
            $normalized['pricing'] = $input['pricing'] ? PricingData::policy($input['pricing']) : null;
        }

        return $normalized;
    }

    public static function result(array $result): array
    {
        return ['before' => self::state($result['before']), 'after' => self::state($result['after']),
            'cost_delta' => (string) $result['cost_delta'], 'paid_products_preserved' => (int) $result['paid_products_preserved'],
            'paid_expenses_preserved' => (int) $result['paid_expenses_preserved'],
            'pricing' => $result['pricing'] ? PricingData::preview($result['pricing']) : null,
            'lines' => array_map(fn ($line) => ['id' => (int) $line['id'], 'name' => $line['name'],
                'quantity_before' => (int) $line['quantity_before'], 'quantity_after' => (int) $line['quantity_after'],
                'cost_before' => (string) $line['cost_before'], 'cost_after' => (string) $line['cost_after'], 'cost_delta' => (string) $line['cost_delta'],
                'price_before' => $line['price_before'] === null ? null : (string) $line['price_before'],
                'price_after' => $line['price_after'] === null ? null : (string) $line['price_after']], $result['lines'])];
    }

    private static function state(array $state): array
    {
        $projection = $state['projection'];
        foreach (['covered_revenue', 'covered_cost', 'covered_profit', 'revenue', 'profit', 'margin'] as $field) {
            $projection[$field] = $projection[$field] === null ? null : (string) $projection[$field];
        }

        return ['reference_rate' => (string) $state['reference_rate'], 'cost' => (string) $state['cost'],
            'unassigned' => (string) $state['unassigned'], 'projection' => $projection];
    }

    private static function change(array $row, array $integers, array $decimals): array
    {
        $result = ['id' => (int) $row['id']];
        foreach ($integers as $field) {
            if (array_key_exists($field, $row)) {
                $result[$field] = $row[$field] === null ? null : (int) $row[$field];
            }
        }
        foreach ($decimals as $field) {
            if (array_key_exists($field, $row)) {
                $result[$field] = $row[$field] === null ? null : (string) $row[$field];
            }
        }

        return $result;
    }
}
