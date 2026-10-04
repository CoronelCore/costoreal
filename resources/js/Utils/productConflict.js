export const productFields = ['nombre','moneda','cantidad','costo_unitario','weight','volume','rate_mode','applied_rate','rate_source','rate_date'];
const decimals = new Set(['cantidad','costo_unitario','weight','volume','applied_rate']);
export function productDraft(product) {
    return Object.fromEntries(productFields.map(field=>[field,product[field] ?? '']));
}
export function sameProductValue(field,left,right) {
    const canonical = value => {
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
export function compareProductDraft(base,draft,current) {
    return productFields.map(field=>{
        const localChanged = !sameProductValue(field,base[field],draft[field]);
        const remoteChanged = !sameProductValue(field,base[field],current[field]);
        return {field,base:base[field],draft:draft[field],current:current[field],localChanged,remoteChanged,
            conflict:localChanged && remoteChanged && !sameProductValue(field,draft[field],current[field])};
    }).filter(row=>row.localChanged || row.remoteChanged);
}
export function mergeProductDraft(base,draft,current,choices = {}) {
    const rows = compareProductDraft(base,draft,current);
    const unresolved = rows.filter(row=>row.conflict && !['draft','current'].includes(choices[row.field]));
    if (unresolved.length) throw new Error('Selecciona el valor de cada campo en conflicto.');
    const merged = productDraft(current);
    for (const row of rows) {
        if (row.localChanged && (!row.conflict || choices[row.field] === 'draft')) merged[row.field] = draft[row.field];
    }
    return merged;
}
