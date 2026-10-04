import test from 'node:test';
import assert from 'node:assert/strict';
import { formatMoney, formatDateOnly } from '../../resources/js/Utils/format.js';
test('decimal presentation preserves large amounts, signs and half-up cents', () => {
    assert.equal(formatMoney('9000000000000000.015', 2), '9.000.000.000.000.000,02');
    assert.equal(formatMoney('-1234.565',2), '-1.234,57');
    assert.equal(formatMoney('-0.004',2), '0,00');
    assert.equal(formatMoney('0.005',2), '0,01');
    assert.equal(formatMoney('450000.00'), '450.000');
    assert.equal(formatMoney(null), 'Sin dato');
    assert.equal(formatMoney(''), 'Sin dato');
});
test('calendar date is stable across timezones including Chile', () => {
    for (const timezone of ['America/Santiago','UTC','America/Los_Angeles','Pacific/Auckland']) {
        process.env.TZ = timezone;
        assert.match(formatDateOnly('2026-10-03'), /^3 de octubre de 2026$/);
    }
});
