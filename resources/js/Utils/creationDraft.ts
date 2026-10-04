import type { CreationContext, CreationPayload, DraftWrite, CreationStep, ProductEditorDraft, ExpenseEditorDraft } from '../Types/creation';

/** Explicit copies retain decimal text and never serialize a File or live form methods. */
export function creationContext(source: Partial<CreationContext> | null, companyId: number | null = null): CreationContext {
    return { nombre:source?.nombre ?? '',descripcion:source?.descripcion ?? '',company_id:source ? source.company_id ?? '' : companyId ?? '',fecha_inicio:source?.fecha_inicio ?? '',fecha_fin:source?.fecha_fin ?? '',moneda_trabajo:source?.moneda_trabajo ?? 'USD',tipo_cambio:source?.tipo_cambio ?? '' };
}
const productFields = (fields: ProductEditorDraft['fields']) => ({nombre:fields.nombre ?? '',moneda:fields.moneda,cantidad:String(fields.cantidad ?? ''),costo_unitario:fields.costo_unitario ?? '',weight:fields.weight ?? '',volume:fields.volume ?? '',rate_mode:fields.rate_mode,applied_rate:fields.applied_rate ?? '',rate_source:fields.rate_source ?? '',rate_date:fields.rate_date ?? ''});
export function copyCreationPayload(source: CreationPayload): CreationPayload {
    const p = source.product, e = source.expense;
    return { context:creationContext(source.context), product:p ? {mode:p.mode,id:p.id,fields:{...productFields(p.fields),expected_revision:p.fields.expected_revision},base:p.base ? {...productFields({...p.base,expected_revision:p.fields.expected_revision})} : null} : null,
        expense:e ? {id:e.id,receipt_name:e.receipt_name ?? '',fields:{expense_category_id:e.fields.expense_category_id ?? '',concepto:e.fields.concepto ?? '',moneda:e.fields.moneda,monto:e.fields.monto ?? '',rate_mode:e.fields.rate_mode,applied_rate:e.fields.applied_rate ?? '',rate_source:e.fields.rate_source ?? '',rate_date:e.fields.rate_date ?? '',allocation_method:e.fields.allocation_method,target_product_id:e.fields.target_product_id ?? '',eliminar_comprobante:e.fields.eliminar_comprobante ?? false,expected_revision:e.fields.expected_revision}} : null };
}
export function captureDraftWrite(id: string, revision: number, step: CreationStep, payload: CreationPayload, writeKey: string): DraftWrite {
    return {draft_id:id,expected_revision:revision,step,write_key:writeKey,payload:copyCreationPayload(payload)};
}
export function expenseMultipart(fields: ExpenseEditorDraft['fields'], file: File | null, editing: boolean): FormData {
    const body = new FormData();
    for (const [key,value] of Object.entries(fields)) body.append(key,value === null ? '' : typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
    if (file) body.append('comprobante',file);
    if (editing) body.append('_method','put');
    return body;
}
