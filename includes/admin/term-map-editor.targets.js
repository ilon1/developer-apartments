(function($){
  function $$(s, c){ return (c||document).querySelector(s); }
  function state(){ return (window.DEV_MAP_EDITOR && window.DEV_MAP_EDITOR.state) || null; }
  var userModeOverrideUntil = 0;
  function now(){ return Date.now(); }

  function writeJson(){ var st=state(); if(!st) return; var out=(st.shapes||[]).map(function(s){ return { uid:s.uid, points:(s.points||[]).map(function(p){return [Math.round(p.x),Math.round(p.y)];}), color:s.color||'#e91e63', target_id:s.target_id||null, target_type:s.target_type||null, custom_title:s.custom_title||'' }; }); var h=$$('#dev_map_json'); if(h) h.value=JSON.stringify(out); }

  function mountUI(){ var mount=$$('#dev_targets_mount'); if(!mount) return; if($$('#dev-target-mode')) return; var wrap=document.createElement('div'); wrap.className='dev-target-wrap'; wrap.innerHTML=[
    '<div class="dev-target-row">',
    ' <label style="margin-right:6px">Priradiť k režim:</label>',
    ' <label class="dev-radio"><input type="radio" name="dev_target_mode" id="dev-target-mode" value="term" checked> Štruktúra</label>',
    ' <label class="dev-radio"><input type="radio" name="dev_target_mode" value="apartment"> Byt (DB)</label>',
    '</div>',
    '<div class="dev-target-row">',
    ' <input type="search" id="dev_target_search" class="regular-text" placeholder="Hľadať byt (kód, názov)…" style="display:none;">',
    ' <select id="dev_target_select" class="widefat"></select>',
    ' <button type="button" class="button" id="dev_reload_targets_clean">Znova načítať možnosti</button>',
    '</div>'
  ].join(''); mount.appendChild(wrap); }

  function addOptions(sel, items){ (items||[]).forEach(function(it){ var o=document.createElement('option'); o.value=String(it.id); o.textContent=it.label; o.setAttribute('data-type', it.type); sel.appendChild(o); }); }
  function mode(){ var r=document.querySelector('input[name="dev_target_mode"]:checked'); return r ? r.value : 'term'; }
  function setMode(m){ document.querySelectorAll('input[name="dev_target_mode"]').forEach(function(r){ r.checked=(r.value===m); }); }

  function populate(m){ var sel=$$('#dev_target_select'); if(!sel) return; sel.innerHTML=''; var root=(window.DevApt && DevApt.root) || 0; var def=document.createElement('option'); def.value=''; def.textContent='— vyberte —'; sel.appendChild(def);
    if(m==='apartment'){ $$('#dev_target_search').style.display='inline-block'; if(root){ $.getJSON(DevApt.ajax,{action:'devapt_get_apartments', root:root}).done(function(resp){ if(resp&&resp.success){ addOptions(sel, resp.data.items||[]); syncToCurrent(true); } }); } }
    else { $$('#dev_target_search').style.display='none'; $.getJSON(DevApt.ajax,{action:'devapt_get_targets', root:root}).done(function(resp){ if(resp&&resp.success){ addOptions(sel, resp.data.items||[]); syncToCurrent(true); } }); }
  }

  function searchDB(q){ var sel=$$('#dev_target_select'); if(!sel) return; $.getJSON(DevApt.ajax,{action:'devapt_search_apartments', q:q, limit:100}).done(function(resp){ if(resp&&resp.success){ sel.innerHTML=''; var d=document.createElement('option'); d.value=''; d.textContent='— vyberte —'; sel.appendChild(d); addOptions(sel, resp.data.items||[]); syncToCurrent(true); } }); }

  function syncToCurrent(fromPopulate){ var st=state(); var sel=$$('#dev_target_select'); if(!st||!sel) return; var cur = (st.current>=0? st.shapes[st.current] : null); var wantFlipDelay = (now() < userModeOverrideUntil) ? false : true; if(!cur){ if(fromPopulate) sel.value=''; return; }
    // only auto-flip if user recently neprepínal a shape already has a target
    if(wantFlipDelay && cur.target_type){ var need = (cur.target_type==='post')?'apartment':'term'; if(mode()!==need){ setMode(need); populate(need); return; } }
    sel.value = cur.target_id ? String(cur.target_id) : '';
  }

  function initLeafBehavior(){ var root=(window.DevApt && DevApt.root) || 0; if(!root) return; $.getJSON(DevApt.ajax,{action:'devapt_term_is_leaf', term_id:root}).done(function(resp){ if(resp&&resp.success && resp.data.leaf){ // leaf: force APARTMENT only
      setMode('apartment');
      var termRadio=document.querySelector('input[name="dev_target_mode"][value="term"]').parentElement; if(termRadio) termRadio.style.display='none';
      populate('apartment');
    } else { populate(mode()); }
  }); }

  function bind(){
    // user mode change – set small timeout to prevent auto-flip
    document.addEventListener('change', function(e){ if(e.target && e.target.name==='dev_target_mode'){ userModeOverrideUntil = now()+3000; populate(mode()); }});
    document.addEventListener('change', function(e){ if(e.target && e.target.id==='dev_target_select'){ var st=state(); if(!st||st.current<0) return; var cur=st.shapes[st.current]; var v=e.target.value; var t=e.target.selectedOptions[0] && e.target.selectedOptions[0].getAttribute('data-type'); if(v){ cur.target_type=(t==='post')?'post':'term'; cur.target_id=parseInt(v,10)||null; } else { cur.target_type=null; cur.target_id=null; } writeJson(); }});
    document.addEventListener('click', function(e){ if(e.target && e.target.id==='dev_reload_targets_clean'){ e.preventDefault(); populate(mode()); }});
    var timer=null; document.addEventListener('input', function(e){ if(e.target && e.target.id==='dev_target_search'){ var q=e.target.value.trim(); clearTimeout(timer); timer=setTimeout(function(){ searchDB(q); }, 250); }});
    var ed=window.DEV_MAP_EDITOR; if(ed && ed.render && !ed.__targetsHooked){ var orig=ed.render; ed.render=function(){ try{ orig.apply(ed, arguments); } finally { syncToCurrent(false); } }; ed.__targetsHooked=true; }
  }

  function init(){ mountUI(); bind(); initLeafBehavior(); }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
})(jQuery);
