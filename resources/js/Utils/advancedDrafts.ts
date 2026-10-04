import type { BulkPreviewMutation, BulkSettings, BulkSource, PricingMutation, PricingSettings, ScenarioExpenseChange, ScenarioExpenseDraft, ScenarioInput, ScenarioParameters, ScenarioPreviewMutation, ScenarioProductChange, ScenarioProductDraft } from '../Types/advanced';

/** Copy only the request contract; keep File objects and decimal text intact. */
export function bulkRequest(settings: BulkSettings, source: BulkSource, revision: number): BulkPreviewMutation {
    return { ...settings, mapping: { ...settings.mapping }, ...source, expected_revision: revision };
}

export function bulkMultipart(request: BulkPreviewMutation): FormData {
    const body = new FormData();
    body.append('delimiter', request.delimiter);
    body.append('has_header', request.has_header ? '1' : '0');
    body.append('default_currency', request.default_currency);
    body.append('expected_revision', String(request.expected_revision));
    for (const [field, column] of Object.entries(request.mapping)) body.append(`mapping[${field}]`, column === null ? '' : String(column));
    if (request.file) body.append('file', request.file);
    else body.append('source_text', request.source_text);
    return body;
}

export function pricingRequest(settings: PricingSettings, ids: number[], revision: number): PricingMutation {
    return { ...settings, product_ids: [...ids], expected_revision: revision };
}

export function scenarioProductChange(id: number, draft: ScenarioProductDraft, objective: boolean): ScenarioProductChange {
    return { id, cantidad: draft.cantidad, costo_unitario: draft.costo_unitario,
        weight: draft.weight === '' ? null : draft.weight, volume: draft.volume === '' ? null : draft.volume,
        ...(objective ? {} : { precio_venta: draft.precio_venta === '' ? null : draft.precio_venta }) };
}

export function scenarioExpenseChange(id: number, draft: ScenarioExpenseDraft, paid: boolean): ScenarioExpenseChange {
    return { id, ...(paid ? {} : { monto: draft.monto }), allocation_method: draft.allocation_method,
        target_product_id: draft.allocation_method === 'direct' && draft.target_product_id !== '' ? draft.target_product_id : null };
}

export function cloneScenarioInput(input: ScenarioInput): ScenarioInput {
    return { ...input, products: input.products?.map(row => ({ ...row })), expenses: input.expenses?.map(row => ({ ...row })),
        pricing: input.pricing ? { ...input.pricing, product_ids: [...input.pricing.product_ids] } : input.pricing };
}

export function scenarioRequest(parameters: ScenarioParameters, settings: PricingSettings | null, ids: number[], revision: number): ScenarioPreviewMutation {
    return { ...parameters, expected_revision: revision,
        products: parameters.products.map(row => {
            const copy = { ...row };
            if (settings) delete copy.precio_venta;
            return copy;
        }), expenses: parameters.expenses.map(row => ({ ...row })),
        pricing: settings ? { ...settings, product_ids: [...ids] } : null };
}
