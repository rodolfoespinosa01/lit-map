(function (Drupal, once, settings, M) {
  'use strict';
  const NS = 'http://www.w3.org/2000/svg';
  Drupal.behaviors.litMapBubbles = {attach(context) {
    once('lit-map-bubbles', '.lit-bubbles', context).forEach(root => {
      const config = settings.litMapBubbles?.[root.id];
      if (!config) return;
      const data = config.survey, geo = config.geography;
      const $ = q => root.querySelector(q);
      const stage = $('.lit-bubbles__stage'), overlay = $('.lit-bubbles__overlay');
      const nj = $('[data-nj] svg'), usaHost = $('[data-usa]');
      // All IDs in the trusted inline county source are instance-scoped in Twig.
      nj.removeAttribute('role'); nj.setAttribute('aria-label', 'New Jersey county boundaries');
      const countyById = Object.fromEntries(data.counties.map(c => [c.geoid, c]));
      const townById = Object.fromEntries(data.municipalities.map(t => [t.id, t]));
      const languageById = Object.fromEntries(data.languages.map(l => [l.id, l.label]));
      let state = M.read(location.href, data), camera, bounds, activeSvg = nj, renderToken = 0;
      let bubbleNodes = [], suppressClick = false, drag = null;
      const assets = new Map();
      const fmt = n => n === undefined ? 'No data available' : `${n.toLocaleString()} survey responses`;
      const el = (tag, text, parent, attrs = {}) => {
        const node = document.createElement(tag); if (text !== null) node.textContent = text;
        Object.entries(attrs).forEach(([k,v]) => node.setAttribute(k,v)); parent?.append(node); return node;
      };
      const svg = (tag, attrs, parent) => {
        const node = document.createElementNS(NS, tag); Object.entries(attrs).forEach(([k,v]) => node.setAttribute(k,v)); parent?.append(node); return node;
      };
      const option = (select, id, label) => el('option', label, select, {value:id});
      data.languages.forEach(l => {
        const label = el('label', null, $('[data-languages]'));
        el('input', null, label, {type:'checkbox', value:l.id}); label.append(document.createTextNode(l.label));
      });
      data.counties.forEach(c => option($ ('select[data-county]'), c.geoid, c.name));
      const chooseCounty = county => change({county, municipality:'', level:county ? (state.breakdown ? 'municipality' : 'county') : 'nj'});
      const chooseTown = municipality => change({municipality, breakdown:true, level:'municipality'});
      $('[data-languages]').addEventListener('change', () => change({languages:[...$('[data-languages]').querySelectorAll('input:checked')].map(i => i.value)}));
      $('[data-active]').addEventListener('change', e => change({active:e.target.value}));
      $ ('select[data-county]').addEventListener('change', e => chooseCounty(e.target.value));
      $ ('select[data-municipality]').addEventListener('change', e => chooseTown(e.target.value));
      $('[data-breakdown]').addEventListener('change', e => change({breakdown:e.target.checked, municipality:'', level:e.target.checked ? 'municipality' : 'county'}));
      function change(patch) {
        state = M.normalize({...state, ...patch}, data);
        history.pushState(null, '', M.write(location.href, state)); render();
      }
      window.addEventListener('popstate', () => {state = M.read(location.href, data); render();});
      function setCamera(next) { camera = M.clamp(next, bounds); activeSvg.setAttribute('viewBox', camera.join(' ')); positionBubbles(); }
      function zoom(factor) {
        if (factor < 1 && camera[2] >= bounds[2] - .001) {
          if (state.county) change({level:'nj'});
          else if (state.level !== 'usa') change({level:'usa'});
        } else setCamera(M.zoom(camera, bounds, factor));
      }
      function pan(dx, dy) { setCamera([camera[0]+dx*camera[2], camera[1]+dy*camera[3], camera[2], camera[3]]); }
      function action(name) {
        if (name === 'nj' || name === 'usa') change({level:name});
        if (name === 'reset') change({languages:[],active:'',county:'',municipality:'',breakdown:false,level:'nj'});
        if (name === 'in') zoom(1.5); if (name === 'out') zoom(1/1.5);
        if (name === 'fit') setCamera(bounds);
        if (name === 'left') pan(-.15,0); if (name === 'right') pan(.15,0);
        if (name === 'up') pan(0,-.15); if (name === 'down') pan(0,.15);
      }
      root.querySelectorAll('[data-action]').forEach(b => b.addEventListener('click', () => action(b.dataset.action)));
      stage.addEventListener('keydown', e => {
        if (e.target !== stage) return;
        const a = {ArrowLeft:'left', ArrowRight:'right', ArrowUp:'up', ArrowDown:'down', '+':'in', '=':'in', '-':'out', Home:'fit'}[e.key];
        if (a) {e.preventDefault(); action(a);}
      });
      $('[data-touch]').addEventListener('change', e => stage.classList.toggle('is-touch-moving',e.target.checked));
      stage.addEventListener('pointerdown', e => {
        if (e.button !== 0 || (e.pointerType === 'touch' && !$('[data-touch]').checked)) return;
        // Delay capture until the drag threshold, preserving native click targets.
        drag = {id:e.pointerId,x:e.clientX,y:e.clientY,camera:[...camera],moved:false};
      });
      stage.addEventListener('pointermove', e => {
        if (!drag || e.pointerId !== drag.id) return;
        const dx=e.clientX-drag.x, dy=e.clientY-drag.y;
        if (!drag.moved && Math.hypot(dx,dy)<6) return;
        if (!drag.moved) {stage.setPointerCapture(e.pointerId); drag.moved=true; stage.classList.add('is-dragging');}
        const matrix=activeSvg.getScreenCTM();
        if (matrix) setCamera([drag.camera[0]-dx/matrix.a,drag.camera[1]-dy/matrix.d,drag.camera[2],drag.camera[3]]);
      });
      function endDrag(e) {
        if (!drag || drag.id !== e.pointerId) return;
        suppressClick=drag.moved; drag=null; stage.classList.remove('is-dragging');
        if (stage.hasPointerCapture(e.pointerId)) stage.releasePointerCapture(e.pointerId);
        setTimeout(() => {suppressClick=false;}, 0);
      }
      stage.addEventListener('pointerup',endDrag); stage.addEventListener('pointercancel',endDrag);
      stage.addEventListener('click', e => {if(suppressClick) {e.preventDefault();e.stopImmediatePropagation();}},true);
      function activate(node, callback, label) {
        node.setAttribute('tabindex','0'); node.setAttribute('role','button'); node.setAttribute('aria-label',label);
        node.addEventListener('click',callback);
        node.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' ') {e.preventDefault();callback();}});
      }
      nj.querySelectorAll('[data-geoid]').forEach(p => activate(p, () => chooseCounty(p.dataset.geoid), `${countyById[p.dataset.geoid].name} County: zoom to county`));
      const reduced = matchMedia('(prefers-reduced-motion: reduce)');
      let motionPreference; try {motionPreference=localStorage.getItem('lit_map.motion');} catch (_) { /* Storage can be disabled. */ }
      function motion() {
        const on=!reduced.matches && motionPreference !== 'off';
        $('[data-motion]').checked=on; $('[data-motion]').disabled=reduced.matches;
        root.classList.toggle('is-motion-off',!on);
        $('[data-motion-help]').textContent=reduced.matches ? 'Motion is off because your device requests reduced motion.' : 'Motion pauses on hover, focus, selection, and while the map is offscreen.';
      }
      $('[data-motion]').addEventListener('change', e=>{motionPreference=e.target.checked?'on':'off';try{localStorage.setItem('lit_map.motion',motionPreference);}catch(_){}motion();});
      reduced.addEventListener('change',motion); motion();
      let visible=true;
      function visibility() {root.classList.toggle('is-offscreen',!visible || document.hidden);}
      new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;visibility();}).observe(stage);
      document.addEventListener('visibilitychange',visibility);
      new ResizeObserver(positionBubbles).observe(stage);
      async function asset(file) {
        if (!assets.has(file)) {
          const promise = fetch(config.assetBase+file, {credentials:'same-origin'}).then(r=>{if(!r.ok)throw Error('asset');return r.text();}).then(text=>{
            const doc=new DOMParser().parseFromString(text,'image/svg+xml');
            const allowed=new Set(['svg','g','path','title','desc','text','rect']);
            if(doc.querySelector('parsererror')||doc.documentElement.localName!=='svg')throw Error('svg');
            for(const node of doc.querySelectorAll('*')) {
              if(!allowed.has(node.localName))throw Error('element');
              for(const attr of node.attributes) {
                if(/^on/i.test(attr.name)||/href|style/i.test(attr.name)||/url\s*\(/i.test(attr.value))throw Error('attribute');
              }
            }
            return doc.documentElement;
          }).catch(error=>{assets.delete(file);throw error;});
          assets.set(file,promise);
        }
        return (await assets.get(file)).cloneNode(true);
      }
      function comparison(parent, values) {
        const selected=state.languages.length?state.languages:data.languages.map(l=>l.id).filter(id=>values?.[id]!==undefined);
        if (!selected.length) {el('p','No data available',parent);return;}
        selected.forEach(id=>el('p',`${languageById[id]}: ${fmt(values?.[id])}`,parent));
      }
      function results() {
        const target=$('[data-results]'); target.replaceChildren();
        const county=countyById[state.county];
        $('[data-results-heading]').textContent=county?`${county.name} County · survey responses`:'County survey responses';
        $('[data-summary]').textContent=state.level==='usa'?'USA overview is context only. Select New Jersey to explore synthetic survey responses.':!state.languages.length?'Choose languages to size bubbles. With a county selected, all its available languages are listed below.':'Each language is reported separately. Responses across languages are never added into a unique-person total.';
        if(state.level==='usa')return;
        if(county) {
          const summary=el('section',null,target);el('h4','County total (includes unspecified municipality)',summary);comparison(summary,data.totals[state.county]);
          if(state.breakdown) {
            const towns=data.municipalities.filter(t=>t.county===state.county);
            const rows=[...towns,{id:'_unspecified',name:'Municipality unspecified'}];
            rows.filter(t=>!state.municipality||state.municipality===t.id).forEach(t=>{
              const row=el('section',null,target,{class:'lit-bubbles__result'+(state.municipality===t.id?' is-selected':'')});
              const b=el('button',t.name,row,{type:'button'});b.addEventListener('click',()=>chooseTown(t.id));
              comparison(row,data.breakdown[state.county]?.[t.id]);
            });
          }
        } else if(state.languages.length) data.counties.forEach(c=>{
          const row=el('section',null,target,{class:'lit-bubbles__result'});
          const b=el('button',`${c.name} County`,row,{type:'button'});b.addEventListener('click',()=>chooseCounty(c.geoid));comparison(row,data.totals[c.geoid]);
        });
      }
      function bubbles() {
        overlay.replaceChildren(); bubbleNodes=[];
        if(!state.active || state.level==='usa')return;
        const towns=state.breakdown;
        const features=towns?data.municipalities.filter(t=>t.county===state.county):data.counties;
        features.forEach((feature,index)=>{
          const id=towns?feature.id:feature.geoid;
          const value=towns?data.breakdown[state.county]?.[id]?.[state.active]:data.totals[id]?.[state.active];
          if(value===undefined)return;
          const geometry=(towns?geo.municipalities:geo.counties)[id];
          const group=svg('g',{'class':'lit-bubbles__bubble'+(value===0?' is-zero':'')+((towns?state.municipality:state.county)===id?' is-selected':''),'data-bubble':id},overlay);
          const r=M.radius(value,towns), floating=svg('g',{'class':'lit-bubbles__float'},group);
          floating.style.setProperty('--duration',`${3+(index%9)*.27}s`);floating.style.setProperty('--phase',`${-(index%7)*.37}s`);
          svg('circle',{r:Math.max(22,r),class:'hit'},group);
          svg('circle',{r,class:'disc'},floating);
          const title=svg('title',{},group);title.textContent=`${feature.name}: ${languageById[state.active]}, ${fmt(value)}`;
          const label=svg('text',{'text-anchor':'middle',dy:4},floating); label.textContent=value.toLocaleString();
          const callout=()=>{$('[data-callout]').textContent=title.textContent;if(document.activeElement!==group)overlay.append(group);};
          group.addEventListener('mouseenter',callout);group.addEventListener('focus',callout);
          activate(group,()=>towns?chooseTown(id):chooseCounty(id),title.textContent);
          bubbleNodes.push({group,floating,geometry});
        });
        const selected=overlay.querySelector('.is-selected'); if(selected)overlay.append(selected);
        if(state.breakdown && state.municipality && state.municipality!=='_unspecified') svg('line',{'data-leader':'',stroke:'#163b46','stroke-width':1.5,'stroke-dasharray':'4 3'},overlay);
        positionBubbles();
      }
      function positionBubbles() {
        if (!activeSvg || !overlay) return;
        const matrix=activeSvg.getScreenCTM(), rect=stage.getBoundingClientRect(); if(!matrix)return;
        overlay.setAttribute('viewBox',`0 0 ${rect.width} ${rect.height}`);
        const leader=overlay.querySelector('[data-leader]');
        if(leader) {
          const anchor=geo.municipalities[state.municipality]?.anchor;
          if(anchor) {const point=new DOMPoint(...anchor).matrixTransform(matrix);
            const x=point.x-rect.left,y=point.y-rect.top;
            leader.setAttribute('x1',x);leader.setAttribute('y1',y);leader.setAttribute('x2',rect.width/2);leader.setAttribute('y2',rect.height);
            leader.style.display=(x>=0 && x<=rect.width && y>=0 && y<=rect.height)?'':'none';
          }
        }
        bubbleNodes.forEach(({group,floating,geometry})=>{
          const point=new DOMPoint(...geometry.anchor).matrixTransform(matrix);
          group.setAttribute('transform',`translate(${point.x-rect.left} ${point.y-rect.top})`);
          floating.style.setProperty('--bob',`${Math.min(1.8,geometry.clearance*Math.abs(matrix.a)*.2)}px`);
        });
      }
      function legend() {
        const container=$('[data-legend]');container.replaceChildren();
        el('p',state.active?`${languageById[state.active]} only · ${state.breakdown?'municipality':'county'} scale`:'Select a language to show bubbles.',container);
        const row=el('div',null,container,{class:'lit-bubbles__legend-samples'});
        (state.breakdown?[0,25,100,600]:[0,250,1000,6000]).forEach(n=>{const item=el('span',null,row);const circle=el('i',null,item,{'aria-hidden':'true'});circle.style.width=circle.style.height=`${2*M.radius(n,state.breakdown)}px`;if(n===0)circle.style.background='white';item.append(document.createTextNode(n.toLocaleString()));});
      }
      async function render() {
        const token=++renderToken;
        root.classList.toggle('is-county',Boolean(state.county));
        root.classList.toggle('has-town-selection',Boolean(state.municipality && state.municipality !== '_unspecified'));
        $('.lit-bubbles__legend').hidden=state.level==='usa';
        $('[data-languages]').querySelectorAll('input').forEach(i=>{i.checked=state.languages.includes(i.value);});
        const active=$('[data-active]');active.replaceChildren();
        if(!state.languages.length)option(active,'','Choose languages first');
        state.languages.forEach(l=>option(active,l,languageById[l]));active.value=state.active;active.disabled=!state.languages.length;
        $ ('select[data-county]').value=state.county;
        $('[data-breakdown]').disabled=!state.county;$('[data-breakdown]').checked=state.breakdown;
        const town=$ ('select[data-municipality]');town.replaceChildren();option(town,'','All towns/cities');
        data.municipalities.filter(t=>t.county===state.county).forEach(t=>option(town,t.id,t.name));
        option(town,'_unspecified','Municipality unspecified');town.disabled=!state.county;town.value=state.municipality;
        nj.querySelector('[data-town-layer]')?.remove();
        nj.querySelectorAll('[data-geoid]').forEach(p=>p.classList.toggle('is-selected',p.dataset.geoid===state.county));
        $('[data-nj]').hidden=state.level==='usa';usaHost.hidden=state.level!=='usa';
        $('[data-map-status]').textContent='';
        $('[data-heading]').textContent=state.level==='usa'?'USA overview · New Jersey highlighted':state.county?`${countyById[state.county].name} County${state.breakdown?' · towns/cities':''}`:'New Jersey · counties';
        $('[data-callout]').textContent=state.municipality?(state.municipality==='_unspecified'?'Municipality unspecified: included in county totals, with no invented map location.':`${townById[state.municipality].name}: ${state.active?languageById[state.active]+', '+fmt(data.breakdown[state.county]?.[state.municipality]?.[state.active]):'select a language to size bubbles.'}`):state.county?`${countyById[state.county].name} County: ${state.active?languageById[state.active]+', '+fmt(data.totals[state.county]?.[state.active]):'all available languages are listed in the panel.'}`:(state.active?'Select a bubble or county to inspect exact counts.':'Select languages to show bubbles. Select a county at any time.');
        if(state.level==='usa')$('[data-callout]').textContent='Select highlighted New Jersey or use Back to New Jersey to explore counties.';
        results();legend();
        activeSvg=nj;bounds=state.county?M.fit(geo.counties[state.county].bbox):[0,0,464.231,824];
        // Clear prior bubbles immediately while loading a different geographic level.
        overlay.replaceChildren();bubbleNodes=[];
        if(state.level==='usa') {
          $('[data-map-status]').textContent='Loading USA boundaries…';
          try {
            const us=await asset(geo.usaAsset); if(token!==renderToken)return; us.removeAttribute('role');
            usaHost.replaceChildren(us); activeSvg=us; bounds=[0,0,1000,760];
            activate(us.querySelector('[data-state="NJ"]'),()=>change({level:'nj'}),'New Jersey: explore counties');
            $('[data-map-status]').textContent='New Jersey is highlighted. Alaska and Hawaii are separate insets at different scales. Other states are context only; territories are omitted.';
          }catch(_){if(token===renderToken)$('[data-map-status]').textContent='USA boundaries could not load. Use Back to New Jersey, or select USA overview to retry.';return;}
        }
        setCamera(bounds);bubbles();
        if(state.breakdown) {
          $('[data-map-status]').textContent='Loading town/city boundaries…';
          try {
            const towns=await asset(geo.assets[state.county]);if(token!==renderToken)return;
            const layer=svg('g',{'data-town-layer':''},nj);
            towns.querySelectorAll('path').forEach(p=>{
              const id=p.dataset.municipality;
              if(!townById[id]||townById[id].county!==state.county)throw Error('county');
              p.classList.toggle('is-selected',id===state.municipality);
              activate(p,()=>chooseTown(id),`${townById[id].name}: show survey response comparisons`);layer.append(p);
            });
            $('[data-map-status]').textContent=`${data.municipalities.filter(t=>t.county===state.county).length} municipality boundaries loaded. Unspecified responses remain in the county total only.`;
          }catch(_){if(token===renderToken){nj.querySelector('[data-town-layer]')?.remove();$('[data-map-status]').textContent='Town boundaries could not load. Counts remain available below. Toggle Show towns/cities off and on to retry.';}}
        }
      }
      render();
    });
  }};
})(Drupal, once, drupalSettings, window.LitBubbleModel);
