const assert=require('node:assert/strict'),M=require('../../web/modules/custom/lit_map/js/bubble-model.js');
assert.deepEqual(M.positiveLanguages(['en','es','ko','pt'],{en:2,es:0,ko:1}),['en','ko']);
assert.equal(M.colorMode(['en']),'solid');assert.equal(M.colorMode(['en','es']),'segments');assert.equal(M.colorMode(['en','es','ko']),'segments');assert.equal(M.colorMode(['en','es','ko','pt','ar','hi','ase']),'many');
assert.equal(M.color('en'),M.color('en'));assert.notEqual(M.color('en'),M.color('es'));assert.equal(M.colorMode([]),'none');
console.log('8 categorical color model checks passed.');
