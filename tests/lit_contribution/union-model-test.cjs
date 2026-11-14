const assert=require('node:assert/strict'),fs=require('node:fs'),M=require('../../web/modules/custom/lit_map/js/bubble-model.js');
const data=JSON.parse(fs.readFileSync('private/union-qa/deterministic.json'));let n=0;const check=(v)=>{assert(v);n++;};
check(M.combined(data,['en']).totals['34003']===100);check(M.combined(data,['es']).totals['34003']===60);check(M.combined(data,['en','es']).totals['34003']===120);check(M.combined(data,['en','es','ko']).totals['34003']===130);check(M.combined(data,['ar']).totals['34003']===0);check(M.combined(data,['hi']).totals['34003']===undefined);
check(M.combined(data,['en','es']).breakdown['34003']._unspecified===15);check(M.combined(data,['en','es']).breakdown['34003'].m3400300700===105);check(Object.keys(M.combined(data,[]).totals).length===0);
const s=M.read('https://example.test/?lit_lang=en&lit_lang=es&lit_active=es&lit_county=34003&campaign=qa',data,true);check(s.active==='');const u=M.write('https://example.test/?lit_active=es&campaign=qa',s);check(!u.searchParams.has('lit_active')&&u.searchParams.get('campaign')==='qa');assert.deepEqual(M.read(u,data,true),s);n++;
console.log(n+' shared union model/state checks passed.');
