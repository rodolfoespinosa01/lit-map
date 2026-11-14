(function (Drupal, once, settings, M) {
  'use strict';
  const NS = 'http://www.w3.org/2000/svg';
  Drupal.behaviors.litMapBubbles = {attach(context) {
    once('lit-map-bubbles', '.lit-bubbles', context).forEach(root => {
      const config = settings.litMapBubbles?.[root.id];
      if (!config) return;
      const originalData=config.survey, geo=config.geography, unionMode=originalData.schemaVersion>=3;
      let data=originalData;
      const unionLabel='Survey responses reporting speaking or understanding ANY selected language';
      let combined={totals:{},breakdown:{}};
      const countAt=(county,town=null)=>unionMode?(town?combined.breakdown[county]?.[town]:combined.totals[county]):(town?data.breakdown[county]?.[town]?.[state.active]:data.totals[county]?.[state.active]);
      const $ = q => root.querySelector(q);
      const stage = $('.lit-bubbles__stage'), overlay = $('.lit-bubbles__overlay');
      const nj = $('[data-nj] svg'), usaHost = $('[data-usa]');
      // All IDs in the trusted inline county source are instance-scoped in Twig.
      nj.removeAttribute('role'); nj.setAttribute('aria-label', 'New Jersey county boundaries');
      const countyById = Object.fromEntries(data.counties.map(c => [c.geoid, c]));
      const townById = Object.fromEntries(data.municipalities.map(t => [t.id, t]));
      const languageById = Object.fromEntries(data.languages.map(l => [l.id, l.label]));
      let state = M.read(location.href, originalData, config.openingPreset), camera, bounds, activeSvg = nj, renderToken = 0;
      let bubbleNodes = [], suppressClick = false, drag = null;
      const assets = new Map();
      const displayRadius=(n,towns)=>config.openingPreset?M.presentationRadius(n,towns):M.radius(n,towns);
      const matching=n=>n===undefined?'No data available':`${n.toLocaleString()} matching survey responses`;
      const fmt = n => n === undefined ? 'No data available' : `${n.toLocaleString()} survey responses`;
      const el = (tag, text, parent, attrs = {}) => {
        const node = document.createElement(tag); if (text !== null) node.textContent = text;
        Object.entries(attrs).forEach(([k,v]) => node.setAttribute(k,v)); parent?.append(node); return node;
      };
      const svg = (tag, attrs, parent) => {
        const node = document.createElementNS(NS, tag); Object.entries(attrs).forEach(([k,v]) => node.setAttribute(k,v)); parent?.append(node); return node;
      };
      const option = (select, id, label) => el('option', label, select, {value:id});
      // A persistent tooltip dock is outside the map/control hit areas. It never
      // times out, can be read with a pointer over it, and is dismissed explicitly.
      const tooltip=$('[data-tooltip]');let tooltipOwner=null;
      function hideTooltip(){tooltip.hidden=true;tooltip.textContent='';tooltipOwner?.removeAttribute('aria-describedby');tooltipOwner=null;$('[data-tooltip-dismiss]').hidden=true;}
      function describe(node, name, values, total=()=>undefined) {
        const show=()=>{
          hideTooltip();const counts=values();
          const languages=state.languages.length?state.languages:data.languages.map(l=>l.id).filter(id=>counts?.[id]!==undefined);
          tooltip.textContent=`${name}: ${unionMode&&state.languages.length?unionLabel+': '+matching(total())+'. Individual language counts overlap. ':''}${languages.length?languages.map(id=>`${languageById[id]}: ${fmt(counts?.[id])}`).join('; '):'No data available'}. Click or press Enter for the information panel.`;
          tooltip.hidden=false;tooltipOwner=node;node.setAttribute('aria-describedby',tooltip.id);$('[data-tooltip-dismiss]').hidden=false;
        };
        node.addEventListener('mouseenter',show);node.addEventListener('focus',show);
      }
      $('[data-tooltip-dismiss]').addEventListener('click',hideTooltip);
      root.addEventListener('keydown',e=>{if(e.key==='Escape')hideTooltip();});

      data.languages.forEach(l => {
        const label = el('label', null, $('[data-languages]'));
        el('input', null, label, {type:'checkbox', value:l.id}); label.append(document.createTextNode(l.label));
      });
      data.counties.forEach(c => option($ ('select[data-county]'), c.geoid, c.name));
      if($('[data-date-from]')){
        $('[data-date-from]').addEventListener('change',()=>change({from:$('[data-date-from]').value}));
        $('[data-date-through]').addEventListener('change',()=>change({through:$('[data-date-through]').value}));
        root.querySelectorAll('[data-date-preset]').forEach(b=>b.addEventListener('click',()=>change(M.datePreset(b.dataset.datePreset,config.today))));
      }
      async function loadPanels(token){
        const panel=$('[data-question-panels]');if(!panel||!config.panelsUrl)return;panel.replaceChildren();
        if(state.dateError){el('p','Invalid date range; no questionnaire results.',panel);return;}
        if(!state.languages.length){el('p','Select languages to view questionnaire aggregates.',panel);return;}
        const url=new URL(config.panelsUrl,location.origin);if(config.publicationHash)url.searchParams.set('publication',config.publicationHash);state.languages.forEach(l=>url.searchParams.append('languages[]',l));for(const [k,v] of Object.entries({county:state.county,municipality:state.municipality,from:state.from,through:state.through}))if(v)url.searchParams.set(k,v);
        el('p','Loading approved questionnaire aggregates…',panel);
        try{const response=await fetch(url,{credentials:'same-origin'});if(!response.ok)throw Error('Unavailable');const d=await response.json();if(token!==renderToken)return;panel.replaceChildren();el('p',d.notice,panel);el('p',`${d.legacyWithoutQuestionnaire} matching legacy responses have no questionnaire answers. Minimum answered-response threshold: ${d.threshold}.`,panel);if(!d.panels.length)el('p','No questionnaire results for this selection.',panel);
          for(const p of d.panels){const box=el('details',null,panel);el('summary',p.label,box);if(p.suppressed){el('p','Suppressed: too few answered responses or a small answer/missing breakdown.',box);continue;}el('p',`${p.answered} answered of ${p.eligible} applicable responses; ${p.missing} missing.`,box);const list=el('ul',null,box);p.values.forEach(v=>el('li',`${v.label}: ${v.count} survey responses`,list));}
        }catch(e){if(token===renderToken){panel.replaceChildren();el('p','Questionnaire aggregates unavailable or publication changed. Refresh the map to align all results.',panel);}}
      }
      const chooseCounty = county => change({county, municipality:'', level:county ? (state.breakdown ? 'municipality' : 'county') : 'nj'});
      const chooseTown = municipality => change({municipality, breakdown:true, level:'municipality'});
      $('[data-languages]').addEventListener('change', () => change({languages:[...$('[data-languages]').querySelectorAll('input:checked')].map(i => i.value)}));
      $('[data-active]')?.addEventListener('change', e => change({active:e.target.value}));
      $ ('select[data-county]').addEventListener('change', e => chooseCounty(e.target.value));
      $ ('select[data-municipality]').addEventListener('change', e => chooseTown(e.target.value));
      $('[data-breakdown]').addEventListener('change', e => change({breakdown:e.target.checked, municipality:'', level:e.target.checked ? 'municipality' : 'county'}));
      function change(patch) {
        state = M.normalize({...state, ...patch}, data);
        history.pushState(null, '', M.write(location.href, state)); render();
      }
      window.addEventListener('popstate', () => {state = M.read(location.href, originalData, config.openingPreset); render();});
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
        if (name === 'reset') change(config.openingPreset?M.opening(originalData):{languages:[],active:'',county:'',municipality:'',breakdown:false,level:'nj'});
        if (name === 'clear') change({languages:[],active:''});
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
      nj.querySelectorAll('[data-geoid]').forEach(p => {activate(p, () => chooseCounty(p.dataset.geoid), `${countyById[p.dataset.geoid].name} County: zoom to county`);describe(p,`${countyById[p.dataset.geoid].name} County`,()=>data.totals[p.dataset.geoid],()=>countAt(p.dataset.geoid));});
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
      function comparison(parent, values, total) {
        if(unionMode&&state.languages.length)el('p',`${unionLabel}: ${matching(total)}`,parent,{'data-combined-count':''});
        const selected=state.languages.length?state.languages:data.languages.map(l=>l.id).filter(id=>values?.[id]!==undefined);
        if (!selected.length) {el('p','No data available',parent);return;}
        if(unionMode)el('h5','Language breakdown · individual counts overlap',parent);
        selected.forEach(id=>el('p',`${languageById[id]}: ${fmt(values?.[id])}`,parent));
      }
      function resources(parent) {
        if(!unionMode)return;
        const section=el('section',null,parent,{'data-resource-panel':'',class:'lit-bubbles__resources'});
        el('h4','Recordings and resources',section);
        if(!state.county){el('p','Select a bubble or place to explore related recordings and resources.',section);return;}
        const matches=(config.resources||[]).filter(r=>M.resourceMatches(r,state));
        if(!matches.length){el('p','No approved resources available for this selection.',section);return;}
        el('p','Related resources are separate from survey responses. County-wide and statewide resources may also appear for a selected town.',section);
        matches.forEach(r=>{const card=$('[data-resource-templates]')?.content.querySelector(`[data-resource-card="${r.id}"]`);if(card)section.append(card.cloneNode(true));});
      }
      function results() {
        const target=$('[data-results]'); target.replaceChildren();
        const county=countyById[state.county],selectedTown=state.municipality;
        const place=selectedTown?(selectedTown==='_unspecified'?'Municipality unspecified':townById[selectedTown].name):county?county.name+' County':'County survey responses';
        $('[data-results-heading]').textContent=place;
        $('[data-summary]').textContent=state.level==='usa'?'USA overview is context only. Select New Jersey to explore synthetic survey responses.':!state.languages.length?'Choose languages to size bubbles. With a county selected, all its available languages are listed below.':unionMode?`${unionLabel}. Each matching approved submission is counted once per area. Individual language counts overlap and must not be added together.`:`Numbers and bubble size: ${languageById[state.active]} survey responses. Other languages are comparisons.`;
        if(state.level==='usa')return;
        if(county) {
          const summary=el('section',null,target,{'data-selected-details':''});
          el('h4',selectedTown?place:'County total (includes unspecified municipality)',summary);
          comparison(summary,selectedTown?data.breakdown[state.county]?.[selectedTown]:data.totals[state.county],countAt(state.county,selectedTown||null));
          resources(summary);
          if(selectedTown){const context=el('details',null,target);el('summary','County total (includes all towns and unspecified)',context);comparison(context,data.totals[state.county],countAt(state.county));}
          if(state.breakdown) {
            const detail=el('details',null,target,{'data-town-breakdown':''});detail.open=!selectedTown;
            el('summary','Town/city counts and unspecified municipality',detail);
            const towns=data.municipalities.filter(t=>t.county===state.county);
            const rows=[...towns,{id:'_unspecified',name:'Municipality unspecified'}];
            if(selectedTown){const clear=el('button','Clear town/city selection',summary,{type:'button'});clear.addEventListener('click',()=>chooseTown(''));}
            rows.sort((a,b)=>{const rank=t=>t.id===selectedTown?0:t.id==='_unspecified'?1:data.breakdown[state.county]?.[t.id]?2:3;return rank(a)-rank(b);}).forEach(t=>{
              const row=el('section',null,detail,{class:'lit-bubbles__result'+(selectedTown===t.id?' is-selected':'')});
              const b=el('button',t.name,row,{type:'button'});b.addEventListener('click',()=>chooseTown(t.id));
              comparison(row,data.breakdown[state.county]?.[t.id],countAt(state.county,t.id));
            });
          }
        } else {
          resources(target);
          if(state.languages.length)data.counties.forEach(c=>{
            const row=el('section',null,target,{class:'lit-bubbles__result'});
            const b=el('button',`${c.name} County`,row,{type:'button'});b.addEventListener('click',()=>chooseCounty(c.geoid));comparison(row,data.totals[c.geoid],countAt(c.geoid));
          });
        }
      }
      function bubbles() {
        overlay.replaceChildren(); bubbleNodes=[];
        if(!state.languages.length || state.level==='usa')return;
        const towns=state.breakdown;
        const features=towns?data.municipalities.filter(t=>t.county===state.county):data.counties;
        features.forEach((feature,index)=>{
          const id=towns?feature.id:feature.geoid;
          const values=towns?data.breakdown[state.county]?.[id]:data.totals[id];
          const value=countAt(towns?state.county:id,towns?id:null);
          const matches=M.positiveLanguages(state.languages,values), mode=M.colorMode(matches);
          if(value===undefined)return;
          const geometry=(towns?geo.municipalities:geo.counties)[id];
          const group=svg('g',{'class':'lit-bubbles__bubble'+(value===0?' is-zero':'')+((towns?state.municipality:state.county)===id?' is-selected':''),'data-bubble':id},overlay);
          const r=displayRadius(value,towns), floating=svg('g',{'class':'lit-bubbles__float'},group);
          floating.style.setProperty('--duration',`${3+(index%9)*.27}s`);floating.style.setProperty('--phase',`${-(index%7)*.37}s`);
          svg('circle',{r:Math.max(22,r),class:'hit'},group);
          if(unionMode)svg('circle',{r:r+4,class:'lit-bubbles__selection'},floating);
          const disc=svg('circle',{r,class:'disc'},floating);
          group.dataset.colorMode=mode;group.dataset.matches=matches.join(',');
          disc.style.fill=value===0?'white':mode==='solid'?M.color(matches[0]):'#666b73';
          if(mode==='segments' || (value===0 && mode==='solid')) {
            matches.forEach((language,segment)=>{
              if(value===0) {
                const arc=2*Math.PI*r/matches.length;
                svg('circle',{r,fill:'none',stroke:M.color(language),'stroke-width':2,'stroke-dasharray':`${arc} ${2*Math.PI*r-arc}`,'stroke-dashoffset':-segment*arc,transform:'rotate(-90)',class:'lit-bubbles__segment'},floating);
              } else {
                const a=-Math.PI/2+segment*2*Math.PI/matches.length,b=a+2*Math.PI/matches.length;
                svg('path',{d:`M0 0 L${r*Math.cos(a)} ${r*Math.sin(a)} A${r} ${r} 0 0 1 ${r*Math.cos(b)} ${r*Math.sin(b)} Z`,fill:M.color(language),class:'lit-bubbles__segment'},floating);
              }
            });
            svg('circle',{r,fill:'none',stroke:'white','stroke-width':1,class:'lit-bubbles__edge'},floating);
          }
          if(mode==='many') {const badge=svg('text',{x:r+5,y:-r,'text-anchor':'middle',class:'lit-bubbles__many'},floating);badge.textContent='+';}
          if(unionMode&&value>0)svg('path',{d:`M${-r*.58} ${-r*.38} A${r*.72} ${r*.72} 0 0 1 ${r*.28} ${-r*.68}`,class:'lit-bubbles__shine',fill:'none',stroke:'white','stroke-width':2.5,'stroke-linecap':'round','aria-hidden':'true'},floating);
          const title=svg('title',{},group);title.textContent=`${feature.name}: ${unionMode?unionLabel:'size = '+languageById[state.active]}, ${unionMode?matching(value):fmt(value)}. Positive selected languages: ${matches.map(l=>languageById[l]).join(', ')||'none'}. ${mode==='many'?'More than six: neutral disc with + indicator. ':''}Colors are categories, not shares.`;
          const label=svg('text',{'text-anchor':'middle',dy:4},floating); label.textContent=value.toLocaleString();
          describe(group,feature.name,()=>values,()=>value);
          activate(group,()=>towns?chooseTown(id):chooseCounty(id),title.textContent);title.remove();
          bubbleNodes.push({group,floating,geometry,label,r,id,name:feature.name,value});
        });
        const selected=overlay.querySelector('.is-selected'); if(selected)overlay.append(selected);
        if(!unionMode && state.breakdown && state.municipality && state.municipality!=='_unspecified') svg('line',{'data-leader':'',stroke:'#163b46','stroke-width':1.5,'stroke-dasharray':'4 3'},overlay);
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
        overlay.querySelector('[data-count-callouts]')?.remove();
        overlay.querySelector('[data-anchor-lines]')?.remove();
        const placed=[];
        const visible=bubbleNodes.map(b=>{const p=new DOMPoint(...b.geometry.anchor).matrixTransform(matrix);return {...b,ax:p.x-rect.left,ay:p.y-rect.top};});
        const anchors=svg('g',{'data-anchor-lines':'','aria-hidden':'true','pointer-events':'none'},null);overlay.prepend(anchors);
        visible.sort((a,b)=>Number(b.group.classList.contains('is-selected'))-Number(a.group.classList.contains('is-selected')) || b.r-a.r || a.id.localeCompare(b.id));
        visible.forEach(b=>{
          let x=b.ax,y=b.ay;
          const onMap=x>=0&&x<=rect.width&&y>=0&&y<=rect.height;
          if(config.openingPreset && onMap){
            let best=Infinity;
            for(let ring=0;ring<=7;ring++)for(let i=0;i<(ring?24:1);i++){
              const a=i*Math.PI/12,px=b.ax+ring*12*Math.cos(a),py=b.ay+ring*12*Math.sin(a);
              if(px<b.r+3||px>rect.width-b.r-3||py<b.r+3||py>rect.height-b.r-3)continue;
              const overlap=placed.reduce((n,p)=>n+Math.max(0,Math.max(23,b.r+p.r+5)-Math.hypot(px-p.x,py-p.y))**2,0);
              const score=overlap*100+ring*ring*144;
              if(score<best){best=score;x=px;y=py;}
            }
            placed.push({...b,x,y});
            if(Math.hypot(x-b.ax,y-b.ay)>6){
              svg('line',{x1:b.ax,y1:b.ay,x2:x,y2:y,class:'lit-bubbles__anchor-line'},anchors);
              svg('circle',{cx:b.ax,cy:b.ay,r:2.3,class:'lit-bubbles__anchor-dot'},anchors);
            }
          }
          b.group.setAttribute('transform',`translate(${x} ${y})`);
          b.floating.style.setProperty('--bob',`${Math.min(1.8,b.geometry.clearance*Math.abs(matrix.a)*.2)}px`);
          b.label.style.display='';
        });
        // Reserve numeric callouts for actual text collisions, not overlapping rims.
        if(config.openingPreset && !state.breakdown){
          const crowded=placed.filter(a=>placed.some(b=>a.id!==b.id && Math.abs(a.x-b.x)<(a.label.getComputedTextLength()+b.label.getComputedTextLength())/2+5 && Math.abs(a.y-b.y)<18)).sort((a,b)=>a.y-b.y);
          if(crowded.length){
            const rail=svg('g',{'data-count-callouts':'',class:'lit-bubbles__count-callouts'},overlay),step=Math.min(28,(rect.height-24)/crowded.length);
            crowded.forEach((b,i)=>{
              b.label.style.display='none';const y=14+i*step,width=Math.min(155,rect.width*.34);
              svg('line',{x1:b.x,y1:b.y,x2:8+width,y2:y,stroke:'#365a65','stroke-width':1,'pointer-events':'none'},rail);
              const box=svg('g',{transform:`translate(8 ${y})`,'data-county-callout':b.id},rail);
              svg('rect',{x:0,y:-10,width,height:20,rx:3,fill:'white',stroke:'#365a65'},box);
              const t=svg('text',{x:5,y:4},box);t.textContent=`${b.name}: ${b.value.toLocaleString()}`;
              activate(box,()=>chooseCounty(b.id),`${b.name} County: ${unionLabel}, ${matching(b.value)}`);
              describe(box,`${b.name} County`,()=>data.totals[b.id],()=>countAt(b.id));
            });
          }
        }
      }

      function legend() {
        const container=$('[data-legend]');container.replaceChildren();
        const colors=$('[data-color-legend]');colors.replaceChildren();
        el('p','Stable language colors · positive selected-language counts only',colors);
        const entries=el('ul',null,colors,{class:'lit-bubbles__color-key'});
        data.languages.filter(l=>state.languages.includes(l.id)).forEach(l=>{
          const item=el('li',null,entries),swatch=el('span',null,item,{'aria-hidden':'true',class:'lit-bubbles__swatch'});
          swatch.style.backgroundColor=M.color(l.id);item.append(document.createTextNode(l.label));
        });
        el('p',state.languages.length?`${unionMode?'ANY selected language':languageById[state.active]+' only'} · ${state.breakdown?'municipality':'county'} scale`:'Select a language to show bubbles.',container);
        const row=el('div',null,container,{class:'lit-bubbles__legend-samples'});
        (state.breakdown?[0,25,100,600]:[0,250,1000,6000]).forEach(n=>{const item=el('span',null,row);const circle=el('i',null,item,{'aria-hidden':'true'});circle.style.width=circle.style.height=`${2*displayRadius(n,state.breakdown)}px`;if(n===0)circle.style.background='white';item.append(document.createTextNode(n.toLocaleString()));});
      }
      async function render() {
        const token=++renderToken;hideTooltip();
        if(unionMode){data=M.dated(originalData,state);combined=M.combined(data,state.languages);}
        if($('[data-date-from]')){
          $('[data-date-from]').value=state.from;$('[data-date-through]').value=state.through;
          $('[data-date-status]').textContent=state.dateError?'Invalid date range. Choose valid dates with From on or before Through.':`${state.from||'All dates'} through ${state.through||'latest publication'} · America/New_York. ${data.undated||0} undated legacy responses are included only in All time. Date filters use submitted dates, not approval or publication dates.`;
          if(!state.dateError && (state.from||state.through) && !Object.values(combined.totals).some(n=>n>0))$('[data-date-status]').textContent+=' No published responses match these dates and languages.';
          const summary=$('[data-filter-disclosure] summary');if(summary)summary.textContent='Change languages, places and submission dates · '+(state.dateError?'Invalid dates':(state.from||state.through)?`${state.from||'earliest'} – ${state.through||'latest'}`:'All time');
          loadPanels(token);
        }
        root.classList.toggle('is-county',Boolean(state.county));
        root.classList.toggle('has-town-selection',Boolean(state.municipality && state.municipality !== '_unspecified'));
        $('.lit-bubbles__legend').hidden=state.level==='usa';
        $('[data-languages]').querySelectorAll('input').forEach(i=>{i.checked=state.languages.includes(i.value);});
        const active=$('[data-active]');if(active){active.replaceChildren();
        if(!state.languages.length)option(active,'','Choose languages first');
        state.languages.forEach(l=>option(active,l,languageById[l]));active.value=state.active;active.disabled=!state.languages.length;}
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
        $('[data-callout]').textContent=state.municipality?(state.municipality==='_unspecified'?`Municipality unspecified: ${state.active?languageById[state.active]+', '+fmt(data.breakdown[state.county]?.['_unspecified']?.[state.active]):'included in county totals, with no invented map location.'}`:`${townById[state.municipality].name}: ${state.active?languageById[state.active]+', '+fmt(data.breakdown[state.county]?.[state.municipality]?.[state.active]):'select a language to size bubbles.'}`):state.county?`${countyById[state.county].name} County: ${state.active?languageById[state.active]+', '+fmt(data.totals[state.county]?.[state.active]):'all available languages are listed in the panel.'}`:(state.active?'Select a bubble or county to inspect exact counts.':'Select languages to show bubbles. Select a county at any time.');
        if(state.level==='usa')$('[data-callout]').textContent='Select highlighted New Jersey or use Back to New Jersey to explore counties.';
        if(unionMode && state.level!=='usa'){
          const place=state.municipality?(state.municipality==='_unspecified'?'Municipality unspecified':townById[state.municipality].name):state.county?countyById[state.county].name+' County':'New Jersey';
          const n=state.county?countAt(state.county,state.municipality||null):(Object.keys(combined.totals).length?Object.values(combined.totals).reduce((a,b)=>a+b,0):undefined);
          $('[data-callout]').textContent=state.languages.length?`${place} · ${unionLabel}: ${matching(n)}. Individual language counts overlap.`:'Languages cleared. Choose languages or reset the opening preset.';
        }
        if(config.openingPreset){
          const count=state.languages.length?data.counties.filter(c=>countAt(c.geoid)!==undefined).length:0;
          $('[data-opening-status]').textContent=state.languages.length?(unionMode?`${unionLabel}. Numbers and bubble area count each matching submission once.`:`Numbers and bubble size: ${languageById[state.active]} survey responses.`):'Languages cleared. Choose a language or reset to the opening preset.';
          $('[data-coverage]').textContent=state.languages.length?`Approved publication: ${count} of ${data.counties.length} counties have records for the selected languages. Other counties have No data available, not zero. City mode displays published municipality records; unspecified responses remain in the county total.`:'No language selected; bubbles are hidden until you choose a language.';

        }
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
              activate(p,()=>chooseTown(id),`${townById[id].name}: show survey response comparisons`);describe(p,townById[id].name,()=>data.breakdown[state.county]?.[id],()=>countAt(state.county,id));layer.append(p);
            });
            $('[data-map-status]').textContent=`${data.municipalities.filter(t=>t.county===state.county).length} municipality boundaries loaded. Unspecified responses remain in the county total only.`;
          }catch(_){if(token===renderToken){nj.querySelector('[data-town-layer]')?.remove();$('[data-map-status]').textContent='Town boundaries could not load. Counts remain available below. Toggle Show towns/cities off and on to retry.';}}
        }
      }
      render();
    });
  }};
})(Drupal, once, drupalSettings, window.LitBubbleModel);
