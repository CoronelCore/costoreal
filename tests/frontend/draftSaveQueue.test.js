import test from 'node:test';
import assert from 'node:assert/strict';
import { DraftSaveQueue } from '../../resources/js/Utils/DraftSaveQueue.ts';

test('a no-change save cannot prevent the next edited draft from reaching the server',async()=>{
    const queue=new DraftSaveQueue();const stored=[];
    await queue.run(async()=>true);
    assert.equal(await queue.run(async()=>{stored.push('edited context');return true;}),true);
    assert.deepEqual(stored,['edited context']);
});
test('concurrent save and leave requests share one in-flight write and then accept the next revision',async()=>{
    const queue=new DraftSaveQueue();let release;let writes=0;
    const first=queue.run(async()=>{writes++;return new Promise(resolve=>{release=resolve;});});
    const concurrent=queue.run(async()=>{writes++;return true;});
    assert.equal(first,concurrent);
    await Promise.resolve();assert.equal(writes,1);release(true);await Promise.all([first,concurrent]);
    await queue.run(async()=>{writes++;return true;});assert.equal(writes,2);
});
test('a failed save releases the queue so corrected fields can be retried',async()=>{
    const queue=new DraftSaveQueue();
    assert.equal(await queue.run(async()=>false),false);
    assert.equal(await queue.run(async()=>true),true);
    await assert.rejects(queue.run(async()=>{throw new Error('connection failed');}),/connection failed/);
    assert.equal(await queue.run(async()=>true),true);
});
