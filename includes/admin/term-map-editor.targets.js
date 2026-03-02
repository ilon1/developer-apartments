(function($){
  function qs(s, c){return (c||document).querySelector(s);} 
  function qsa(s, c){return Array.prototype.slice.call((c||document).querySelectorAll(s));}
  function state(){ return (window.DEV_MAP_EDITOR && window.DEV_MAP_EDITOR.state) || null; }
  function ed(){ return window.DEV_MAP_EDITOR || null; }

  function detectRoot(){
    var r=0; try{ if(window.DevApt && DevApt.root) r=parseInt(DevApt.root,10)||0; }catch(e){}
    if(!r){ var t=qs('input[name="tag_ID"], #tag_ID, input[name="term_id"], #term_id'); if(t) r=parseInt(t.value,10)||0; }
    return r||0;
  }

  function findJsonField(){
    var cand = qsa('#dev_map_json, textarea[name="dev_map_json"], textarea[name="dev_map_data"], #dev_map_data, textarea[id*="dev_map"], input[name="dev_map_data"], input[id="dev_map_data"]');
    return cand.length? cand[0] : null;
  }

  // ===== insert panel – Editor máp má #dev_targets_mount alebo .dev-side-col =====
  function findPropsContainer(){
    var candidates = ['#dev_targets_mount', '.dev-side-col', '#dev_shape_props', '.dev-shape-props', '.map-props', '#postbox-container-1', '#side-sortables', '.editor-styles-wrapper'];
    for(var i=0;i<candidates.length;i++){ var el = qs(candidates[i]); if(el) return el; }
    var el2 = qs('#poststuff #side-sortables'); if(el2) return el2;
    return qs('#dev_map_sidebar') || qs('.dev-map-sidebar') || document.body;
  }
  function placeAfterHover(panel){
    var sel = 'input[type="color"][name*="hover"], input[type="text"][id*="hover"], input[type="text"][name*="hover"], .hover-picker';
    var hoverInput = qs(sel);
    if(hoverInput){ var anchor = hoverInput.closest('tr, .field, .components-base-control, label, div') || hoverInput; anchor.insertAdjacentElement('afterend', panel); return true; }
    return false;
  }
  function ensurePanel(){
    var host = qs('#dev_target_panel'); if(host) return host;
    host = document.createElement('div'); host.id='dev_target_panel'; host.style.margin='10px 0'; host.style.width='100%';
    host.innerHTML = '\n<fieldset class="dev-target-fieldset" style="border:1px solid #ddd;padding:8px 10px">\n  <legend style="font-weight:600">Cieľ polygónu</legend>\n  <div class="dev-target-mode-wrap">\n    <label style="margin-right:12px"><input type="radio" name="dev_target_mode" value="term" checked> <span class="dev-target-label-term">Štruktúra (nižšia kategória v štruktúre)</span></label>\n    <label class="dev-target-label-byt-wrap"><input type="radio" name="dev_target_mode" value="apartment"> <span class="dev-target-label-byt">Byt (len na poschodí)</span></label>\n  </div>\n  <div style="margin-top:8px">\n    <input type="text" id="dev_target_search" placeholder="Hľadať byt..." style="display:none;min-width:220px;margin-right:6px"/>\n    <select id="dev_target_select" style="min-width:260px;max-width:100%"></select>\n    <button type="button" class="button" id="dev_reload_targets_clean" title="Znovu načítať">↻</button>\n  </div>\n</fieldset>';
    var props = findPropsContainer(); if(!placeAfterHover(host)) props.insertBefore(host, props.firstChild); return host;
  }

  var isLeafCached = true;
  function setLeafMode(isLeaf){
    isLeafCached = !!isLeaf;
    var wrap = qs('.dev-target-label-byt-wrap');
    if(!wrap) return;
    wrap.style.display = isLeaf ? '' : 'none';
    if(!isLeaf){ setMode('term'); }
  }

  function fetchLeafAndPopulate(){
    var root = detectRoot();
    if(!root){ populate(mode()); return; }
    $.getJSON(ajaxurl || (window.DevApt && DevApt.ajax) || '', { action: 'devapt_term_is_leaf', term_id: root }).done(function(resp){
      var isLeaf = resp && resp.success && resp.data && resp.data.leaf;
      setLeafMode(!!isLeaf);
      populate(mode());
    }).fail(function(){ setLeafMode(false); populate('term'); });
  }

  function performSave(btn){
    if(!btn) return;
    try { writeJson(); } catch(err) {}
    var jsonEl = findJsonField(); if(!jsonEl){ setSaveResult(btn, 'Chýba pole pre dáta.', true); return; }
    var json = (jsonEl.value || '').trim();
    var termId = (btn.getAttribute && btn.getAttribute('data-term')) ? parseInt(btn.getAttribute('data-term'), 10) : detectRoot();
    if(!termId){ setSaveResult(btn, 'Chýba term_id.', true); return; }
    var resultEl = qs('#dev_save_result'); if(resultEl) resultEl.textContent = 'Ukladám…';
    var ajaxUrl = (typeof ajaxurl !== 'undefined' && ajaxurl) ? ajaxurl : (window.DevApt && DevApt.ajax) || '';
    var nonce = (window.DevApt && DevApt.save_nonce) || '';
    $.post(ajaxUrl, { action: 'dev_save_term_map', nonce: nonce, term_id: termId, json: json })
      .done(function(resp){
        var ok = resp && resp.success !== false;
        var msg = (resp && resp.data) ? (typeof resp.data === 'object' && resp.data.message ? resp.data.message : String(resp.data)) : (ok ? 'Uložené.' : 'Chyba.');
        setSaveResult(btn, ok ? (msg || 'Uložené.') : msg, !ok);
      })
      .fail(function(xhr){ var d = xhr.responseJSON && xhr.responseJSON.data; setSaveResult(btn, d ? String(d) : 'Chyba siete.', true); });
  }

  function ensureSaveHook(){
    var fld = findJsonField(); if(!fld) return;
    var form = fld.closest('form') || qs('form#edittag') || qs('form');
    if(form && !form.__devPersistHooked){ form.addEventListener('submit', function(){ try{ writeJson(); }catch(e){} }); form.__devPersistHooked=true; }
    if(document.__devSaveDelegated) return;
    document.__devSaveDelegated = true;
    document.addEventListener('click', function(e){
      var btn = e.target.closest && e.target.closest('#dev_save_map, [data-action="dev_save_map"]');
      if(!btn) return;
      e.preventDefault();
      performSave(btn);
    });
  }
  function setSaveResult(btn, text, isError){
    var resultEl = qs('#dev_save_result'); if(!resultEl) return;
    resultEl.textContent = text || '';
    resultEl.style.color = isError ? '#b32d2e' : '#00a32a';
    resultEl.style.marginLeft = '8px';
    setTimeout(function(){ resultEl.textContent = ''; }, 4000);
  }

  function addOptions(sel, items){
    if(!sel || !items) return;
    var seen = {};
    qsa('option', sel).forEach(function(o){ seen[o.value] = true; });
    items.forEach(function(it){
      var v = String(it.id);
      if(seen[v]) return;
      seen[v] = true;
      var o = document.createElement('option');
      o.value = v;
      o.textContent = it.label;
      o.setAttribute('data-type', it.type);
      sel.appendChild(o);
    });
  }
  function mode(){ var r=qs('input[name="dev_target_mode"]:checked'); return r ? r.value : 'term'; }
  function setMode(m){ qsa('input[name="dev_target_mode"]').forEach(function(r){ r.checked=(r.value===m); }); }

  function populate(m){ var sel=qs('#dev_target_select'); if(!sel) return; sel.innerHTML='';
    var root = detectRoot(); var def=document.createElement('option'); def.value=''; def.textContent='— vyberte —'; sel.appendChild(def);
    if(m==='apartment'){
      var sf=qs('#dev_target_search'); if(sf) sf.style.display='inline-block';
      $.getJSON(ajaxurl || (window.DevApt && DevApt.ajax) || '', {action:'devapt_get_apartments', root:root}).done(function(resp){ if(resp&&resp.success){ addOptions(sel, resp.data.items||[]); syncToCurrent(true); } });
    } else {
      var sfield=qs('#dev_target_search'); if(sfield) sfield.style.display='none';
      $.getJSON(ajaxurl || (window.DevApt && DevApt.ajax) || '', {action:'devapt_get_targets', root:root}).done(function(resp){
        if(resp&&resp.success){
          var items = resp.data.items||[];
          addOptions(sel, items);
          syncToCurrent(true);
          if(items.length===0 && root && isLeafCached){ setMode('apartment'); userModeOverrideUntil=now()+2000; populate('apartment'); }
        }
      });
    }
  }
  function searchDB(q){ var sel=qs('#dev_target_select'); if(!sel) return; var root=detectRoot(); $.getJSON(ajaxurl || (window.DevApt && DevApt.ajax) || '', {action:'devapt_search_apartments', q:q, root:root, limit:100}).done(function(resp){ if(resp&&resp.success){ sel.innerHTML=''; var d=document.createElement('option'); d.value=''; d.textContent='— vyberte —'; sel.appendChild(d); addOptions(sel, resp.data.items||[]); syncToCurrent(true); } }); }

  function writeJson(){ var st=state(); if(!st) return; (st.shapes||[]).forEach(function(s){ if(!s.uid) s.uid='p'+Math.floor(Math.random()*1e9); });
    var out=(st.shapes||[]).map(function(s){ return { uid:s.uid, points:(s.points||[]).map(function(p){return [Math.round(p.x),Math.round(p.y)];}), color:s.color||'#9d9c7e', hover:s.hover||'#26a69a', no_stroke:!!s.no_stroke, target_id:(typeof s.target_id!=='undefined'? s.target_id : null), target_type:(typeof s.target_type!=='undefined'? s.target_type : null), custom_title:s.custom_title||'', tooltip:s.tooltip||'' }; });
    var h=findJsonField(); if(h) h.value=JSON.stringify(out);
  }

  function preSaveValidate(){ var st=state(); if(!st) return true; writeJson(); var missing=(st.shapes||[]).filter(function(s){return !s.target_id||!s.target_type;}).length; if(missing>0){ return confirm('Pozor: '+missing+' polygón(ov) nemá priradený cieľ (byt alebo štruktúra). Chcete aj tak uložiť?'); } return true; }

  var userModeOverrideUntil=0; function now(){return Date.now();}
  function syncToCurrent(fromPopulate){ var st=state(); var sel=qs('#dev_target_select'); if(!st||!sel) return; var cur=(st.current>=0? st.shapes[st.current]:null); var allowAutoFlip=(now()>=userModeOverrideUntil); if(!cur){ if(fromPopulate) sel.value=''; return; } if(allowAutoFlip && cur.target_type){ var need=(cur.target_type==='post')?'apartment':'term'; if(mode()!==need){ setMode(need); populate(need); } }
    var val=cur.target_id? String(cur.target_id):'';
    if(val){ var found=false; qsa('option',sel).forEach(function(o){ if(o.value===val) found=true; });
      if(!found){ var o=document.createElement('option'); o.value=val; o.textContent=cur.custom_title||(cur.target_type==='post'?'Byt #'+val:'Štruktúra #'+val); o.setAttribute('data-type',cur.target_type||'term'); sel.appendChild(o); } }
    sel.value=val;
  }

  function highlight(){ var E=ed(); var st=state(); if(!E||!E.render||!st) return; var cur=st.current; var assigned={}; (st.shapes||[]).forEach(function(s){ assigned[s.uid]=!!(s.target_id&&s.target_type); });
    var svg = document.getElementById('dev_svg'); if(!svg) return;
    qsa('polygon[data-uid]', svg).forEach(function(el){ var uid=el.getAttribute('data-uid'); var idx=el.getAttribute('data-idx'); var isSelected=idx!==null && parseInt(idx,10)===cur;
      if(isSelected){ el.style.stroke='#1e88e5'; el.style.strokeWidth='4px'; el.style.strokeOpacity='1'; el.style.fillOpacity='0.45'; return; }
      var ok=assigned[uid]; el.style.stroke= ok?'#2e7d32':'#c62828'; el.style.strokeWidth='3px'; el.style.strokeOpacity= ok?'0.9':'0.8'; });
  }

  function patchEditor(){ var E=ed(); if(!E||!E.render||E.__patchedTargets) return; var orig=E.render; E.render=function(){ try{ orig.apply(E, arguments); } finally { syncToCurrent(false); highlight(); ensureSaveHook(); } }; E.__patchedTargets=true; }

  function doBind(){
    ensurePanel();
    fetchLeafAndPopulate();
    patchEditor();
    ensureSaveHook();
  }

  function bind(){
    doBind();
    document.addEventListener('dev_map_editor_ready', function(){ patchEditor(); ensureSaveHook(); });
    document.addEventListener('dev_map_selection_changed', function(){
      var st=state(); if(!st) return;
      var run=function(){ syncToCurrent(false); highlight(); };
      if(typeof requestAnimationFrame!=='undefined') requestAnimationFrame(function(){ requestAnimationFrame(run); });
      else setTimeout(run, 0);
    });
    document.addEventListener('change', function(e){ if(e.target && e.target.name==='dev_target_mode'){ userModeOverrideUntil=now()+3000; populate(mode()); } if(e.target && e.target.id==='dev_target_select'){ var st=state(); if(!st||st.current<0) return; var cur=st.shapes[st.current]; var v=e.target.value; var t=e.target.selectedOptions[0] && e.target.selectedOptions[0].getAttribute('data-type'); if(v){ cur.target_type=(t==='post')?'post':'term'; cur.target_id=parseInt(v,10)||null; } else { cur.target_type=null; cur.target_id=null; } writeJson(); highlight(); }});
    var timer=null; document.addEventListener('input', function(e){ if(e.target && e.target.id==='dev_target_search'){ var q=e.target.value.trim(); clearTimeout(timer); timer=setTimeout(function(){ searchDB(q); }, 250); }});
    document.addEventListener('click', function(e){ if(e.target && e.target.id==='dev_reload_targets_clean'){ e.preventDefault(); populate(mode()); }});
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})(jQuery);
