import test from 'node:test';
import assert from 'node:assert/strict';
import { formatMoney, formatDateOnly, formatRate, formatPesos } from '../../resources/js/Utils/format.ts';
test('decimal presentation preserves large amounts, signs and half-up cents', () => {
    assert.equal(formatMoney('9000000000000000.015', 2), '9.000.000.000.000.000,02');
    assert.equal(formatMoney('-1234.565',2), '-1.234,57');
    assert.equal(formatMoney('-0.004',2), '0,00');
    assert.equal(formatMoney('0.005',2), '0,01');
    assert.equal(formatMoney('450000.00'), '450.000');
    assert.equal(formatMoney(null), 'Sin dato');
    assert.equal(formatMoney(''), 'Sin dato');
});
test('exchange rates hide padded zeros and preserve all eight fractional places', () => {
    assert.equal(formatRate('900.00000000'), '900');
    assert.equal(formatRate('1250.25000000'), '1.250,25');
    assert.equal(formatRate('900.12345678'), '900,12345678');
    assert.equal(formatRate('0.00000001'), '0,00000001');
    assert.equal(formatRate('9000000000000000.12345678'), '9.000.000.000.000.000,12345678');
    assert.equal(formatRate(null), 'Sin dato');
});
test('per-unit pesos hide empty cents but keep real ones', () => {
    assert.equal(formatPesos('31500.00000000'), '31.500');
    assert.equal(formatPesos('40347.70000000'), '40.347,70');
    assert.equal(formatPesos('-5347.70000000'), '-5.347,70');
    assert.equal(formatPesos('0.004'), '0');
    assert.equal(formatPesos(null), 'Sin dato');
});
test('calendar date is stable across timezones including Chile', () => {
    for (const timezone of ['America/Santiago','UTC','America/Los_Angeles','Pacific/Auckland']) {
        process.env.TZ = timezone;
        assert.match(formatDateOnly('2026-10-03'), /^3 de octubre de 2026$/);
    }
});
