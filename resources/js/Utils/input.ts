/** Read DOM inputs without coercing exact decimal text to a floating point value. */
export function inputValue(event: Event): string {
    const target = event.target;
    return target instanceof HTMLInputElement || target instanceof HTMLSelectElement ? target.value : '';
}

export function inputFile(event: Event): File | null {
    return event.target instanceof HTMLInputElement ? event.target.files?.[0] ?? null : null;
}
