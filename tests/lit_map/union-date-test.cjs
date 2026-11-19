const assert=require('node:assert/strict');const fs=require('node:fs');const M=require('../../web/modules/custom/lit_map/js/bubble-model.js');
const raw=JSON.parse(fs.readFileSync('web/modules/custom/lit_map/data/demo-10000.v1.json')).aggregate;
const slice=(records,groups)=>{const totals={},breakdown={};for(const r of records){const c=r.county_geoid,m=r.municipality||'_unspecified';((breakdown[c]??={})[m]??={})[r.language]=r.response_count;(totals[c]??={})[r.language]=(totals[c][r.language]||0)+r.response_count;}return {totals,breakdown,combinations:groups.map(g=>({county:g.county_geoid,municipality:g.municipality||'_unspecified',languages:g.languages,count:g.response_count}))};};
const data={schemaVersion:4,...slice(raw.records,raw.groups),days:[...new Set(raw.daily_groups.map(g=>g.day))].map(day=>({day,...slice(raw.daily_records.filter(r=>r.day===day),raw.daily_groups.filter(g=>g.day===day))}))};let checks=0;
for(const languages of [['en'],['en','es'],['en','es','cmn','ko'],['ase','yue']])for(const [from,through] of [['',''],['2026-01-01','2026-06-30'],['2026-11-01','2026-11-19']]){
 const result=M.combined(M.dated(data,{from,through}),languages);const counts={};for(const g of raw.daily_groups){if((from&&(!g.day||g.day<from))||(through&&(!g.day||g.day>through))||!g.languages.some(l=>languages.includes(l)))continue;const key=g.county_geoid+'/'+(g.municipality||'_unspecified');counts[key]=(counts[key]||0)+g.response_count;}
 for(const [county,areas] of Object.entries(result.breakdown)){for(const [town,n] of Object.entries(areas)){assert.equal(n,counts[county+'/'+town]||0);checks++;}assert.equal(result.totals[county],Object.values(areas).reduce((a,b)=>a+b,0));checks++;}
}
console.log(`PASS: ${checks} county/municipality language-union and date assertions.`);
