(function($){
  var IMG, SVG, HJSON, BTN_NEW, BTN_FINISH, BTN_DELETE, BTN_SAVE;
  var IN_TITLE, IN_COLOR, IN_HOVER, IN_NOSTROKE, IN_TOOLTIP;
  var state = { shapes: [], current: -1, mode: 'idle', scale: 1, vw: 0, vh: 0, zoom: 1 };
  var uidSeed = Date.now();
  var ui = { alpha: 0.18 };

  function newUID(){ return 'p'+(uidSeed++)+Math.floor(Math.random()*1e3).toString(36); }
  function qs(sel){ return document.querySelector(sel); }
  function toXY(p){ if(Array.isArray(p)) return {x:+p[0]||0,y:+p[1]||0}; return {x:+p.x||0,y:+p.y||0}; }

  // --- ensure side controls exist (auto-inject if missing) ---
  function ensureSideControls(){
    var side = document.querySelector('.dev-side-col'); if(!side) return;

    function ensureTooltip(){
      IN_TOOLTIP = document.getElementById('dev_tooltip');
      if(IN_TOOLTIP) return;
      var wrap=document.createElement('p');
      wrap.innerHTML='<label>Tooltip</label><br><input type="text" id="dev_tooltip" class="widefat" placeholder="Vlastný text pre tooltip">';
      side.appendChild(wrap);
      IN_TOOLTIP = document.getElementById('dev_tooltip');
    }

    function colorElem(id, label, def){
      var el = document.getElementById(id);
      if(el) return el.closest('p') ? el.closest('p') : el;
      var p=document.createElement('p');
      p.style.margin='0';
      p.innerHTML='<label>'+label+'</label><br><input type="color" id="'+id+'" value="'+def+'">';
      return p;
    }

    // Group master color + hover color side-by-side
    var color = document.getElementById('dev_color');
    var hover = document.getElementById('dev_hover_color');
    var row   = document.getElementById('dev_color_row');

    // Build row container when needed
    if(!row){
      row = document.createElement('div');
      row.id = 'dev_color_row';
      row.style.display='grid';
      row.style.gridTemplateColumns='1fr 1fr';
      row.style.gap='8px';
      row.style.margin='6px 0 10px';
    }

    // Prepare left/right cells
    var left  = color ? (color.closest('p')||color) : colorElem('dev_color','Farba (master)','#e91e63');
    var right = hover ? (hover.closest('p')||hover) : colorElem('dev_hover_color','Hover farba (master)','#26a69a');

    // Normalize labels if they already exist
    var l1 = left.querySelector('label'); if(l1) l1.textContent='Farba (master)';
    var l2 = right.querySelector('label'); if(l2) l2.textContent='Hover farba (master)';

    // If row not in DOM – insert heading + row right after Title
    if(!document.getElementById('dev_color_row')){
      var anchorTitle = document.getElementById('dev_title');
      // Heading: Farba polygónu
      var heading = document.createElement('h3');
      heading.id = 'dev_color_heading';
      heading.textContent = 'Farba polygónu';
      heading.style.margin='12px 0 4px';
      heading.style.fontSize='14px';
      heading.style.lineHeight='1.3';

      if(anchorTitle && anchorTitle.closest('p')){
        var after = anchorTitle.closest('p').nextSibling;
        side.insertBefore(heading, after);
        side.insertBefore(row, heading.nextSibling);
      } else {
        side.appendChild(heading);
        side.appendChild(row);
      }
    }

    // Clean row and append both controls
    row.innerHTML='';
    row.appendChild(left);
    row.appendChild(right);

    // Refresh element refs
    IN_COLOR = document.getElementById('dev_color');
    IN_HOVER = document.getElementById('dev_hover_color');

    // Title (if missing)
    IN_TITLE = document.getElementById('dev_title');
    if(!IN_TITLE){
      var p=document.createElement('p');
      p.innerHTML='<label>Nadpis</label><br><input type="text" id="dev_title" class="widefat" placeholder="Interný názov polygonu">';
      side.insertBefore(p, document.getElementById('dev_color_heading') || row); // pred farby
      IN_TITLE = document.getElementById('dev_title');
    }

    // No-stroke (optional)
    IN_NOSTROKE = document.getElementById('dev_no_stroke');

    // Tooltip last
    ensureTooltip();
  }

  function normShape(s){
    var pts=(s&& (s.points||s.coords||[]))||[]; pts=pts.map(toXY);
    return {
      uid:(s&&s.uid)?String(s.uid):newUID(),
      points:pts,
      color:s&&s.color?s.color:'#e91e63',
      hover:(s&&s.hover)? s.hover:'#26a69a',
      no_stroke: !!(s&&s.no_stroke),
      target_id:s&&s.target_id? s.target_id:null,
      target_type:s&&s.target_type? s.target_type:null,
      custom_title:s&&s.custom_title? s.custom_title:'',
      tooltip:(s&&s.tooltip)? String(s.tooltip):''
    };
  }

  function loadFromJSON(){
    var raw = HJSON ? (HJSON.value||'') : '';
    var arr=[]; try{ if(raw && raw.trim().charAt(0)=='[') arr=JSON.parse(raw); }catch(e){ arr=[]; }
    state.shapes = (arr||[]).map(normShape).filter(function(s){ return s.points.length>=3; });
  }

  function saveToJSON(){
    var out = state.shapes.map(function(s){
      return {
        uid: s.uid || newUID(),
        points: s.points.map(function(p){ return [Math.round(p.x), Math.round(p.y)]; }),
        color: s.color || '#e91e63',
        hover: s.hover || '#26a69a',
        no_stroke: !!s.no_stroke,
        target_id: s.target_id || null,
        target_type: s.target_type || null,
        custom_title: s.custom_title || '',
        tooltip: s.tooltip || ''
      };
    });
    if(HJSON) HJSON.value = JSON.stringify(out);
    return out;
  }

  function setViewBox(){
    if(!IMG || !SVG) return;
    var w = IMG.naturalWidth || IMG.width;
    var h = IMG.naturalHeight || IMG.height;
    if(!w||!h){ w = IMG.clientWidth; h = IMG.clientHeight; }
    state.vw=w; state.vh=h;
    SVG.setAttribute('viewBox','0 0 '+w+' '+h);
    var rect = IMG.getBoundingClientRect();
    var scaleX = w / rect.width;
    state.scale = scaleX;
  }

  function clearSVG(){ while(SVG.firstChild) SVG.removeChild(SVG.firstChild); }

  function polyEl(points, color, no_stroke){
    var ns='http://www.w3.org/2000/svg';
    var poly=document.createElementNS(ns,'polygon');
    poly.setAttribute('points', points.map(function(p){ return p.x+','+p.y; }).join(' '));
    poly.setAttribute('fill', color||'#e91e63');
    poly.setAttribute('fill-opacity','0.35');
    if(no_stroke){ poly.setAttribute('stroke','none'); }
    else { poly.setAttribute('stroke', color||'#e91e63'); poly.setAttribute('stroke-width','2'); }
    poly.setAttribute('data-idx','');
    poly.style.cursor='pointer';
    return poly;
  }

  function dotEl(p){ var ns='http://www.w3.org/2000/svg'; var c=document.createElementNS(ns,'circle'); c.setAttribute('cx',p.x); c.setAttribute('cy',p.y); c.setAttribute('r',4); c.setAttribute('fill','#111'); return c; }

  function render(){
    clearSVG();
    state.shapes.forEach(function(s, idx){
      var poly = polyEl(s.points, s.color, s.no_stroke);
      poly.setAttribute('data-idx', String(idx));
      if(state.mode!=='drawing'){
        poly.addEventListener('click', function(ev){ ev.stopPropagation(); select(idx); });
      } else {
        poly.style.pointerEvents='none';
      }
      poly.addEventListener('mouseenter', function(){ this.setAttribute('fill', s.hover||'#26a69a'); if(!s.no_stroke){ this.setAttribute('stroke', s.hover||'#26a69a'); } });
      poly.addEventListener('mouseleave', function(){ this.setAttribute('fill', s.color||'#e91e63'); if(!s.no_stroke){ this.setAttribute('stroke', s.color||'#e91e63'); } });
      SVG.appendChild(poly);
      if(idx===state.current){ s.points.forEach(function(p){ SVG.appendChild(dotEl(p)); }); }
    });
  }

  function select(i){ state.current=i; state.mode='idle'; hydrateSide(); render(); }

  function hydrateSide(){
    var s = state.shapes[state.current];
    if(!s){
      if(IN_TITLE) IN_TITLE.value='';
      if(IN_COLOR) IN_COLOR.value='#e91e63';
      if(IN_HOVER) IN_HOVER.value='#26a69a';
      if(IN_NOSTROKE) IN_NOSTROKE.checked=false;
      if(IN_TOOLTIP) IN_TOOLTIP.value='';
      return;
    }
    if (IN_TITLE)    IN_TITLE.value   = s.custom_title||'';
    if (IN_COLOR)    IN_COLOR.value   = s.color||'#e91e63';
    if (IN_HOVER)    IN_HOVER.value   = s.hover||'#26a69a';
    if (IN_NOSTROKE) IN_NOSTROKE.checked = !!s.no_stroke;
    if (IN_TOOLTIP)  IN_TOOLTIP.value = s.tooltip||'';
  }

  function commitSide(){
    var s = state.shapes[state.current]; if(!s) return;
    if (IN_TITLE)    s.custom_title = IN_TITLE.value||'';
    if (IN_COLOR)    s.color        = IN_COLOR.value||'#e91e63';
    if (IN_HOVER)    s.hover        = IN_HOVER.value||'#26a69a';
    if (IN_NOSTROKE) s.no_stroke    = !!IN_NOSTROKE.checked;
    if (IN_TOOLTIP)  s.tooltip      = IN_TOOLTIP.value||'';
  }

  function clientToImageXY(evt){
    var rect = IMG.getBoundingClientRect();
    var x = (evt.clientX - rect.left) * state.scale / (state.zoom||1);
    var y = (evt.clientY - rect.top)  * state.scale / (state.zoom||1);
    return {x:Math.max(0,Math.min(state.vw, x)), y:Math.max(0,Math.min(state.vh, y))};
  }

  function startNew(){
    commitSide();
    var s={
      uid:newUID(), points:[],
      color:(IN_COLOR?IN_COLOR.value:'#e91e63'),
      hover:(IN_HOVER?IN_HOVER.value:'#26a69a'),
      no_stroke:(IN_NOSTROKE?!!IN_NOSTROKE.checked:false),
      target_id:null, target_type:null,
      custom_title:(IN_TITLE?IN_TITLE.value:''),
      tooltip:(IN_TOOLTIP?IN_TOOLTIP.value:'')
    };
    state.shapes.push(s);
    state.current = state.shapes.length-1;
    state.mode='drawing';
    render();
  }

  function finishDraw(){ if(state.mode!=='drawing') return; var s=state.shapes[state.current]; if(!s||s.points.length<3){ alert('Polygon musí mať aspoň 3 body.'); return; } state.mode='idle'; saveToJSON(); render(); }
  function stepBack(){ if(state.mode!=='drawing') return; var s=state.shapes[state.current]; if(!s) return; s.points.pop(); render(); }
  function deleteSelected(){ if(state.current<0) return; state.shapes.splice(state.current,1); state.current=-1; state.mode='idle'; saveToJSON(); render(); }

  function applyChaikin(){ var s=state.shapes[state.current]; if(!s || s.points.length<3) return; var pts=s.points, out=[]; for(var i=0;i<pts.length;i++){ var p=pts[i], q=pts[(i+1)%pts.length]; out.push({x:0.75*p.x+0.25*q.x,y:0.75*p.y+0.25*q.y}); out.push({x:0.25*p.x+0.75*q.x,y:0.25*p.y+0.75*q.y}); } s.points=out; render(); saveToJSON(); }
  function zoomBy(by){ state.zoom=Math.max(0.25, Math.min(4, (state.zoom||1)*by)); var Z=state.zoom||1; if(IMG) IMG.style.transform='scale('+Z+')'; if(SVG) SVG.style.transform='scale('+Z+')'; }

  function bind(){
    BTN_NEW    && BTN_NEW.addEventListener('click', function(e){ e.preventDefault(); startNew(); });
    BTN_FINISH && BTN_FINISH.addEventListener('click', function(e){ e.preventDefault(); finishDraw(); });
    BTN_DELETE && BTN_DELETE.addEventListener('click', function(e){ e.preventDefault(); deleteSelected(); });

    ;['input','change'].forEach(function(ev){
      document.addEventListener(ev, function(e){
        if(!state || state.current<0) return;
        var id=e.target && e.target.id; if(!id) return;
        if(id==='dev_title' || id==='dev_color' || id==='dev_hover_color' || id==='dev_no_stroke' || id==='dev_tooltip'){
          commitSide(); render(); saveToJSON();
        }
      }, true);
    });

    SVG && SVG.addEventListener('click', function(e){ if(state.mode!=='drawing') return; var p=clientToImageXY(e); var s=state.shapes[state.current]; s.points.push(p); render(); });

    document.addEventListener('keydown', function(e){
      if(state.mode==='drawing' && (e.key==='Backspace' || e.key==='Delete')){ e.preventDefault(); stepBack(); }
      if(state.mode==='drawing' && e.key==='Enter'){ e.preventDefault(); finishDraw(); }
      if(e.shiftKey && (e.key.toLowerCase()==='s')){ e.preventDefault(); applyChaikin(); }
      if(e.key==='+' || e.key==='=' ){ e.preventDefault(); zoomBy(1.2); }
      if(e.key==='-'){ e.preventDefault(); zoomBy(1/1.2); }
      if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='s'){ e.preventDefault(); BTN_SAVE && BTN_SAVE.click(); }
    }, true);
  }

  function injectMiniBar(){
    if(document.getElementById('dev_qt_bar')) return;
    var bar=document.createElement('div'); bar.id='dev_qt_bar';
    bar.style.position='fixed'; bar.style.top='80px'; bar.style.right='16px'; bar.style.display='flex'; bar.style.flexDirection='column'; bar.style.gap='6px'; bar.style.zIndex='9999'; bar.style.pointerEvents='none';
    function B(id,txt){ var b=document.createElement('button'); b.id=id; b.textContent=txt; b.className='button'; b.style.pointerEvents='auto'; b.style.minWidth='120px'; return b; }
    function L(id,html){ var d=document.createElement('div'); d.id=id; d.innerHTML=html; d.style.pointerEvents='auto'; return d; }
    var b1=B('qt_undo','↶ Krok späť'), b2=B('qt_done','✓ Ukončiť'), b3=B('qt_in','＋ Priblížiť'), b4=B('qt_out','－ Vzdialiť'), b5=B('qt_smooth','◔ Zaobliť');
    var row=document.createElement('div'); row.style.display='grid'; row.style.gridTemplateColumns='auto 1fr auto'; row.style.alignItems='center'; row.style.gap='6px'; row.style.background='#fff'; row.style.padding='6px'; row.style.border='1px solid #ccd0d4';
    var lab=L('qt_alpha_lab','Intenzita:'); var val=L('qt_alpha_val','<strong>0.18</strong>');
    var rng=document.createElement('input'); rng.type='range'; rng.min='0.05'; rng.max='0.35'; rng.step='0.01'; rng.value=String(ui.alpha);
    rng.addEventListener('input', function(){ ui.alpha=parseFloat(rng.value)||0.18; val.innerHTML='<strong>'+ui.alpha.toFixed(2)+'</strong>'; });
    row.appendChild(lab); row.appendChild(rng); row.appendChild(val);

    var box=document.createElement('div');
    box.style.background='#fff'; box.style.padding='6px'; box.style.border='1px solid #ccd0d4'; box.style.display='flex'; box.style.flexDirection='column'; box.style.gap='6px';
    ;[b1,b2,b3,b4,b5].forEach(function(b){ box.appendChild(b); });

    var wrap=document.createElement('div'); wrap.style.pointerEvents='auto'; wrap.appendChild(row); wrap.appendChild(box);
    bar.appendChild(wrap); document.body.appendChild(bar);

    b1.addEventListener('click', function(e){ e.preventDefault(); stepBack(); });
    b2.addEventListener('click', function(e){ e.preventDefault(); finishDraw(); });
    b3.addEventListener('click', function(e){ e.preventDefault(); zoomBy(1.2); });
    b4.addEventListener('click', function(e){ e.preventDefault(); zoomBy(1/1.2); });
    b5.addEventListener('click', function(e){ e.preventDefault(); applyChaikin(); });
  }

  function init(){
    IMG = qs('#dev_floor_img');
    SVG = qs('#dev_svg');
    HJSON = qs('#dev_map_json');
    BTN_NEW = qs('#dev_new_poly');
    BTN_FINISH = qs('#dev_finish_poly');
    BTN_DELETE = qs('#dev_delete_poly');
    BTN_SAVE = qs('#dev_save_map');
    IN_TITLE = qs('#dev_title');
    IN_COLOR = qs('#dev_color');
    IN_HOVER = qs('#dev_hover_color');
    IN_NOSTROKE = qs('#dev_no_stroke');
    IN_TOOLTIP = qs('#dev_tooltip');

    ensureSideControls(); // auto-vloží chýbajúce polia a zarovná farby vedľa seba + nadpis

    if(!IMG||!SVG){ return; }

    var onReady = function(){ setViewBox(); loadFromJSON(); bind(); render(); injectMiniBar(); };
    if(!IMG.complete){ IMG.addEventListener('load', onReady); } else { onReady(); }

    window.DEV_MAP_EDITOR = { state: state, render: render };
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
})(jQuery);
