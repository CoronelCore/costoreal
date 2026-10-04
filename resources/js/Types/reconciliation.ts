import type { Decimal } from './financial';
export interface LegacyBaseline { id: number; revision: number; created_at: string }
export interface ReconciliationTotals { purchase: Decimal; expenses: Decimal; assigned: Decimal; unassigned: Decimal; total: Decimal; allocated_total: Decimal }
export type DifferenceClassification = 'conversion' | 'clp_rounding' | 'allocation' | 'line_total' | 'stored_inconsistency' | 'unit_precision';
export interface CostDifference { key: string; kind: 'products' | 'expenses'; id: number; name: string; field: string; before: Decimal; after: Decimal; delta: Decimal; classification: DifferenceClassification }
export interface CostComparison {
    id?: number; version: 1; revision: number; algorithm: string; fingerprint: string;
    before: ReconciliationTotals | null; after: ReconciliationTotals | null;
    differences: { data: CostDifference[]; total: number; current_page: number; last_page: number };
    warnings: { kind: 'products' | 'expenses'; id: number; message: string }[]; warnings_count: number; errors: string[]; applied_revision?: number | null;
}
export interface ComparisonSummary { id: number; base_revision: number; applied_revision: number | null; applied_at: string | null; created_at: string }
export interface ReconciliationApply { expected_revision: number; acknowledged_keys: string[] }
