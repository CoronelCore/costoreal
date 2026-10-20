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

/**
 * Same reading as the server (App\Support\ChileanNumber): comma decimals and dot thousands.
 * "1.050,5" → "1050.5", "450.000" → "450000", "175,5" → "175.5", "175.5" → "175.5".
 * Returns null when the text is empty or is not a number, so the field shows no reading.
 */
export function parseChileanDecimal(text: string | number | null | undefined): string | null {
    if (text === null || text === undefined) return null;
    const value = String(text).replace(/[\s$]/g, '');
    if (/^-?[1-9]\d{0,2}(\.\d{3})+(,\d+)?$/.test(value)) return value.replace(/\./g, '').replace(',', '.');
    if (/^-?\d+,\d+$/.test(value)) return value.replace(',', '.');
    return /^-?\d+(\.\d+)?$/.test(value) ? value : null;
}

/** Prefills an input with a stored decimal without padded zeros: "900.00000000" → "900", "950.12" → "950,12". */
export function toInputDecimal(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') return '';
    const match = /^(-?\d+)(?:\.(\d*?))?0*$/.exec(String(value));
    if (!match) return String(value);
    return match[2] ? `${match[1]},${match[2]}` : match[1];
}

/** How the app reads what was typed, in the same format it shows amounts: "1.050,5". */
export function readDecimal(text: string | number | null | undefined): string {
    const parsed = parseChileanDecimal(text);
    return parsed === null ? '' : formatRate(parsed);
}

/** Generous CLP ranges per unit; outside them the rate is probably mistyped (1,05 instead of 1.050). */
const RATE_RANGES: Record<string, [number, number]> = { USD: [300, 3000], EUR: [300, 3500], BRL: [40, 600], PEN: [80, 900], CNY: [40, 500] };

export function rateWarning(currency: string, text: string | number | null | undefined): string {
    const parsed = parseChileanDecimal(text);
    const range = RATE_RANGES[currency];
    if (parsed === null || !range || Number(parsed) <= 0) return '';
    if (Number(parsed) < range[0]) return `Ojo: ${formatRate(parsed)} pesos por 1 ${currency} parece muy bajo. Revisa la tasa.`;
    if (Number(parsed) > range[1]) return `Ojo: ${formatRate(parsed)} pesos por 1 ${currency} parece muy alto. Revisa la tasa.`;
    return '';
}
