<?php

namespace App\Support;

/**
 * Reads numbers as they are written in Chile before validation: comma decimals and dot thousands.
 * "1.050,5" → "1050.5", "450.000" → "450000", "175,5" → "175.5". A lone dot followed by other
 * than groups of three digits stays decimal ("175.5", "0.025"). Anything else is left untouched
 * so the validator reports it.
 */
final class ChileanNumber
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $text = str_replace([' ', "\u{00A0}", '$'], '', trim($value));
        if (preg_match('/^-?[1-9]\d{0,2}(\.\d{3})+(,\d+)?$/', $text)) {
            return str_replace(['.', ','], ['', '.'], $text);
        }
        if (preg_match('/^-?\d+,\d+$/', $text)) {
            return str_replace(',', '.', $text);
        }

        return $text === '' ? $value : $text;
    }

    /** Normalizes the given request fields in place, ignoring the ones that are absent. */
    public static function fields(array $input, array $fields): array
    {
        return array_map([self::class, 'normalize'], array_intersect_key($input, array_flip($fields)));
    }
}
