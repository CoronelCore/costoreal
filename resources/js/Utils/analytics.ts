/** Geometry only: exact fixed-point ratio avoids overflow/precision loss in large monetary strings. */
export function barWidths(values: string[]): number[] {
    const cents = values.map(value => {
        const match = /^-?(\d+)(?:\.(\d{1,2}))?$/.exec(value);
        if (!match) return 0n;
        return BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0'));
    });
    const maximum = cents.reduce((a, b) => a > b ? a : b, 0n);
    return cents.map(value => maximum === 0n ? 0 : Number(value * 1000n / maximum) / 10);
}
