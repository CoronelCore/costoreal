import test from 'node:test';
import assert from 'node:assert/strict';
import { compareProductDraft, mergeProductDraft, productDraft, sameProductValue } from '../../resources/js/Utils/productConflict.js';
const base = productDraft({nombre:'Equipo',moneda:'USD',cantidad:1,costo_unitario:'100.00',rate_mode:'estimated',weight:null});
test('disjoint draft and server edits merge without losing either change',()=>{
    const draft = {...base,cantidad:2};
    const current = {...base,weight:'3.00000000'};
    const changes = compareProductDraft(base,draft,current);
    assert.equal(changes.filter(row=>row.conflict).length,0);
    assert.deepEqual(mergeProductDraft(base,draft,current),{...current,cantidad:2});
});
test('conflicting financial edits require an explicit field decision',()=>{
    const draft = {...base,costo_unitario:'110.00'};
    const current = {...base,costo_unitario:'120.00',nombre:'Equipo revisado'};
    assert.throws(()=>mergeProductDraft(base,draft,current),/Selecciona/);
    assert.equal(mergeProductDraft(base,draft,current,{costo_unitario:'draft'}).costo_unitario,'110.00');
    assert.equal(mergeProductDraft(base,draft,current,{costo_unitario:'current'}).costo_unitario,'120.00');
    assert.equal(mergeProductDraft(base,draft,current,{costo_unitario:'draft'}).nombre,'Equipo revisado');
});
test('decimal comparisons preserve eight digits and ignore presentation zeroes',()=>{
    assert.equal(sameProductValue('costo_unitario','100.00',100),true);
    assert.equal(sameProductValue('weight',null,''),true);
    assert.equal(sameProductValue('weight',null,'0'),false);
    assert.equal(sameProductValue('applied_rate','900.12345678','900.12345679'),false);
    assert.equal(sameProductValue('applied_rate','999999.99999999','999999.99999998'),false);
    assert.equal(compareProductDraft(base,{...base,costo_unitario:100},base).length,0);
});
