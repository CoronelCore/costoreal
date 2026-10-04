import type { ProductMutation, ProductDraft, ExpenseMutation, ImportDetail, FinancialStatistics, SalesProjection, NamedRecord, CostRunSummary, Pagination } from './financial';
export type CreationStep = 'contexto' | 'productos' | 'gastos' | 'revision';
export interface CreationContext { nombre: string; descripcion: string; company_id: number | ''; fecha_inicio: string; fecha_fin: string; moneda_trabajo: 'USD' | 'EUR' | 'CNY' | 'BRL' | 'PEN'; tipo_cambio: string }
export interface ProductEditorDraft { mode: 'add' | 'edit'; id: number | null; fields: ProductMutation; base: ProductDraft | null }
export interface ExpenseEditorDraft { id: number | null; fields: Omit<ExpenseMutation, 'comprobante'>; receipt_name: string }
export interface CreationPayload { context: CreationContext; product: ProductEditorDraft | null; expense: ExpenseEditorDraft | null }
export interface CreationDraft { id: string; revision: number; trip_id: number | null; step: CreationStep; payload: CreationPayload; updated_at: string; completed_at: string | null }
export interface CreationSummary { id: string; trip_id: number | null; name: string; step: CreationStep; updated_at: string }
export interface CreationProps { draft: CreationDraft | null; drafts: Pagination<CreationSummary>; trip: ImportDetail | null; estadisticas: FinancialStatistics | null; projection: SalesProjection | null; companies: NamedRecord[]; selectedCompanyId: number | null; categories: NamedRecord[]; latestCostRun: CostRunSummary | null }
export interface DraftWrite { draft_id: string; expected_revision: number; step: CreationStep; write_key: string; payload: CreationPayload }
