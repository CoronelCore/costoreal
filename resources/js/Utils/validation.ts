import axios from 'axios';
import type { ErrorBag } from '../Types/advanced';
import type { ValidationFailure } from '../Types/financial';

/** Inertia also carries application errors such as conflict, outside form field names. */
export function validationErrors(value: unknown): ErrorBag {
    if (!value || typeof value !== 'object') return {};
    return Object.fromEntries(Object.entries(value).filter((entry): entry is [string, string | string[]] =>
        typeof entry[1] === 'string' || (Array.isArray(entry[1]) && entry[1].every(message => typeof message === 'string'))));
}

export function requestErrors(failure: unknown, key: string, message: string): ErrorBag {
    const errors = axios.isAxiosError<ValidationFailure>(failure) ? validationErrors(failure.response?.data?.errors) : {};
    return Object.keys(errors).length ? errors : { [key]: message };
}
