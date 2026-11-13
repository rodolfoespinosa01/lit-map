(function (Drupal, once, settings) {
  'use strict';
  const instances = new WeakMap();
  const NS = 'http://www.w3.org/2000/svg';
  const params = ['lit_lang', 'lit_county', 'lit_municipality', 'lit_breakdown'];
  const own = (object, key) => Object.prototype.hasOwnProperty.call(object || {}, key);
  const element = (tag, text, className) => {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
  };

  function initialize(root, data) {
    const controller = new AbortController();
    const listen = (target, type, handler) => target.addEventListener(type, handler, {signal: controller.signal});
    const q = selector => root.querySelector(selector);
    const languageBox = q('[data-languages]');
    const countySelect = q('[data-county]');
    const townSelect = q('[data-municipality]');
    const toggle = q('[data-breakdown]');
    const svg = q('.lit-map-geometry');
    const results = q('[data-results]');
    const callouts = q('[data-callouts]');
    const counties = new Map(data.counties.map(c => [c.geoid, c]));
    const languages = new Map(data.languages.map(l => [l.id, l]));
    const towns = new Map(data.municipalities.map(t => [t.id, t]));
    const paths = [...svg.querySelectorAll('.lit-map-county')];
    let state;
    let townCounty;

    function readState() {
      const search = new URL(window.location.href).searchParams;
      const county = counties.has(search.get('lit_county')) ? search.get('lit_county') : '';
      const breakdown = search.get('lit_breakdown') === '1';
      const candidate = search.get('lit_municipality');
      const municipality = breakdown && county && (candidate === '_unspecified' || towns.get(candidate)?.county === county) ? candidate : '';
      return {languages: data.languages.filter(l => search.getAll('lit_lang').includes(l.id)).map(l => l.id), county, municipality, breakdown};
    }

    function writeState(replace = false) {
      const url = new URL(window.location.href);
      params.forEach(p => url.searchParams.delete(p));
      state.languages.forEach(id => url.searchParams.append('lit_lang', id));
      if (state.county) url.searchParams.set('lit_county', state.county);
      if (state.breakdown) url.searchParams.set('lit_breakdown', '1');
      if (state.municipality) url.searchParams.set('lit_municipality', state.municipality);
      if (url.href !== window.location.href) {
        window.history[replace ? 'replaceState' : 'pushState'](window.history.state, '', url);
      }
    }

    function update(change) {
      state = {...state, ...change};
      if (!state.breakdown || !state.county || (state.municipality !== '_unspecified' && towns.get(state.municipality)?.county !== state.county)) state.municipality = '';
      writeState();
      render();
    }

    function values(county, municipality = '') {
      return municipality ? data.breakdown[county]?.[municipality] || {} : data.totals[county] || {};
    }

    function chosenLanguages(county) {
      return state.languages.length ? state.languages : data.languages.filter(l => own(values(county), l.id)).map(l => l.id);
    }

    function countText(map, language) {
      return own(map, language) ? `${map[language].toLocaleString()} survey responses` : 'No data available';
    }

    function listCounts(parent, map, ids) {
      if (!ids.length) {
        parent.append(element('p', 'No data available.'));
        return;
      }
      const list = element('dl', undefined, 'lit-interaction__counts');
      ids.forEach(id => {
        const row = element('div');
        row.dataset.language = id;
        row.append(element('dt', languages.get(id).label), element('dd', countText(map, id)));
        list.append(row);
      });
      parent.append(list);
    }

    function countyButton(county) {
      const button = element('button', county.name, 'lit-interaction__county-button');
      button.type = 'button';
      button.dataset.selectCounty = county.geoid;
      button.setAttribute('aria-label', `Select ${county.name} County`);
      button.setAttribute('aria-pressed', String(state.county === county.geoid));
      return button;
    }

    function renderResults() {
      results.replaceChildren(element('h3', 'Survey response results'));
      if (!state.county && !state.languages.length) {
        results.append(element('p', 'Choose a language or county to begin.'));
        if (state.breakdown) results.append(element('p', 'Select a county to see its town/city breakdown.'));
        return;
      }
      if (!state.county) {
        results.append(element('p', 'County totals, shown separately for each selected language. Select a county for more detail.'));
        if (state.breakdown) results.append(element('p', 'Select a county to see its town/city breakdown.'));
        data.counties.forEach(county => {
          const card = element('section', undefined, 'lit-interaction__county-result');
          card.dataset.countyResult = county.geoid;
          const heading = element('h4');
          heading.append(countyButton(county));
          card.append(heading);
          listCounts(card, values(county.geoid), state.languages);
          results.append(card);
        });
        return;
      }
      const county = counties.get(state.county);
      const ids = chosenLanguages(county.geoid);
      results.append(element('h4', `${county.name} County`));
      results.append(element('p', 'County totals include specified towns/cities and municipality-unspecified records. Languages are never added together as unique people.'));
      const totalSection = element('div');
      totalSection.dataset.countyTotals = county.geoid;
      listCounts(totalSection, values(county.geoid), ids);
      results.append(totalSection);
      if (!state.breakdown) return;
      results.append(element('h4', 'Town/city breakdown'));
      if (!Object.keys(values(county.geoid)).length) {
        results.append(element('p', 'No data available for this county.'));
        return;
      }
      const buckets = state.municipality ? [state.municipality] : [...data.municipalities.filter(t => t.county === county.geoid).map(t => t.id), '_unspecified'];
      buckets.forEach(id => {
        const section = element('section', undefined, 'lit-interaction__town-result');
        section.dataset.municipalityResult = id;
        section.append(element('h5', id === '_unspecified' ? 'Municipality unspecified' : towns.get(id).name));
        listCounts(section, values(county.geoid, id), ids);
        results.append(section);
      });
      results.append(element('p', 'Missing records are not zeroes. These are fabricated response counts, not numbers of speakers.', 'lit-interaction__hint'));
    }

    function renderMap() {
      const previousMarkers = svg.querySelector('.lit-interaction__markers');
      if (previousMarkers) previousMarkers.remove();
      const markers = document.createElementNS(NS, 'g');
      markers.setAttribute('class', 'lit-interaction__markers');
      markers.setAttribute('aria-hidden', 'true');
      callouts.replaceChildren();
      const displayed = data.counties.filter(c => state.languages.length ? state.languages.some(id => own(values(c.geoid), id)) : state.county === c.geoid && Object.keys(values(c.geoid)).length);
      paths.forEach(path => {
        const county = counties.get(path.dataset.geoid);
        const map = values(county.geoid);
        const ids = chosenLanguages(county.geoid);
        path.classList.toggle('has-data', state.languages.length > 0 && state.languages.some(id => own(map, id)));
        path.classList.toggle('is-selected', state.county === county.geoid);
        path.setAttribute('aria-pressed', String(state.county === county.geoid));
        const description = ids.length ? ids.map(id => `${languages.get(id).label}: ${countText(map, id)}`).join('; ') : (Object.keys(map).length ? 'Select to see available languages.' : 'No data available.');
        path.setAttribute('aria-label', `${county.name} County. ${description}`);
      });
      displayed.forEach((county, i) => {
        const label = svg.querySelector(`[data-label-for="${county.key}"]`);
        const x = Number(label.getAttribute('x'));
        const y = Number(label.getAttribute('y')) - 17;
        const group = document.createElementNS(NS, 'g');
        const circle = document.createElementNS(NS, 'circle');
        circle.setAttribute('cx', x); circle.setAttribute('cy', y); circle.setAttribute('r', 10);
        const text = document.createElementNS(NS, 'text');
        text.setAttribute('x', x); text.setAttribute('y', y); text.textContent = String(i + 1);
        group.append(circle, text); markers.append(group);
        const card = element('section', undefined, 'lit-interaction__callout');
        card.dataset.calloutCounty = county.geoid;
        const heading = element('h4');
        heading.append(element('span', `${i + 1}`, 'lit-interaction__number'), countyButton(county));
        card.append(heading);
        listCounts(card, values(county.geoid), chosenLanguages(county.geoid));
        callouts.append(card);
      });
      svg.append(markers);
      callouts.hidden = !displayed.length;
      q('.lit-interaction__map-layout').classList.toggle('has-callouts', displayed.length > 0);
    }

    function render() {
      languageBox.querySelectorAll('input').forEach(input => { input.checked = state.languages.includes(input.value); });
      countySelect.value = state.county;
      toggle.checked = state.breakdown;
      townSelect.disabled = !state.county;
      if (townCounty !== state.county) {
        townCounty = state.county;
        townSelect.replaceChildren(new Option(state.county ? 'All towns/cities + unspecified' : 'Select a county first', ''));
        if (state.county) {
          data.municipalities.filter(t => t.county === state.county).forEach(t => townSelect.add(new Option(t.name, t.id)));
          townSelect.add(new Option('Municipality unspecified', '_unspecified'));
        }
      }
      townSelect.value = state.municipality;
      const selected = state.languages.map(id => languages.get(id).label).join(', ');
      const location = state.county ? `${counties.get(state.county).name} County` : 'all counties';
      const town = state.municipality ? ` · ${state.municipality === '_unspecified' ? 'Municipality unspecified' : towns.get(state.municipality).name}` : '';
      q('[data-status]').textContent = !state.county && !selected ? 'Choose a language or county to begin.' : `${selected || 'All available languages'} · ${location}${town}. Survey responses are shown separately by language.`;
      renderMap();
      renderResults();
    }

    languageBox.replaceChildren();
    countySelect.replaceChildren(new Option('Choose a county', ''));
    data.languages.forEach(language => {
      const label = element('label');
      const input = document.createElement('input');
      input.type = 'checkbox'; input.value = language.id;
      label.append(input, document.createTextNode(language.label));
      languageBox.append(label);
    });
    data.counties.forEach(county => countySelect.add(new Option(county.name, county.geoid)));
    listen(languageBox, 'change', () => update({languages: [...languageBox.querySelectorAll('input:checked')].map(i => i.value)}));
    listen(countySelect, 'change', () => update({county: countySelect.value}));
    listen(toggle, 'change', () => update({breakdown: toggle.checked}));
    listen(townSelect, 'change', () => update({municipality: townSelect.value, breakdown: townSelect.value ? true : state.breakdown}));
    listen(q('[data-clear-languages]'), 'click', () => update({languages: []}));
    listen(q('[data-reset]'), 'click', () => update({languages: [], county: '', municipality: '', breakdown: false}));
    listen(root, 'click', event => {
      const button = event.target.closest('[data-select-county]');
      if (button) {
        update({county: button.dataset.selectCounty});
        // Results/callouts are rebuilt; move focus to the persistent county control.
        countySelect.focus({preventScroll: true});
      }
    });
    svg.setAttribute('role', 'group');
    svg.setAttribute('aria-label', 'Select a New Jersey county. Use arrow keys to move between counties, then Enter or Space to select. The County selector provides the same action.');
    paths.forEach((path, index) => {
      path.setAttribute('role', 'button'); path.setAttribute('tabindex', index === 0 ? '0' : '-1');
      listen(path, 'click', () => update({county: path.dataset.geoid}));
      listen(path, 'keydown', event => {
        if (['Enter', ' '].includes(event.key)) { event.preventDefault(); update({county: path.dataset.geoid}); }
        const delta = {ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1}[event.key];
        if (delta || ['Home', 'End'].includes(event.key)) {
          event.preventDefault();
          const next = event.key === 'Home' ? 0 : event.key === 'End' ? paths.length - 1 : (index + delta + paths.length) % paths.length;
          paths.forEach((p, i) => p.setAttribute('tabindex', i === next ? '0' : '-1'));
          paths[next].focus();
        }
      });
    });
    listen(window, 'popstate', () => { state = readState(); writeState(true); render(); });
    state = readState(); writeState(true); render();
    q('[data-controls]').hidden = false;
    return () => controller.abort();
  }

  Drupal.behaviors.litMapInteractions = {
    attach(context) {
      once('lit-map-interactions', '[data-lit-interaction]', context).forEach(root => {
        if (settings.litMapPreview && root.querySelector('[data-controls]')) instances.set(root, initialize(root, settings.litMapPreview));
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') once.remove('lit-map-interactions', '[data-lit-interaction]', context).forEach(root => {
        instances.get(root)?.(); instances.delete(root);
      });
    }
  };
})(Drupal, once, drupalSettings);
