/** Pure state, scale and camera operations, shared with development tests. */
(function (root, factory) {
  const model = factory();
  if (typeof module === 'object' && module.exports) module.exports = model;
  else root.LitBubbleModel = model;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  'use strict';
  const keys = ['lit_lang', 'lit_active', 'lit_county', 'lit_municipality', 'lit_breakdown', 'lit_level', 'lit_from', 'lit_through'];
  function normalize(input, data) {
    const languages = data.languages.map(l => l.id).filter(id => (input.languages || []).includes(id));
    let county = data.counties.some(c => c.geoid === input.county) ? input.county : '';
    let breakdown = Boolean(input.breakdown && county && data.municipalitiesEnabled !== false);
    let municipality = breakdown && (input.municipality === '_unspecified' || data.municipalities.some(m => m.id === input.municipality && m.county === county)) ? input.municipality : '';
    let level = ['usa', 'nj', 'county', 'municipality'].includes(input.level) ? input.level : (county ? (breakdown ? 'municipality' : 'county') : 'nj');
    if (level === 'usa' || level === 'nj') { county = ''; breakdown = false; municipality = ''; }
    else level = county ? (breakdown ? 'municipality' : 'county') : 'nj';
    const from=input.from||'',through=input.through||'';const validDate=d=>!d || /^\d{4}-\d{2}-\d{2}$/.test(d)&&!isNaN(Date.parse(d))&&new Date(d+'T12:00:00Z').toISOString().slice(0,10)===d;
    const dateError=!validDate(from)||!validDate(through)||Boolean(from&&through&&from>through);
    return {from,through,dateError,languages, active: data.schemaVersion>=3 ? '' : (languages.includes(input.active) ? input.active : (languages[0] || '')), county, municipality, breakdown, level};
  }
  function opening(data) {
    const available=data.languages.map(l=>l.id).filter(id=>Object.values(data.totals||{}).some(values=>values[id]!==undefined)).sort();
    const active=available.includes('en')?'en':(available[0]||'');
    return normalize({languages:active?[active]:[],active,level:'nj'},data);
  }
  function read(url, data, preset = false, namespace = '') {
    const params = new URL(url).searchParams;
    const key = k => namespace ? namespace + '_' + k : k;
    const q = {get:k=>params.get(key(k)),getAll:k=>params.getAll(key(k))};
    const state=normalize({languages: q.getAll('lit_lang'), active: q.get('lit_active'), county: q.get('lit_county'), municipality: q.get('lit_municipality'), breakdown: q.get('lit_breakdown') === '1', level: q.get('lit_level'),from:q.get('lit_from'),through:q.get('lit_through')}, data);
    const valid=state.languages.length || state.county || ['nj','usa'].includes(q.get('lit_level')) || q.getAll('lit_lang').includes('');
    return preset && !valid ? normalize({...opening(data),from:state.from,through:state.through},data) : state;
  }
  function write(url, state, namespace = '') {
    const result = new URL(url);
    const original = result.searchParams;
    const key = k => namespace ? namespace + '_' + k : k;
    const params = {delete:k=>original.delete(key(k)),append:(k,v)=>original.append(key(k),v),set:(k,v)=>original.set(key(k),v)};
    keys.forEach(k => params.delete(k));
    if(!state.languages.length)params.append('lit_lang','');
    state.languages.forEach(l => params.append('lit_lang', l));
    if (state.active) params.set('lit_active', state.active);
    if (state.county) params.set('lit_county', state.county);
    if (state.municipality) params.set('lit_municipality', state.municipality);
    if (state.breakdown) params.set('lit_breakdown', '1');
    params.set('lit_level', state.level);
    if(state.from)params.set('lit_from',state.from);if(state.through)params.set('lit_through',state.through);
    return result;
  }
  /** Union of disjoint approved-submission groups, never a sum of marginals. */
  function combined(data, selected) {
    if(data.schemaVersion<3)throw Error('Combination aggregate required');
    const totals={},breakdown={},languages=new Set(selected);
    for(const [county,areas] of Object.entries(data.breakdown))for(const [town,values] of Object.entries(areas)){
      if(selected.some(l=>values[l]!==undefined)){(breakdown[county]??={})[town]=0;totals[county]??=0;}
    }
    for(const group of data.combinations){
      if(!group.languages.some(l=>languages.has(l)))continue;
      (breakdown[group.county]??={})[group.municipality]=(breakdown[group.county]?.[group.municipality]??0)+group.count;
      totals[group.county]=(totals[group.county]??0)+group.count;
    }
    return {totals,breakdown};
  }
  function dated(data,state) {
    const days=data.days||[{day:null,totals:data.totals,breakdown:data.breakdown,combinations:data.combinations||[]}];
    const totals={},breakdown={},combinations=[];let undated=0;
    for(const d of days){if(d.day===null)undated+=d.combinations.reduce((n,g)=>n+g.count,0);if(state.dateError || (d.day===null&&(state.from||state.through)) || (d.day!==null&&((state.from&&d.day<state.from)||(state.through&&d.day>state.through))))continue;
      for(const [c,areas] of Object.entries(d.breakdown))for(const [m,values] of Object.entries(areas))for(const [l,n] of Object.entries(values)){((breakdown[c]??={})[m]??={})[l]=(breakdown[c][m][l]??0)+n;(totals[c]??={})[l]=(totals[c][l]??0)+n;}combinations.push(...d.combinations);
    }return {...data,totals,breakdown,combinations,undated};
  }
  function datePreset(kind,today) {
    if(kind==='all')return {from:'',through:''};let from=today;
    if(kind==='year')from=today.slice(0,4)+'-01-01';
    else {const d=new Date(today+'T12:00:00Z');d.setUTCDate(d.getUTCDate()-(kind==='30'?29:89));from=d.toISOString().slice(0,10);}
    return {from,through:today};
  }
  function radius(count, towns, minimum = 5) {
    if (count === undefined) return null;
    if (count === 0) return 4;
    return Math.max(minimum, Math.min(32, Math.sqrt(count) * (towns ? 1.4 : 0.45)));
  }
  // Area is proportional between the documented clamps; never scale by marginals.
  function presentationRadius(count, towns) {
    if(count===undefined)return null;
    return count===0?4:Math.max(14,Math.min(38,Math.sqrt(count)*(towns?1.6:.7)));
  }
  function resourceMatches(resource, state) {
    if(!state.county || state.level==='usa')return false;
    if(state.languages.length && !resource.languages.some(l=>state.languages.includes(l)))return false;
    if(resource.county && resource.county!==state.county)return false;
    if(state.municipality && resource.municipality && resource.municipality!==state.municipality)return false;
    return true;
  }
  const palette = {en:'#0072b2', es:'#d55e00', ko:'#009e73', pt:'#cc79a7', zh:'#e69f00', ar:'#56b4e9', hi:'#756000', ase:'#343434'};
  function color(id) {
    if (palette[id]) return palette[id];
    let hash=0; for (const ch of id) hash=(hash*31+ch.charCodeAt(0))>>>0;
    return `hsl(${hash%360} 55% 38%)`;
  }
  function positiveLanguages(selected, values) { return selected.filter(id => (values?.[id] ?? 0) > 0); }
  function colorMode(matches) { return matches.length>6?'many':matches.length>1?'segments':matches.length===1?'solid':'none'; }
  function fit(box, padding = 0.09) {
    const [x, y, w, h] = box; return [x - w * padding, y - h * padding, w * (1 + 2 * padding), h * (1 + 2 * padding)];
  }
  function clamp(camera, bounds) {
    const w = Math.max(bounds[2] / 8, Math.min(bounds[2], camera[2]));
    const h = w * bounds[3] / bounds[2];
    return [Math.max(bounds[0], Math.min(bounds[0] + bounds[2] - w, camera[0])), Math.max(bounds[1], Math.min(bounds[1] + bounds[3] - h, camera[1])), w, h];
  }
  function zoom(camera, bounds, factor) {
    const w = camera[2] / factor, h = camera[3] / factor;
    return clamp([camera[0] + (camera[2] - w) / 2, camera[1] + (camera[3] - h) / 2, w, h], bounds);
  }
  return {dated,datePreset,resourceMatches, presentationRadius, combined, opening, normalize, read, write, radius, fit, clamp, zoom, color, positiveLanguages, colorMode};
});
