import test from 'node:test';
import assert from 'node:assert/strict';
import { barWidths } from '../../resources/js/Utils/analytics.ts';
import { analyticsMoney } from '../../resources/js/Utils/format.ts';
test('analytical labels preserve cents and large negative amounts', () => {
    assert.equal(analyticsMoney('750.70'), '750,70');
    assert.equal(analyticsMoney('530012.00'), '530.012');
    assert.equal(analyticsMoney('-1800000000000000000.01'), '-1.800.000.000.000.000.000,01');
});
test('chart ratios preserve decimal proportions without converting monetary values to floats', () => {
    assert.deepEqual(barWidths(['1800000000000000000.00', '900000000000000000.00', '0']), [100, 50, 0]);
    assert.deepEqual(barWidths(['0.10', '-0.05', '0.01']), [100, 50, 10]);
    assert.deepEqual(barWidths(['0', '0.00']), [0, 0]);
});
