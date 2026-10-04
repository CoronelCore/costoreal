export function formatDateOnly(value, options = {}) {
    if (!value) return 'Sin fecha';
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    if (!match) return 'Fecha no válida';
    const [, year, month, day] = match;
    const date = new Date(Number(year), Number(month) - 1, Number(day), 12);
    return date.toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric', ...options });
}
export function formatMoney(value, decimals = 0) {
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
