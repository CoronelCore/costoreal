import test from 'node:test';
import assert from 'node:assert/strict';
import { parseChileanDecimal, toInputDecimal, readDecimal, rateWarning, trimDecimal } from '../../resources/js/Utils/format.ts';
test('numeric inputs drop padded zeros but keep the dot', () => {
    assert.equal(trimDecimal('900.00000000'), '900');
    assert.equal(trimDecimal('950.12000000'), '950.12');
    assert.equal(trimDecimal('1000'), '1000');
});
test('numbers typed the Chilean way read like the server reads them', () => {
    assert.equal(parseChileanDecimal('450.000'), '450000');
    assert.equal(parseChileanDecimal('1.050,5'), '1050.5');
    assert.equal(parseChileanDecimal('12.345.678,90'), '12345678.90');
    assert.equal(parseChileanDecimal('175,5'), '175.5');
    assert.equal(parseChileanDecimal('$ 12.990'), '12990');
    assert.equal(parseChileanDecimal('175.5'), '175.5');
    assert.equal(parseChileanDecimal('0.025'), '0.025');
    assert.equal(parseChileanDecimal('950.12345678'), '950.12345678');
    assert.equal(parseChileanDecimal('1.2.3'), null);
    assert.equal(parseChileanDecimal(''), null);
});
test('stored decimals prefill inputs without padded zeros', () => {
    assert.equal(toInputDecimal('900.00000000'), '900');
    assert.equal(toInputDecimal('950.12000000'), '950,12');
    assert.equal(toInputDecimal('1000'), '1000');
    assert.equal(toInputDecimal(null), '');
});
test('the reading and the rate warning catch a mistyped exchange rate', () => {
    assert.equal(readDecimal('1.050'), '1.050');
    assert.equal(readDecimal('1.05'), '1,05');
    assert.match(rateWarning('USD', '1.05'), /muy bajo/);
    assert.equal(rateWarning('USD', '1.050'), '');
    assert.equal(rateWarning('BRL', '175,5'), '');
    assert.match(rateWarning('BRL', '17.550'), /muy alto/);
    assert.equal(rateWarning('USD', ''), '');
});
