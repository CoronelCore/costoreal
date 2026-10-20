export function formatDateOnly(value: string | null | undefined, options: Intl.DateTimeFormatOptions = {}) {
    if (!value) return 'Sin fecha';
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    if (!match) return 'Fecha no válida';
    const [, year, month, day] = match;
    const date = new Date(Number(year), Number(month) - 1, Number(day), 12);
    return date.toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric', ...options });
}
export function formatMoney(value: string | number | null | undefined, decimals = 0) {
    if (value === null || value === undefined || value === '') return 'Sin dato';
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(String(value));
    if (!match || !Number.isInteger(decimals) || decimals < 0 || decimals > 8) return 'Sin dato';
    const [, sign, integer, fraction = ''] = match;
    const padded = fraction.padEnd(decimals + 1, '0');
    const quantum = 10n ** BigInt(decimals);
    let scaled = BigInt(integer) * quantum + BigInt(padded.slice(0, decimals) || '0');
    if (Number(padded[decimals]) >= 5) scaled += 1n;
    const whole = (scaled / quantum).toLocaleString('es-CL');
    const part = decimals ? ',' + (scaled % quantum).toString().padStart(decimals, '0') : '';
    return (sign && scaled !== 0n ? '-' : '') + whole + part;
}

/** Preserve fractional precision while hiding padded zeros in an exchange rate. */
export function formatRate(value: string | number | null | undefined) {
    const formatted = formatMoney(value, 8);
    return formatted.includes(',') ? formatted.replace(/0+$/, '').replace(/,$/, '') : formatted;
}

/** Per-unit pesos: cents only when the rounded amount has them ($31.500 but $40.347,70). */
export function formatPesos(value: string | number | null | undefined) {
    const formatted = formatMoney(value, 2);
    return formatted.endsWith(',00') ? formatted.slice(0, -3) : formatted;
}

/** Preserve legacy cents while keeping whole CLP compact. */
export const analyticsMoney = (value: string) => formatMoney(value, /\.\d*[1-9]/.test(value) ? 2 : 0);
