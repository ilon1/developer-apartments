(function(){
  function initTable(box){ if(!box || box.__devInit) return; box.__devInit = true;
    var storageKey = 'devTableSort:'+location.pathname+':'+(box.id||'');
    function saveSort(key,dir){ try{ localStorage.setItem(storageKey, JSON.stringify({key:key,dir:dir})); }catch(e){} }
    function loadSort(){ try{ return JSON.parse(localStorage.getItem(storageKey)||'null'); }catch(e){ return null; } }
    box.addEventListener('click', function(e){ var tr=e.target.closest('tr[data-href]'); if(tr){ window.location.href=tr.getAttribute('data-href'); } });
    var enableSort = box.dataset.sort==='1'; var ths = box.querySelectorAll('thead th[data-sortable="1"]');
    function sortRows(key, dir){ var tb=box.querySelector('tbody'); if(!tb) return; var rows=Array.from(tb.querySelectorAll('tr')); rows.sort(function(a,b){ var av=a.getAttribute('data-'+key)||'', bv=b.getAttribute('data-'+key)||''; var na=parseFloat(av), nb=parseFloat(bv); var bothNum = !isNaN(na) && !isNaN(nb); if(bothNum){ return dir==='asc'? na-nb : nb-na; } return dir==='asc' ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av)); }); rows.forEach(function(r){ tb.appendChild(r); }); saveSort(key,dir); }
    if(enableSort){ ths.forEach(function(th){ th.style.cursor='pointer'; th.addEventListener('click', function(){ var key = th.getAttribute('data-key'); var dir = th.getAttribute('data-dir')==='asc' ? 'desc' : 'asc'; ths.forEach(function(t){ t.removeAttribute('data-dir'); }); th.setAttribute('data-dir', dir); sortRows(key, dir); paginate(); applyFilters(); }); }); var last = loadSort(); if(last){ var target = Array.from(ths).find(function(th){return th.getAttribute('data-key')===last.key;}); if(target){ target.setAttribute('data-dir', last.dir); sortRows(last.key, last.dir); } } }
    var enableFilters = box.dataset.filters==='1';
    function rowVisible(r){ if(box.dataset.fStatus==='1'){ var fs = box.querySelector('.f-status'); var sv = fs ? fs.value : ''; if(sv && (r.getAttribute('data-status')||'') !== sv) return false; }
      if(box.dataset.fStructure==='1'){ var fst = box.querySelector('.f-structure'); var stv = fst ? fst.value : ''; if(stv){ var terms = (r.getAttribute('data-terms')||'').split(',').map(function(v){return v.trim();}); if(terms.indexOf(stv)===-1) return false; } }
      if(box.dataset.fRooms==='1'){ var fr = box.querySelector('.f-rooms'); var rv = fr ? fr.value : ''; if(rv){ var typeIds = (r.getAttribute('data-type-ids')||'').split(',').map(function(v){return v.trim();}); if(typeIds.indexOf(rv)===-1) return false; } }
      if(box.dataset.fArea==='1'){ var amin=parseFloat((box.querySelector('.f-amin')||{}).value||''); var amax=parseFloat((box.querySelector('.f-amax')||{}).value||''); var tot=parseFloat(r.getAttribute('data-tot')||'0'); if(!isNaN(amin) && tot<amin) return false; if(!isNaN(amax) && tot>amax) return false; }
      if(box.dataset.fPrice==='1'){ var pmin=parseFloat((box.querySelector('.f-pmin')||{}).value||''); var pmax=parseFloat((box.querySelector('.f-pmax')||{}).value||''); var price=parseFloat(r.getAttribute('data-price')||'0'); if(!isNaN(pmin) && price<pmin) return false; if(!isNaN(pmax) && price>pmax) return false; }
      if(box.dataset.fSearch==='1'){ var q=(box.querySelector('.f-q')||{}).value||''; q=q.toLowerCase().trim(); if(q){ var text=(r.querySelector('td:first-child')||{}).innerText||''; if(text.toLowerCase().indexOf(q)===-1) return false; } } return true; }
    function applyFilters(){ if(!enableFilters){ paginate(); return; } var rows=Array.from(box.querySelectorAll('tbody tr')); rows.forEach(function(r){ r.style.display = rowVisible(r) ? '' : 'none'; }); paginate(); }
    if(enableFilters){ box.addEventListener('input', function(e){ if(e.target.closest('.dev-filters')) applyFilters(); }); box.addEventListener('change', function(e){ if(e.target.closest('.dev-filters')) applyFilters(); }); }
    window.addEventListener('devTermSelect', function(e){ var termId = e.detail && e.detail.termId ? String(e.detail.termId) : ''; if(!termId) return; var rows=Array.from(box.querySelectorAll('tbody tr')); rows.forEach(function(r){ var ids = (r.getAttribute('data-terms')||'').split(',').map(function(v){return v.trim();}); var match = ids.indexOf(termId)>=0; r.style.display = match && (!enableFilters || rowVisible(r)) ? '' : 'none'; }); paginate(); });
    // MAP → TABLE highlight (by term ids or by single post id when on floor level)
    window.addEventListener('devTermHover', function(e){ var terms = (e.detail && e.detail.terms) || []; var postId = (e.detail && e.detail.postId) ? String(e.detail.postId) : ''; var rows=Array.from(box.querySelectorAll('tbody tr')); rows.forEach(function(r){ var on = false; if(postId){ on = (r.getAttribute('data-post-id')||'') === postId; } else if(terms.length){ var ids=(r.getAttribute('data-terms')||'').split(',').map(function(v){return v.trim();}); on = ids.some(function(id){return terms.indexOf(id)>=0;}); } r.classList.toggle('highlight', on); }); });
    // TABLE → MAP highlight (send postId so map highlights only that apartment, not whole floor)
    box.addEventListener('mouseover', function(e){ var tr = e.target.closest('tbody tr'); if(!tr) return; var ids=(tr.getAttribute('data-terms')||'').split(',').map(function(v){return v.trim();}).filter(Boolean); var postId = tr.getAttribute('data-post-id')||null; window.dispatchEvent(new CustomEvent('devTermHover',{detail:{terms:ids, postId: postId}})); }, true);
    box.addEventListener('mouseleave', function(){ window.dispatchEvent(new CustomEvent('devTermHover', { detail: { terms: [], postId: null } })); }, true);

    var hoverPreview = box.dataset.hoverPreview==='1';
    if(hoverPreview){
      var prevEl = box.querySelector('.dev-table-hover-preview');
      if(prevEl){
        var hideTm;
        var isTouchOrNarrow = function(){ return window.innerWidth < 981; };
        var updatePosition = function(prevEl, x, y, e){
          if(isTouchOrNarrow()) return;
          var pw = 220, ph = 165;
          var gap = 12;
          var cx = e ? e.clientX : (typeof x==='number' ? x : 0);
          var cy = e ? e.clientY : (typeof y==='number' ? y : 0);
          var xPos = cx - pw / 2;
          var yPos = cy - ph - gap;
          if(yPos < 8) yPos = cy + gap;
          if(yPos + ph > window.innerHeight - 8) yPos = window.innerHeight - ph - 8;
          if(yPos < 8) yPos = 8;
          if(xPos + pw > window.innerWidth - 8) xPos = window.innerWidth - pw - 8;
          if(xPos < 8) xPos = 8;
          prevEl.style.left = xPos + 'px';
          prevEl.style.top = yPos + 'px';
        };
        box.addEventListener('mouseover', function(e){
          if(isTouchOrNarrow()) return;
          var tr = e.target.closest('tbody tr');
          if(!tr) return;
          var url = tr.getAttribute('data-image-url');
          if(!url){ prevEl.innerHTML=''; prevEl.style.opacity='0'; return; }
          clearTimeout(hideTm);
          prevEl.innerHTML = '';
          var img = document.createElement('img');
          img.src = url;
          img.alt = '';
          prevEl.appendChild(img);
          if(prevEl.parentNode !== document.body) document.body.appendChild(prevEl);
          updatePosition(prevEl, null, null, e);
          prevEl.style.opacity = '1';
        }, true);
        box.addEventListener('mousemove', function(e){
          if(isTouchOrNarrow()) return;
          var tr = e.target.closest('tbody tr');
          if(!tr || !prevEl.firstElementChild) return;
          updatePosition(prevEl, null, null, e);
        }, true);
        box.addEventListener('mouseleave', function(e){
          if(!e.relatedTarget || !box.contains(e.relatedTarget)){
            hideTm = setTimeout(function(){ prevEl.style.opacity = '0'; }, 80);
          }
        }, true);
      }
    }
    var pageSize = parseInt(box.dataset.pagesize||'0',10); var pager = box.querySelector('.dev-pagination');
    function paginate(){ if(!pageSize || pageSize<=0){ if(pager) pager.style.display='none'; return; } var rows=Array.from(box.querySelectorAll('tbody tr')).filter(function(r){return r.style.display!=='none';}); var pages=Math.ceil(rows.length/pageSize); var page=parseInt(pager.getAttribute('data-page')||'1',10); if(page<1) page=1; if(page>pages) page=pages||1; rows.forEach(function(r,idx){ r.style.display = (Math.ceil((idx+1)/pageSize)===page)? '' : 'none'; }); pager.innerHTML=''; for(var i=1;i<=pages;i++){ var btn=document.createElement('button'); btn.textContent=i; btn.style.cssText='padding:4px 8px;border:1px solid #ddd;background:#fff;cursor:pointer'; if(i===page) btn.style.background='#eee'; (function(n){ btn.addEventListener('click', function(){ pager.setAttribute('data-page', String(n)); paginate(); }); })(i); pager.appendChild(btn); } pager.style.display = pages>1 ? 'flex' : 'none'; }
    paginate(); applyFilters();
    if(box.dataset.exportCsv==='1'){ var csvBtn = box.querySelector('.f-csv'); if(csvBtn){ csvBtn.addEventListener('click', function(){ var headers=Array.from(box.querySelectorAll('thead th')).map(function(th){return(th.textContent||'').trim();}); var rows=Array.from(box.querySelectorAll('tbody tr')).filter(function(r){return r.style.display!=='none';}); var data=rows.map(function(r){return Array.from(r.children).map(function(td){return (td.innerText||'').replace(/\s+/g,' ').trim();});}); var csv=[headers].concat(data).map(function(arr){return arr.map(function(cell){cell=cell.replace(/"/g,'""');return /[,"];|\n/.test(cell)?'"'+cell+'"':cell;}).join(',');}).join('\n'); var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'}); var a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='apartmany-export.csv'; a.click(); URL.revokeObjectURL(a.href); }); } }
  }
  function scan(){ document.querySelectorAll('.dev-apartment-table-wrapper').forEach(initTable); }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', scan); } else { scan(); }
})();
