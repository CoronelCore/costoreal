import type { AllocationMethod, AuditSummary, Currency, Decimal, PricingPolicy, RevisionMutation, SalesProjection, ScenarioSummary } from './financial';

export type ErrorBag = Record<string, string | string[]>;
export type PricingSettings = Omit<PricingPolicy, 'product_ids'>;
export interface PricingMutation extends PricingPolicy, RevisionMutation {}
export interface PriceSuggestion {
    id: number; name: string; quantity: number; cost: Decimal; previous_price: Decimal | null;
    price: Decimal; revenue: Decimal; commission_amount: Decimal;
    profit_after_commission: Decimal; margin_after_commission: Decimal; markup_after_commission: Decimal;
}
export interface PricingPreview {
    revision: number; policy: PricingPolicy; lines: PriceSuggestion[];
    totals: { revenue: Decimal; commission: Decimal; profit_after_commission: Decimal };
}
export type ImportColumn = 'nombre' | 'cantidad' | 'moneda' | 'costo_unitario' | 'weight' | 'volume';
export interface BulkSettings {
    delimiter: 'tab' | ',' | ';'; has_header: boolean; default_currency: Currency;
    mapping: Record<ImportColumn, number | null>;
}
export type BulkSource = { file: File; source_text?: never } | { source_text: string; file?: never };
export type BulkPreviewMutation = BulkSettings & RevisionMutation & BulkSource;
export type BulkApplyMutation = BulkPreviewMutation & { idempotency_key: string };
/** Invalid preview rows deliberately retain the user's original text. */
export interface BulkRow {
    row: number;
    data: { nombre: string | null; cantidad: string | number | null; moneda: string; costo_unitario: string | null; weight: string | null; volume: string | null };
    errors: ErrorBag;
}
export interface BulkPreview {
    revision: number; columns: { index: number; label: string }[]; rows: BulkRow[];
    count: number; invalid_count: number; cost_errors: ErrorBag; can_apply: boolean;
    totals: { purchase: Decimal; expenses: Decimal; total: Decimal; unassigned: Decimal } | null;
}
export interface ScenarioProductChange {
    id: number; cantidad?: number | string; costo_unitario?: Decimal;
    weight?: Decimal | null; volume?: Decimal | null; precio_venta?: Decimal | null;
}
export interface ScenarioExpenseChange {
    id: number; monto?: Decimal; allocation_method?: AllocationMethod; target_product_id?: number | null;
}
export interface ScenarioInput {
    reference_rate?: Decimal; expense_variation_percent?: Decimal;
    products?: ScenarioProductChange[]; expenses?: ScenarioExpenseChange[]; pricing?: PricingPolicy | null;
}
export interface ScenarioParameters {
    reference_rate: Decimal; expense_variation_percent: Decimal;
    products: ScenarioProductChange[]; expenses: ScenarioExpenseChange[];
}
export interface ScenarioPreviewMutation extends ScenarioInput, RevisionMutation {}
export interface ScenarioSaveMutation extends ScenarioPreviewMutation { name: string; idempotency_key: string }
export interface ScenarioProductDraft {
    cantidad: number | string; costo_unitario: string; weight: string; volume: string; precio_venta: string;
}
export interface ScenarioExpenseDraft { monto: string; allocation_method: AllocationMethod; target_product_id: number | '' }
export interface ScenarioState { reference_rate: Decimal; cost: Decimal; unassigned: Decimal; projection: SalesProjection }
export interface ScenarioLine {
    id: number; name: string; quantity_before: number; quantity_after: number;
    cost_before: Decimal; cost_after: Decimal; cost_delta: Decimal;
    price_before: Decimal | null; price_after: Decimal | null;
}
export interface ScenarioResult {
    before: ScenarioState; after: ScenarioState; cost_delta: Decimal; lines: ScenarioLine[];
    pricing: PricingPreview | null; paid_expenses_preserved: number; paid_products_preserved: number;
}
export interface SavedScenario extends ScenarioSummary { input: ScenarioInput; result: ScenarioResult }
export interface ScenarioPreview { revision: number; result: ScenarioResult }
export interface ScenarioResponse { scenario: SavedScenario }
export interface ScenarioComparison extends ScenarioPreview { scenario: SavedScenario | null }
/** Historical scalar values retain their original representation. */
export type AuditValue = string | number | boolean | null;
export interface AuditDifference {
    kind: 'trip' | 'products' | 'expenses' | 'documents'; id: number | null; name: string;
    change: 'added' | 'removed' | 'updated'; fields: { field: string; before: AuditValue; after: AuditValue }[];
}
export interface AuditComparison {
    id: number; revision: number; reason: string | null; summary: AuditSummary | null;
    data: AuditDifference[]; total: number; current_page: number; last_page: number;
}
