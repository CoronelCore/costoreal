import type { EditableProduct, ProductDraft } from '../Types/financial';

export const productFields = ['nombre','moneda','cantidad','costo_unitario','weight','volume','rate_mode','applied_rate','rate_source','rate_date'] as const;
export type ProductField = typeof productFields[number];
export type ConflictChoices = Partial<Record<ProductField, 'draft' | 'current'>>;
type Source = ProductDraft | EditableProduct;
export interface ProductComparison {
    field: ProductField; base: string | number | null; draft: string | number | null; current: string | number | null;
    localChanged: boolean; remoteChanged: boolean; conflict: boolean;
}
const decimals = new Set(['cantidad','costo_unitario','weight','volume','applied_rate']);
export function productDraft(product: Source): ProductDraft {
    return {
        nombre: product.nombre, moneda: product.moneda, cantidad: product.cantidad, costo_unitario: product.costo_unitario,
        weight: product.weight ?? '', volume: product.volume ?? '', rate_mode: product.rate_mode,
        applied_rate: product.applied_rate ?? '', rate_source: product.rate_source ?? '', rate_date: product.rate_date ?? '',
    };
}
export function sameProductValue(field: ProductField, left: string | number | null | undefined, right: string | number | null | undefined) {
    const canonical = (value: string | number | null | undefined) => {
        if (value === null || value === undefined || value === '') return null;
        const text = String(value);
        if (!decimals.has(field) || !/^[+-]?\d+(\.\d+)?$/.test(text)) return text;
        const negative = text.startsWith('-');
        const [whole,fraction = ''] = text.replace(/^[+-]/,'').split('.');
        const integer = whole.replace(/^0+(?=\d)/,'');
        const tail = fraction.replace(/0+$/,'');
        const zero = integer === '0' && tail === '';
        return (negative && !zero ? '-' : '')+integer+(tail ? '.'+tail : '');
    };
    return canonical(left) === canonical(right);
}
export function compareProductDraft(base: Source, draft: ProductDraft, current: Source): ProductComparison[] {
    return productFields.map(field=>{
        const localChanged = !sameProductValue(field,base[field],draft[field]);
        const remoteChanged = !sameProductValue(field,base[field],current[field]);
        return {field,base:base[field],draft:draft[field],current:current[field],localChanged,remoteChanged,
            conflict:localChanged && remoteChanged && !sameProductValue(field,draft[field],current[field])};
    }).filter(row=>row.localChanged || row.remoteChanged);
}
export function mergeProductDraft(base: Source, draft: ProductDraft, current: Source, choices: ConflictChoices = {}): ProductDraft {
    const rows = compareProductDraft(base,draft,current);
    const unresolved = rows.filter(row=>row.conflict && choices[row.field] !== 'draft' && choices[row.field] !== 'current');
    if (unresolved.length) throw new Error('Selecciona el valor de cada campo en conflicto.');
    const merged = productDraft(current);
    for (const row of rows) {
        if (row.localChanged && (!row.conflict || choices[row.field] === 'draft')) Object.assign(merged, { [row.field]: draft[row.field] });
    }
    return merged;
}
