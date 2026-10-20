/** Decimals cross HTTP as text; never convert amounts to JS numbers for arithmetic. */
export type Decimal = string;
export type Currency = 'CLP' | 'USD' | 'EUR' | 'CNY' | 'BRL' | 'PEN';
export type ImportState = 'activo' | 'finalizado' | 'archivado';
export type AllocationMethod = 'value' | 'units' | 'weight' | 'volume' | 'direct';
export type RateMode = 'estimated' | 'paid';
export interface RevisionMutation { expected_revision: number }
export interface NamedRecord { id: number; nombre: string }
export interface CompanySummary extends NamedRecord { color: string | null; icono: string | null }
export interface ConversionData {
    rate_mode: RateMode;
    applied_rate: Decimal | null;
    rate_source: string | null;
    rate_date: string | null;
}
export interface EditableProduct extends NamedRecord, ConversionData {
    moneda: Currency;
    cantidad: number;
    costo_unitario: Decimal;
    weight: Decimal | null;
    volume: Decimal | null;
}
export interface ProductData extends EditableProduct {
    subtotal_clp: Decimal;
    gastos_prorrateados_clp: Decimal;
    costo_final_total_clp: Decimal;
    costo_final_unitario_clp: Decimal;
    precio_venta: Decimal | null;
    document_id: number | null;
    resultado: ProductResult;
}
/** Per-unit CLP view computed by the server; profit fields stay null while the price is pending. */
export interface ProductResult {
    compra_unitaria_clp: Decimal;
    gastos_unitarios_clp: Decimal;
    costo_unitario_clp: Decimal;
    precio_venta: Decimal | null;
    ganancia_unitaria_clp: Decimal | null;
    ganancia_total_clp: Decimal | null;
    margen: Decimal | null;
}
export interface ExpenseData extends ConversionData {
    id: number;
    concepto: string;
    expense_category_id: number | null;
    category: NamedRecord | null;
    moneda: Currency;
    monto: Decimal;
    monto_clp: Decimal;
    allocation_method: AllocationMethod;
    target_product_id: number | null;
    document_id: number | null;
}
export interface DocumentData {
    id: number;
    filename: string;
    mime: string;
    size: number;
    kind: 'receipt' | 'product_image' | 'other';
    download_url: string;
    same_content_count: number;
}
export interface ImportConfiguration extends NamedRecord {
    revision: number;
    descripcion: string | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    moneda_base: 'CLP';
    moneda_trabajo: Currency;
    tipo_cambio: Decimal;
    estado: ImportState;
    company_id: number | null;
}
export interface ImportListItem extends ImportConfiguration {
    company: CompanySummary | null;
    total_productos_clp: Decimal;
    total_gastos_clp: Decimal;
    costo_total: Decimal;
    products_count: number;
    expenses_count: number;
    priced_count: number;
}
export interface ImportDetail extends ImportConfiguration {
    company: CompanySummary | null;
    costo_total: Decimal;
    products: ProductData[];
    expenses: ExpenseData[];
    documents: DocumentData[];
}
export interface FinancialStatistics {
    total_gastos_clp: Decimal;
    total_productos_clp: Decimal;
    total_gastos_prorrateados_clp: Decimal;
    gastos_sin_asignar_clp: Decimal;
    costo_total_final_clp: Decimal;
    costo_total: Decimal;
}
export interface SalesProjection {
    lines: number;
    priced_lines: number;
    units: number;
    priced_units: number;
    complete: boolean;
    covered_revenue: Decimal;
    covered_cost: Decimal;
    covered_profit: Decimal;
    revenue: Decimal | null;
    profit: Decimal | null;
    margin: Decimal | null;
}
export interface CostLine {
    id: number;
    quantity: number;
    purchase: Decimal;
    assigned: Decimal;
    total: Decimal;
    unit_cost: Decimal;
}
export interface Allocation {
    expense_id: number;
    product_id: number;
    amount: Decimal;
    method: AllocationMethod;
    trace?: AllocationTrace;
}
export interface AllocationTrace {
    version: 1; expense_amount: Decimal; basis: Decimal; basis_total: Decimal; floor: Decimal;
    remainder_numerator: Decimal; remainder_denominator: Decimal; priority: number; residual_pool: number; residual_award: 0 | 1;
}
export interface CostLineDetail extends CostLine {
    name: string; currency: Currency; rate_mode: RateMode; rate: Decimal | null; rate_source: string; rate_date: string | null;
}
export interface CostAllocationDetail extends Omit<Allocation, 'trace'> { name: string; trace: AllocationTrace | null }
export interface CostBreakdown {
    current_revision: number; run: CostRunSummary; line: CostLineDetail;
    totals: Omit<CostResult, 'lines' | 'allocations'>; allocations: Pagination<CostAllocationDetail>;
}
export interface CostResult {
    currency: 'CLP';
    algorithm: string;
    purchase: Decimal;
    expenses: Decimal;
    assigned: Decimal;
    unassigned: Decimal;
    total: Decimal;
    allocated_total: Decimal;
    lines: CostLine[];
    allocations: Allocation[];
}
export interface Pagination<T> {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
export interface ControlMetrics {
    count: number;
    active: number;
    purchase: Decimal;
    expenses: Decimal;
    total: Decimal;
    products: number;
    priced: number;
}
export interface ControlFilters {
    q?: string;
    state?: ImportState | '';
    sort?: 'latest' | 'cost' | 'name';
    from?: string;
    to?: string;
}
/** Browser drafts may contain incomplete values, unlike saved DTOs. */
export interface ProductDraft {
    nombre: string;
    moneda: Currency;
    cantidad: number | string;
    costo_unitario: string;
    weight: string;
    volume: string;
    rate_mode: RateMode;
    applied_rate: string;
    rate_source: string;
    rate_date: string;
}
export interface ProductMutation extends ProductDraft, RevisionMutation {}
export interface ProductRevision { revision: number; editable: boolean; product: EditableProduct }
export interface MutationReceipt { revision: number }
export interface ValidationFailure { errors?: Record<string, string | string[]> }
export interface PricingPolicy {
    method: 'margin' | 'markup'; target: Decimal; commission: Decimal; quantum: 1 | 10 | 100 | 1000; product_ids: number[];
}
export interface PricingBatch { revision: number; policy: PricingPolicy; lines_count: number }
export interface Actor { id: number; name: string }
export interface CostRunSummary { id: number; revision: number; reason: string; algorithm: string; created_at: string }
export interface AuditState { state: ImportState | null; reference_rate: Decimal | null; cost: Decimal | null; products: number; expenses: number; documents: number; deleted_at: string | null }
export interface AuditSummary { version: number; before: AuditState; after: AuditState }
export interface AuditEventSummary { id: number; revision: number; action: string; reason: string | null; summary: AuditSummary | null; actor: Actor | null; created_at: string }
export interface ScenarioSummary { id: number; name: string; base_revision: number; applied_revision: number | null; applied_at: string | null; actor: Actor | null; created_at: string }
export interface ImportShowProps {
    initialTab?: string | null;
    trip: ImportDetail;
    estadisticas: FinancialStatistics;
    projection: SalesProjection;
    categories: NamedRecord[];
    latestCostRun: CostRunSummary | null;
    legacyBaseline: import('./reconciliation').LegacyBaseline | null;
    history: Pagination<AuditEventSummary>;
    scenarios: Pagination<ScenarioSummary>;
    lastPricingBatch: PricingBatch | null;
    trashedDocuments: DocumentData[];
}

export interface ExpenseMutation extends RevisionMutation {
    expense_category_id: number | '';
    concepto: string;
    moneda: Currency;
    monto: string;
    rate_mode: RateMode;
    applied_rate: string;
    rate_source: string;
    rate_date: string;
    allocation_method: AllocationMethod;
    target_product_id: number | '';
    comprobante: File | null;
    eliminar_comprobante: boolean;
}
export interface DocumentMutation extends RevisionMutation { file: File | null }

export interface ImportReview { revision: number; state: ImportState; can_close: boolean; blocks: string[]; warnings: string[]; stored_total: Decimal | null; calculated_total: Decimal | null; differences_count: number; document_policy: 'optional' }

export interface CostRunHistory extends Pagination<CostRunSummary> { anchor: number | null; reason: 'close' | null; revision: number | null }
