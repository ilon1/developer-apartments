(function($){
  var IMG, SVG, HJSON, BTN_NEW, BTN_FINISH, BTN_DELETE, BTN_SAVE;
  var IN_TITLE, IN_COLOR, IN_HOVER, IN_NOSTROKE, IN_TOOLTIP;
  var state = { shapes: [], current: -1, mode: 'idle', scale: 1, vw: 0, vh: 0, zoom: 1, undoStack: [] };
  var uidSeed = Date.now();
  var ui = { alpha: 0.18 };
  var MAX_UNDO = 30;

  function newUID(){ return 'p'+(uidSeed++)+Math.floor(Math.random()*1e3).toString(36); }
  function qs(sel){ return document.querySelector(sel); }
  function toXY(p){ if(Array.isArray(p)) return {x:+p[0]||0,y:+p[1]||0}; return {x:+p.x||0,y:+p.y||0}; }

  function parseColorToHex(str){
    if(!str||typeof str!=='string') return null;
    str=str.trim();
    var hex=/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.exec(str);
    if(hex){
      var h=hex[1]; if(h.length===3) h=h[0]+h[0]+h[1]+h[1]+h[2]+h[2]; return '#'+h;
    }
    var rgb=/^rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*[\d.]+)?\s*\)$/.exec(str)||/^rgba?\s*\(\s*(\d+)\s+(\d+)\s+(\d+)(?:\s*\/\s*[\d.]+)?\s*\)$/.exec(str);
    if(rgb){ var r=Math.max(0,Math.min(255,parseInt(rgb[1],10))); var g=Math.max(0,Math.min(255,parseInt(rgb[2],10))); var b=Math.max(0,Math.min(255,parseInt(rgb[3],10))); return '#'+[r,g,b].map(function(x){ var h=x.toString(16); return h.length<2?'0'+h:h; }).join(''); }
    return null;
  }
  function bindColorHexSync(){
    var pairs=[['dev_global_color','dev_global_color_hex'],['dev_global_hover','dev_global_hover_hex'],['dev_color','dev_color_hex'],['dev_hover_color','dev_hover_color_hex']];
    pairs.forEach(function(p){
      var picker=document.getElementById(p[0]); var hexInput=document.getElementById(p[1]);
      if(!picker||!hexInput) return;
      function pickerToHex(){ hexInput.value=picker.value||''; }
      function hexToPicker(){
        var h=parseColorToHex(hexInput.value); if(h){ picker.value=h; picker.dispatchEvent(new Event('input',{bubbles:true})); }
      }
      picker.addEventListener('input',pickerToHex); picker.addEventListener('change',pickerToHex);
      hexInput.addEventListener('blur',hexToPicker); hexInput.addEventListener('input',hexToPicker); hexInput.addEventListener('keydown',function(e){ if(e.key==='Enter'){ hexToPicker(); e.preventDefault(); } });
      pickerToHex();
    });
  }

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

    // Ak už máme farby z PHP (nový layout), len obnovíme referencie
    if(color && hover){
      IN_COLOR = color;
      IN_HOVER = hover;
      IN_TITLE = document.getElementById('dev_title');
      IN_NOSTROKE = document.getElementById('dev_no_stroke');
      ensureTooltip();
      return;
    }

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

  function dotEl(p, vertexIndex){
    var ns='http://www.w3.org/2000/svg';
    var c=document.createElementNS(ns,'circle');
    c.setAttribute('cx',p.x); c.setAttribute('cy',p.y); c.setAttribute('r',4); c.setAttribute('fill','#111');
    if(vertexIndex>=0) c.setAttribute('data-vertex-index', String(vertexIndex));
    c.style.cursor = vertexIndex>=0 ? 'pointer' : 'default';
    return c;
  }

  function render(){
    clearSVG();
    state.shapes.forEach(function(s, idx){
      var poly = polyEl(s.points, s.color, s.no_stroke);
      poly.setAttribute('data-idx', String(idx));
      if(s.uid) poly.setAttribute('data-uid', String(s.uid));
      if(idx===state.current){
        poly.setAttribute('data-selected', '1');
        if(!s.no_stroke){ poly.setAttribute('stroke', '#1e88e5'); poly.setAttribute('stroke-width', '4'); }
        poly.setAttribute('fill-opacity', '0.45');
      }
      if(state.mode!=='drawing'){
        poly.addEventListener('click', function(ev){ ev.stopPropagation(); select(idx); });
      } else {
        poly.style.pointerEvents='none';
      }
      poly.addEventListener('mouseenter', function(){ this.setAttribute('fill', s.hover||'#26a69a'); if(!s.no_stroke && idx!==state.current){ this.setAttribute('stroke', s.hover||'#26a69a'); this.setAttribute('stroke-width','2'); } });
      poly.addEventListener('mouseleave', function(){ this.setAttribute('fill', s.color||'#e91e63'); if(!s.no_stroke){ this.setAttribute('stroke', idx===state.current?'#1e88e5':(s.color||'#e91e63')); this.setAttribute('stroke-width', idx===state.current?'4':'2'); } });
      SVG.appendChild(poly);
      if(idx===state.current){ s.points.forEach(function(p, vi){ var dot=dotEl(p, vi); SVG.appendChild(dot); }); }
    });
    updatePolyList();
  }

  function updatePolyList(){
    var listEl = document.getElementById('dev_poly_list');
    if(!listEl) return;
    listEl.innerHTML='';
    state.shapes.forEach(function(s, idx){
      var row = document.createElement('div');
      row.className = 'dev-poly-row' + (idx===state.current ? ' dev-poly-row-current' : '');
      row.setAttribute('data-idx', String(idx));
      row.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 8px;border-bottom:1px solid #eee;cursor:pointer;' + (idx===state.current ? 'background:#e7f3ff;' : '');
      var title = (s.custom_title||'').trim() || ('Polygón ' + (idx+1));
      var swatch = document.createElement('span');
      swatch.style.cssText = 'width:14px;height:14px;border:1px solid #999;background:' + (s.color||'#e91e63') + ';flex-shrink:0;';
      var swatchHover = document.createElement('span');
      swatchHover.style.cssText = 'width:14px;height:14px;border:1px solid #999;background:' + (s.hover||'#26a69a') + ';flex-shrink:0;';
      var label = document.createElement('span');
      label.textContent = title;
      label.style.flex = '1';
      label.style.overflow = 'hidden';
      label.style.textOverflow = 'ellipsis';
      label.style.whiteSpace = 'nowrap';
      var btnDel = document.createElement('button');
      btnDel.type = 'button';
      btnDel.className = 'button button-small';
      btnDel.textContent = '×';
      btnDel.title = 'Vymazať';
      btnDel.style.flexShrink = '0';
      btnDel.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); deletePolyAtIndex(idx); });
      row.appendChild(swatch);
      row.appendChild(swatchHover);
      row.appendChild(label);
      row.appendChild(btnDel);
      row.addEventListener('click', function(e){ if(e.target!==btnDel) select(idx); });
      listEl.appendChild(row);
    });
  }

  function select(i){ state.current=i; state.mode='idle'; hydrateSide(); render(); updatePolyList();
    try{ window.dispatchEvent(new CustomEvent('dev_map_selection_changed', { detail: { index: i } })); }catch(e){}
  }
  function deletePolyAtIndex(i){
    if(i<0||i>=state.shapes.length) return;
    state.shapes.splice(i,1);
    if(state.current===i) state.current=-1;
    else if(state.current>i) state.current--;
    state.mode='idle';
    saveToJSON();
    render();
    updatePolyList();
    hydrateSide();
  }

  function hydrateSide(){
    var s = state.shapes[state.current];
    var globalColor = (qs('#dev_global_color')||{}).value || '#e91e63';
    var globalHover = (qs('#dev_global_hover')||{}).value || '#26a69a';
    if(!s){
      if(IN_TITLE) IN_TITLE.value='';
      if(IN_COLOR) IN_COLOR.value=globalColor;
      if(IN_HOVER) IN_HOVER.value=globalHover;
      var hc=qs('#dev_color_hex'); var hh=qs('#dev_hover_color_hex'); if(hc) hc.value=IN_COLOR?IN_COLOR.value:''; if(hh) hh.value=IN_HOVER?IN_HOVER.value:'';
      if(IN_NOSTROKE) IN_NOSTROKE.checked=false;
      if(IN_TOOLTIP) IN_TOOLTIP.value='';
      return;
    }
    if (IN_TITLE)    IN_TITLE.value   = s.custom_title||'';
    if (IN_COLOR)    IN_COLOR.value   = s.color||'#e91e63';
    if (IN_HOVER)    IN_HOVER.value   = s.hover||'#26a69a';
    var hc=qs('#dev_color_hex'); var hh=qs('#dev_hover_color_hex'); if(hc) hc.value=IN_COLOR?IN_COLOR.value:''; if(hh) hh.value=IN_HOVER?IN_HOVER.value:'';
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
    var globalColor = (qs('#dev_global_color')||{}).value || '#e91e63';
    var globalHover = (qs('#dev_global_hover')||{}).value || '#26a69a';
    var s={
      uid:newUID(), points:[],
      color:(IN_COLOR&&IN_COLOR.value)?IN_COLOR.value:globalColor,
      hover:(IN_HOVER&&IN_HOVER.value)?IN_HOVER.value:globalHover,
      no_stroke:(IN_NOSTROKE?!!IN_NOSTROKE.checked:false),
      target_id:null, target_type:null,
      custom_title:(IN_TITLE?IN_TITLE.value:''),
      tooltip:(IN_TOOLTIP?IN_TOOLTIP.value:'')
    };
    state.shapes.push(s);
    state.current = state.shapes.length-1;
    state.mode='drawing';
    render();
    updatePolyList();
  }

  function pushUndo(){
    if(state.current<0) return;
    var s=state.shapes[state.current]; if(!s) return;
    state.undoStack.push({ shapeIndex: state.current, points: s.points.map(function(p){ return {x:p.x,y:p.y}; }) });
    if(state.undoStack.length>MAX_UNDO) state.undoStack.shift();
  }
  function stepBack(){
    if(state.mode==='drawing'){
      var s=state.shapes[state.current]; if(!s) return;
      s.points.pop(); saveToJSON(); render(); updatePolyList();
      return;
    }
    if(state.undoStack.length>0){
      var entry=state.undoStack.pop();
      var sh=state.shapes[entry.shapeIndex]; if(sh) sh.points=entry.points;
      saveToJSON(); render(); updatePolyList(); hydrateSide();
      try{ window.dispatchEvent(new CustomEvent('dev_map_selection_changed', { detail: { index: state.current } })); }catch(e){}
      return;
    }
  }
  function deleteSelected(){ if(state.current<0) return; state.shapes.splice(state.current,1); state.current=-1; state.mode='idle'; saveToJSON(); render(); updatePolyList(); }

  function applyChaikin(){ var s=state.shapes[state.current]; if(!s || s.points.length<3) return; pushUndo(); var pts=s.points, out=[]; var alpha=Math.max(0.05, Math.min(0.35, (ui.alpha||0.18))); var f=0.2+alpha; var left=0.5-f/2, right=0.5+f/2; for(var i=0;i<pts.length;i++){ var p=pts[i], q=pts[(i+1)%pts.length]; out.push({x:left*p.x+right*q.x,y:left*p.y+right*q.y}); out.push({x:right*p.x+left*q.x,y:right*p.y+left*q.y}); } s.points=out; render(); saveToJSON(); }
  function applyChaikinAtVertex(vertexIndex){
    var s=state.shapes[state.current]; if(!s || s.points.length<3) return;
    var pts=s.points, n=pts.length;
    var i=(vertexIndex+n)%n;
    var prev=pts[(i-1+n)%n], curr=pts[i], next=pts[(i+1)%n];
    var alpha=Math.max(0.05, Math.min(0.35, (ui.alpha||0.18)));
    var mid={x:(prev.x+next.x)*0.5, y:(prev.y+next.y)*0.5};
    var control={x:(1-alpha)*mid.x+alpha*curr.x, y:(1-alpha)*mid.y+alpha*curr.y};
    var arcPts=[];
    for(var k=1;k<=3;k++){
      var t=k*0.25, u=1-t;
      arcPts.push({
        x: u*u*prev.x + 2*u*t*control.x + t*t*next.x,
        y: u*u*prev.y + 2*u*t*control.y + t*t*next.y
      });
    }
    pushUndo();
    s.points=pts.slice(0,i).concat(arcPts, pts.slice(i+1));
    render(); saveToJSON();
  }
  function zoomBy(by){ state.zoom=Math.max(0.25, Math.min(4, (state.zoom||1)*by)); var Z=state.zoom||1; if(IMG) IMG.style.transform='scale('+Z+')'; if(SVG) SVG.style.transform='scale('+Z+')'; }

  function pointToSegmentDist(p, a, b){
    var dx=b.x-a.x, dy=b.y-a.y;
    var len2=dx*dx+dy*dy;
    if(len2<1e-10) return { dist: Math.hypot(p.x-a.x, p.y-a.y), t: 0, proj: {x:a.x, y:a.y} };
    var t=((p.x-a.x)*dx+(p.y-a.y)*dy)/len2;
    t=Math.max(0, Math.min(1, t));
    var proj={x: a.x+t*dx, y: a.y+t*dy};
    return { dist: Math.hypot(p.x-proj.x, p.y-proj.y), t: t, proj: proj };
  }
  function addPointOnEdge(clickXY, shapeIndex){
    var s=state.shapes[shapeIndex]; if(!s) return;
    var pts=s.points, n=pts.length; if(n<2) return;
    var best={ dist: 1e9, edgeIndex: -1, proj: null };
    for(var i=0;i<n;i++){
      var a=pts[i], b=pts[(i+1)%n];
      var r=pointToSegmentDist(clickXY, a, b);
      if(r.dist<best.dist){ best.dist=r.dist; best.edgeIndex=i; best.proj=r.proj; }
    }
    var maxDist=Math.max(15, state.vw*0.02);
    if(best.edgeIndex<0 || best.dist>maxDist) return;
    pushUndo();
    var i=best.edgeIndex;
    s.points=pts.slice(0,i+1).concat([best.proj], pts.slice(i+1));
    render(); saveToJSON(); updatePolyList();
  }

  function parsePathD(d){
    var pts=[]; if(!d) return pts;
    var re=/[ML]\s*([\d.-]+)\s*[\s,]\s*([\d.-]+)/gi, m;
    while((m=re.exec(d))!==null) pts.push({x:parseFloat(m[1])||0, y:parseFloat(m[2])||0});
    return pts;
  }
  function importFromSVG(svgText){
    var parser=new DOMParser(), doc=parser.parseFromString(svgText,'image/svg+xml');
    var added=0, globalColor=(qs('#dev_global_color')||{}).value||'#e91e63', globalHover=(qs('#dev_global_hover')||{}).value||'#26a69a';
    doc.querySelectorAll('polygon, polyline').forEach(function(poly){
      var ptsAttr=poly.getAttribute('points'); if(!ptsAttr) return;
      var pts=[], parts=ptsAttr.trim().split(/[\s,]+/);
      for(var i=0;i<parts.length-1;i+=2) pts.push({x:parseFloat(parts[i])||0, y:parseFloat(parts[i+1])||0});
      if(pts.length>=3){ state.shapes.push(normShape({points:pts, color:globalColor, hover:globalHover})); added++; }
    });
    doc.querySelectorAll('path').forEach(function(path){
      var d=path.getAttribute('d'); if(!d) return;
      var pts=parsePathD(d); if(pts.length>=3){ state.shapes.push(normShape({points:pts, color:globalColor, hover:globalHover})); added++; }
    });
    if(added){ saveToJSON(); render(); updatePolyList(); }
    return added;
  }
  function importFromJSON(jsonText){
    var arr=[]; try{ arr=JSON.parse(jsonText); }catch(e){ return 0; }
    if(!Array.isArray(arr)) return 0;
    var globalColor=(qs('#dev_global_color')||{}).value||'#e91e63', globalHover=(qs('#dev_global_hover')||{}).value||'#26a69a';
    var added=0; arr.forEach(function(item){ var s=normShape(item); if(s.points.length>=3){ state.shapes.push(s); added++; } });
    if(added){ saveToJSON(); render(); updatePolyList(); }
    return added;
  }

  function bind(){
    BTN_NEW    && BTN_NEW.addEventListener('click', function(e){ e.preventDefault(); startNew(); });
    BTN_FINISH && BTN_FINISH.addEventListener('click', function(e){ e.preventDefault(); finishDraw(); });
    BTN_DELETE && BTN_DELETE.addEventListener('click', function(e){ e.preventDefault(); deleteSelected(); });

    var savePolyBtn = qs('#dev_save_poly');
    if(savePolyBtn){
      savePolyBtn.addEventListener('click', function(e){
        e.preventDefault();
        commitSide();
        saveToJSON();
        if(BTN_SAVE) BTN_SAVE.click();
      });
    }

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

    SVG && SVG.addEventListener('click', function(e){
      if(e.altKey && e.target && e.target.getAttribute && e.target.getAttribute('data-vertex-index')!==null){
        e.preventDefault(); e.stopPropagation();
        var vi=parseInt(e.target.getAttribute('data-vertex-index'),10);
        if(!isNaN(vi) && state.current>=0) applyChaikinAtVertex(vi);
      }
    }, true);

    SVG && SVG.addEventListener('click', function(e){
      if(e.ctrlKey && state.mode==='idle' && state.current>=0){
        var t=e.target;
        if(t && t.getAttribute && t.getAttribute('data-idx')!==null){
          var idx=parseInt(t.getAttribute('data-idx'),10);
          if(idx===state.current){
            e.preventDefault(); e.stopPropagation();
            addPointOnEdge(clientToImageXY(e), state.current);
          }
        }
      }
    }, true);

    document.addEventListener('keydown', function(e){
      var t = e.target; if(t && (t.tagName==='INPUT' || t.tagName==='TEXTAREA') && t.type!=='range') return;
      if(state.mode==='drawing'){
        if(e.key==='Backspace' || e.key==='Delete'){ e.preventDefault(); stepBack(); }
        if(e.key==='Enter'){ e.preventDefault(); finishDraw(); }
      } else if(state.current>=0 && (e.key==='Backspace' || e.key==='Delete')){ e.preventDefault(); stepBack(); }
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
    var b1=B('qt_undo','↶ Krok späť'), b2=B('qt_done','✓ Ukončiť'), b3=B('qt_in','＋ Priblížiť'), b4=B('qt_out','－ Vzdialiť'), b5=B('qt_smooth','◔ Zaobliť všetko');
    var row=document.createElement('div'); row.style.display='grid'; row.style.gridTemplateColumns='auto 1fr auto'; row.style.alignItems='center'; row.style.gap='6px'; row.style.background='#fff'; row.style.padding='6px'; row.style.border='1px solid #ccd0d4';
    var lab=L('qt_alpha_lab','Intenzita zaoblenia:'); var val=L('qt_alpha_val','<strong>0.18</strong>');
    var rng=document.createElement('input'); rng.type='range'; rng.min='0.05'; rng.max='0.35'; rng.step='0.01'; rng.value=String(ui.alpha);
    rng.addEventListener('input', function(){ ui.alpha=parseFloat(rng.value)||0.18; val.innerHTML='<strong>'+ui.alpha.toFixed(2)+'</strong>'; });
    row.appendChild(lab); row.appendChild(rng); row.appendChild(val);

    var box=document.createElement('div');
    box.style.background='#fff'; box.style.padding='6px'; box.style.border='1px solid #ccd0d4'; box.style.display='flex'; box.style.flexDirection='column'; box.style.gap='6px';
    ;[b1,b2,b3,b4,b5].forEach(function(b){ box.appendChild(b); });

    var wrap=document.createElement('div'); wrap.style.pointerEvents='auto'; wrap.appendChild(row); wrap.appendChild(box);
    bar.appendChild(wrap); document.body.appendChild(bar);

    b1.addEventListener('click', function(e){ e.preventDefault(); stepBack(); });
    b1.title = 'Počas kreslenia: odstráni posledný bod. V režime úprav: vráti posledné zaoblenie (Alt+Klik alebo Zaobliť všetko).';
    b2.addEventListener('click', function(e){ e.preventDefault(); finishDraw(); });
    b3.addEventListener('click', function(e){ e.preventDefault(); zoomBy(1.2); });
    b4.addEventListener('click', function(e){ e.preventDefault(); zoomBy(1/1.2); });
    b5.addEventListener('click', function(e){ e.preventDefault(); applyChaikin(); });
    b5.title = 'Zaobliť celý polygón (reaguje na intenzitu). Pre jeden roh: Alt+Klik na bod rohu. Pridanie bodu na hranu: Ctrl+Klik na hranu.';
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
    bindColorHexSync();

    if(!IMG||!SVG){ return; }

    window.DEV_MAP_EDITOR = { state: state, render: render, importFromSVG: importFromSVG, importFromJSON: importFromJSON };
    var onReady = function(){ setViewBox(); loadFromJSON(); bind(); render(); injectMiniBar(); try{ window.dispatchEvent(new CustomEvent('dev_map_editor_ready')); }catch(e){} };
    if(!IMG.complete){ IMG.addEventListener('load', onReady); } else { onReady(); }
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
})(jQuery);
