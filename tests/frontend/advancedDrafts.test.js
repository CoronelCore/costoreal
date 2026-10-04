import test from 'node:test';
import assert from 'node:assert/strict';
import { bulkRequest, bulkMultipart, pricingRequest, scenarioProductChange, scenarioExpenseChange, cloneScenarioInput, scenarioRequest } from '../../resources/js/Utils/advancedDrafts.ts';
import { validationErrors, requestErrors, formFieldErrors } from '../../resources/js/Utils/validation.ts';

test('file previews keep their file, column mapping and decimal text after the editor changes', async () => {
    const settings = { delimiter: ';', has_header: true, default_currency: 'USD', mapping: { nombre: 0, cantidad: 1, moneda: null, costo_unitario: 2, weight: 3, volume: null } };
    const file = new File(['Sensor;2;10.25;2.12345678'], 'productos.csv', { type: 'text/csv' });
    const request = bulkRequest(settings, { file }, 10);
    settings.mapping.weight = null;
    settings.has_header = false;
    const body = bulkMultipart(request);
    assert.equal(body.get('file'), file);
    assert.equal(await body.get('file').text(), 'Sensor;2;10.25;2.12345678');
    assert.equal(body.get('mapping[weight]'), '3');
    assert.equal(body.get('mapping[volume]'), '');
    assert.equal(body.get('has_header'), '1');
    assert.equal(body.get('expected_revision'), '10');
    assert.equal(body.has('source_text'), false);
});

test('preview price selections and saved scenario copies cannot alias live editor state', () => {
    const ids = [3, 9];
    const settings = { method: 'margin', target: '20.01', commission: '1.25', quantum: 10 };
    const request = pricingRequest(settings, ids, 10);
    ids.splice(0, 1);
    settings.target = '50';
    assert.deepEqual(request.product_ids, [3, 9]);
    assert.equal(request.target, '20.01');
    const saved = { reference_rate: '950.12345678', products: [{ id: 3, weight: '2.12345678', precio_venta: null }], expenses: [{ id: 7, monto: '10.25' }], pricing: request };
    const copy = cloneScenarioInput(saved);
    copy.products[0].weight = '9';
    copy.expenses[0].monto = '100';
    copy.pricing.product_ids.push(99);
    assert.equal(saved.products[0].weight, '2.12345678');
    assert.equal(saved.expenses[0].monto, '10.25');
    assert.deepEqual(saved.pricing.product_ids, [3, 9]);
});

test('objective simulation preserves the draft while omitting manual prices and paid amounts', () => {
    const draft = { cantidad: 3, costo_unitario: '105.25', weight: '2.12345678', volume: '', precio_venta: '140625' };
    const product = scenarioProductChange(3, draft, false);
    const expense = scenarioExpenseChange(7, { monto: '100.25', allocation_method: 'weight', target_product_id: 3 }, true);
    const parameters = { reference_rate: '950.12345678', expense_variation_percent: '20.01', products: [product], expenses: [expense] };
    const request = scenarioRequest(parameters, { method: 'margin', target: '20', commission: '0', quantum: 1 }, [3], 10);
    parameters.products[0].weight = '5';
    parameters.expenses[0].allocation_method = 'value';
    assert.equal(request.reference_rate, '950.12345678');
    assert.equal(request.products[0].weight, '2.12345678');
    assert.equal(request.products[0].volume, null);
    assert.equal('precio_venta' in request.products[0], false);
    assert.equal(draft.precio_venta, '140625');
    assert.equal('monto' in request.expenses[0], false);
    assert.equal(request.expenses[0].allocation_method, 'weight');
    assert.equal(request.expenses[0].target_product_id, null);
    assert.equal(scenarioProductChange(3, { ...draft, precio_venta: '' }, false).precio_venta, null);
});

test('validation boundaries reject malformed errors and keep actionable server messages', () => {
    assert.deepEqual(validationErrors({ conflict: 'Recarga la revisión', weight: ['Falta el peso'], malformed: { text: 'bad' }, mixed: ['a', 1], numeric: 42 }), { conflict: 'Recarga la revisión', weight: ['Falta el peso'] });
    assert.deepEqual(requestErrors({ isAxiosError: true, response: { data: { errors: { conflict: ['Revisión obsoleta'] } } } }, 'import', 'Error de conexión'), { conflict: ['Revisión obsoleta'] });
    assert.deepEqual(requestErrors(new Error('offline'), 'import', 'Error de conexión'), { import: 'Error de conexión' });
    assert.deepEqual(validationErrors(null), {});
});

test('JSON and Inertia field errors render the same message without array notation', () => {
    const text = 'Selecciona el producto que recibe este gasto.';
    const json = requestErrors({ isAxiosError: true, response: { data: { errors: { target_product_id: [text], conflict: ['La revisión cambió.', 'Recarga antes de guardar.'] } } } }, 'save', 'Error de conexión');
    assert.deepEqual(formFieldErrors(json), { target_product_id: text, conflict: 'La revisión cambió. Recarga antes de guardar.' });
    assert.deepEqual(formFieldErrors({ target_product_id: text }), { target_product_id: text });
});
