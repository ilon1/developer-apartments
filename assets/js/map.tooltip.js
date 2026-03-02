(function(){
  function $(s, c){ return (c||document).querySelector(s); }
  function $all(s, c){ return Array.prototype.slice.call((c||document).querySelectorAll(s)); }
  function pluralSk(n){ n=+n||0; if(n===1) return 'Voľný 1'; if(n>=2 && n<=4) return 'Voľné '+n; return 'Voľných '+n; }
  function makeTip(){ var d=document.createElement('div'); d.className='devapt-tooltip'; d.style.position='absolute'; d.style.pointerEvents='none'; d.style.zIndex=60; d.style.transform='translate(-50%, -100%)'; d.style.whiteSpace='nowrap'; d.style.textAlign='center'; return d; }
  function centroid(points){ var x=0,y=0,len=points.length; if(!len) return {x:0,y:0}; for(var i=0;i<len;i++){ var p=points[i]; x+=p[0]; y+=p[1]; } return {x:x/len,y:y/len}; }
  function parsePointsAttr(poly){ var pts=(poly.getAttribute('points')||'').trim().split(/\s+/).map(function(s){var a=s.split(',');return [parseFloat(a[0])||0, parseFloat(a[1])||0];}); return pts.filter(function(p){return p.length===2;}); }
  function color(free){ var root = document.documentElement; var freeC = getComputedStyle(root).getPropertyValue('--devapt-free-color').trim()||'#2e7d32'; var busyC = getComputedStyle(root).getPropertyValue('--devapt-busy-color').trim()||'#c62828'; return (free>0)?freeC:busyC; }
  function label(free, base){ var t=free>0? pluralSk(free) : 'Nedostupné'; return '<div class="devapt-tip-title">'+(base||'')+'</div><div class="devapt-tip-free">'+t+'</div>'; }
  function attach(root){ var host = root || document; var tooltip = makeTip(); document.body.appendChild(tooltip);
    function showFor(poly){ var pts=parsePointsAttr(poly); if(pts.length<3){ tooltip.style.display='none'; return; } var c=centroid(pts); var svg=poly.ownerSVGElement, vb=svg.getBoundingClientRect(); var vw=svg.viewBox.baseVal.width||vb.width, vh=svg.viewBox.baseVal.height||vb.height; var px={x: vb.left + (c.x*vb.width/(vw||1)), y: vb.top + (c.y*vb.height/(vh||1))}; var free = parseInt(poly.getAttribute('data-free')||'0',10)||0; var base = poly.getAttribute('data-tooltip') || poly.getAttribute('data-title') || ''; tooltip.innerHTML = label(free, base); tooltip.style.background=color(free); tooltip.style.color = 'var(--devapt-tip-text,#fff)'; tooltip.style.padding='6px 10px'; tooltip.style.borderRadius='4px'; tooltip.style.display='block'; tooltip.style.left=px.x+'px'; tooltip.style.top=px.y+'px'; }
    function hide(){ tooltip.style.display='none'; }
    $all('svg polygon[data-overlay]', host).forEach(function(poly){ poly.addEventListener('mouseenter', function(){ showFor(poly); }); poly.addEventListener('mouseleave', hide); poly.addEventListener('mousemove', function(){ showFor(poly); }); });
  }
  window.DevAptTooltip = { mount: attach };
})();
