import test from 'node:test';
import assert from 'node:assert/strict';
import { creationContext, copyCreationPayload, captureDraftWrite, expenseMultipart } from '../../resources/js/Utils/creationDraft.ts';

test('an explicit unassigned company is preserved when the workspace company changes',()=>{
    assert.equal(creationContext(null,9).company_id,9);
    assert.equal(creationContext({company_id:null},9).company_id,'');
    assert.equal(creationContext({company_id:3},9).company_id,3);
    assert.equal(creationContext(null).tipo_cambio,'');
});
test('a retry retains its captured fields, original revision and decimal text after the editor changes',()=>{
    const payload = {context:creationContext({nombre:'Compra',tipo_cambio:'950.12345678'}),product:{mode:'add',id:null,base:null,fields:{nombre:'Sensor',moneda:'USD',cantidad:3,costo_unitario:'105.25',weight:'2.12345678',volume:'',rate_mode:'estimated',applied_rate:'',rate_source:'',rate_date:'',expected_revision:7}},expense:null};
    const write = captureDraftWrite('draft-id',2,'productos',payload,'write-key');
    payload.context.tipo_cambio='1000';payload.product.fields.weight='4';payload.product.fields.costo_unitario='110';
    assert.equal(write.payload.context.tipo_cambio,'950.12345678');
    assert.equal(write.payload.product.fields.costo_unitario,'105.25');
    assert.equal(write.payload.product.fields.weight,'2.12345678');
    assert.equal(write.payload.product.fields.cantidad,'3');
    assert.equal(write.payload.product.fields.expected_revision,7);
    assert.equal(write.expected_revision,2);
    assert.equal(write.write_key,'write-key');
});
test('expense draft keeps editor text, while multipart explicitly sends the file and method override',async()=>{
    const fields={expense_category_id:'',concepto:'Despacho',moneda:'USD',monto:'105.25',rate_mode:'paid',applied_rate:'940.12345678',rate_source:'Banco',rate_date:'2026-10-04',allocation_method:'direct',target_product_id:7,eliminar_comprobante:false,expected_revision:9};
    const file=new File(['%PDF QA'],'comprobante.pdf',{type:'application/pdf'});
    const payload={context:creationContext(null),product:null,expense:{id:4,fields,receipt_name:file.name}};
    const draft=copyCreationPayload(payload);
    fields.monto='1000';
    assert.equal(draft.expense.fields.monto,'105.25');
    assert.equal('comprobante' in draft.expense.fields,false);
    const body=expenseMultipart(draft.expense.fields,file,true);
    assert.equal(body.get('monto'),'105.25');
    assert.equal(body.get('applied_rate'),'940.12345678');
    assert.equal(body.get('eliminar_comprobante'),'0');
    assert.equal(body.get('_method'),'put');
    assert.equal(body.get('comprobante'),file);
    assert.equal(await body.get('comprobante').text(),'%PDF QA');
});
