/** Pure state, scale and camera operations, shared with development tests. */
(function (root, factory) {
  const model = factory();
  if (typeof module === 'object' && module.exports) module.exports = model;
  else root.LitBubbleModel = model;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  'use strict';
  const keys = ['lit_lang', 'lit_active', 'lit_county', 'lit_municipality', 'lit_breakdown', 'lit_level'];
  function normalize(input, data) {
    const languages = data.languages.map(l => l.id).filter(id => (input.languages || []).includes(id));
    let county = data.counties.some(c => c.geoid === input.county) ? input.county : '';
    let breakdown = Boolean(input.breakdown && county);
    let municipality = breakdown && (input.municipality === '_unspecified' || data.municipalities.some(m => m.id === input.municipality && m.county === county)) ? input.municipality : '';
    let level = ['usa', 'nj', 'county', 'municipality'].includes(input.level) ? input.level : (county ? (breakdown ? 'municipality' : 'county') : 'nj');
    if (level === 'usa' || level === 'nj') { county = ''; breakdown = false; municipality = ''; }
    else level = county ? (breakdown ? 'municipality' : 'county') : 'nj';
    return {languages, active: languages.includes(input.active) ? input.active : (languages[0] || ''), county, municipality, breakdown, level};
  }
  function read(url, data) {
    const q = new URL(url).searchParams;
    return normalize({languages: q.getAll('lit_lang'), active: q.get('lit_active'), county: q.get('lit_county'), municipality: q.get('lit_municipality'), breakdown: q.get('lit_breakdown') === '1', level: q.get('lit_level')}, data);
  }
  function write(url, state) {
    const result = new URL(url); keys.forEach(k => result.searchParams.delete(k));
    state.languages.forEach(l => result.searchParams.append('lit_lang', l));
    if (state.active) result.searchParams.set('lit_active', state.active);
    if (state.county) result.searchParams.set('lit_county', state.county);
    if (state.municipality) result.searchParams.set('lit_municipality', state.municipality);
    if (state.breakdown) result.searchParams.set('lit_breakdown', '1');
    result.searchParams.set('lit_level', state.level);
    return result;
  }
  function radius(count, towns) {
    if (count === undefined) return null;
    if (count === 0) return 4;
    return Math.max(5, Math.min(32, Math.sqrt(count) * (towns ? 1.4 : 0.45)));
  }
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
  return {normalize, read, write, radius, fit, clamp, zoom};
});
