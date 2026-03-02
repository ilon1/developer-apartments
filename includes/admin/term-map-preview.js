(function(){
  function draw(div){ var json=div.getAttribute('data-json')||'[]'; var img=div.getAttribute('data-img')||''; var data=[]; try{ data=JSON.parse(json);}catch(e){ data=[]; }
    var wrap=document.createElement('div'); wrap.style.position='relative'; wrap.style.maxWidth='100%'; wrap.style.overflow='hidden';
    var bg=new Image(); bg.src=img; bg.style.width='100%'; bg.style.display='block';
    var svgNS='http://www.w3.org/2000/svg'; var svg=document.createElementNS(svgNS,'svg'); svg.setAttribute('width','100%'); svg.style.position='absolute'; svg.style.left=0; svg.style.top=0; svg.style.pointerEvents='none';
    bg.onload=function(){ svg.setAttribute('viewBox','0 0 '+bg.naturalWidth+' '+bg.naturalHeight); };
    wrap.appendChild(bg); wrap.appendChild(svg); div.innerHTML=''; div.appendChild(wrap);
    function toPointsAttr(pts){ return pts.map(function(p){ return (Array.isArray(p)? p:[p.x,p.y]).join(','); }).join(' '); }
    (data||[]).forEach(function(s){ var pts=s&&(s.points||s.coords||[]); if(!pts || (pts.length<3)) return; var poly=document.createElementNS(svgNS,'polygon'); poly.setAttribute('points', toPointsAttr(pts)); poly.setAttribute('fill', s.color||'#1e88e5'); poly.setAttribute('fill-opacity','0.35'); poly.setAttribute('stroke', s.color||'#1e88e5'); poly.setAttribute('stroke-width','1'); svg.appendChild(poly); });
  }
  function init(){
    var d=document.getElementById('dev-apt-preview'); if(d) draw(d);
    var cb=document.getElementById('dev_coming_soon'); var status=document.getElementById('dev_coming_soon_status');
    if(cb && status && typeof devAptTermPreview !== 'undefined' && devAptTermPreview.ajaxUrl && devAptTermPreview.nonce){
      cb.addEventListener('change', function(){
        var termId=cb.getAttribute('data-term-id')||''; if(!termId) return;
        status.textContent='';
        var formData=new FormData(); formData.append('action','dev_apt_save_coming_soon'); formData.append('nonce', devAptTermPreview.nonce); formData.append('term_id', termId); formData.append('value', cb.checked ? '1' : '0');
        fetch(devAptTermPreview.ajaxUrl, { method:'POST', body: formData, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){ status.textContent= data.success ? (devAptTermPreview.i18n && devAptTermPreview.i18n.saved ? devAptTermPreview.i18n.saved : 'Uložené') : (devAptTermPreview.i18n && devAptTermPreview.i18n.error ? devAptTermPreview.i18n.error : 'Chyba'); status.style.color= data.success ? '#00a32a' : '#d63638'; setTimeout(function(){ status.textContent=''; }, 2500); })
          .catch(function(){ status.textContent= devAptTermPreview.i18n && devAptTermPreview.i18n.error ? devAptTermPreview.i18n.error : 'Chyba'; status.style.color='#d63638'; });
      });
    }
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
