(function(){
  function initMap(wrap){
    if(!wrap || wrap.__devMapInit) return; wrap.__devMapInit = true;
    var payload = wrap.dataset.payload ? JSON.parse(wrap.dataset.payload) : null;
    var imgUrl  = wrap.dataset.img || '';
    if(!payload) return;

    wrap.style.position='relative';

    // background image
    var bg=null;
    if(imgUrl){
      bg = new Image(); bg.src = imgUrl;
      bg.style.cssText='position:absolute;left:0;top:0;width:100%;height:100%;object-fit:contain;z-index:0';
      wrap.appendChild(bg);
    }

    var svgNS='http://www.w3.org/2000/svg';
    var svg=document.createElementNS(svgNS,'svg');
    svg.setAttribute('width','100%'); svg.setAttribute('height','100%');
    svg.style.display='block'; svg.style.position='relative'; svg.style.zIndex='1';
    wrap.appendChild(svg);

    function setViewBox(){ if(bg && bg.naturalWidth && bg.naturalHeight){ svg.setAttribute('viewBox','0 0 '+bg.naturalWidth+' '+bg.naturalHeight); } }
    if(bg){ if(bg.complete){ setViewBox(); } else { bg.onload=setViewBox; } }

    var tip=document.createElement('div'); tip.style.position='absolute'; tip.style.pointerEvents='none'; tip.style.zIndex='5'; wrap.appendChild(tip);

    function styleTooltipNode(node){
      var cs = getComputedStyle(wrap);
      node.style.background   = cs.getPropertyValue('--dev-tooltip-bg')   || '#222';
      node.style.color        = cs.getPropertyValue('--dev-tooltip-color')|| '#fff';
      node.style.padding      = cs.getPropertyValue('--dev-tooltip-pad')  || '8px';
      var fs = cs.getPropertyValue('--dev-tooltip-size') || '14px'; node.style.fontSize = fs.toString().trim();
      node.style.borderRadius = '6px'; node.style.boxShadow='0 2px 8px rgba(0,0,0,.15)';
    }

    // Auto-position tooltip INSIDE the visible SVG box (no overflow)
    function placeTooltip(node, x, y){
      var svgRect = svg.getBoundingClientRect();
      // preferred to the bottom-right of cursor
      var left = x + 12; var top = y + 12;
      // if overflowing to right -> place left
      if(left + node.offsetWidth > svgRect.width) left = x - node.offsetWidth - 12;
      // if overflowing to bottom -> place above
      if(top + node.offsetHeight > svgRect.height) top = y - node.offsetHeight - 12;
      // clamp to svg box
      if(left < 0) left = 6; if(top < 0) top = 6;
      node.style.left = left + 'px'; node.style.top = top + 'px';
    }

    function showTooltip(html, x, y){
      tip.innerHTML = '<div class="dev-map-tooltip">'+html+'</div>';
      var node = tip.firstChild; if(!node) return;
      styleTooltipNode(node); placeTooltip(node, x, y);
    }
    function hideTooltip(){ tip.innerHTML=''; }

    function shapeColor(s){ if(payload.colorMode==='override') return payload.color; return s.color || '#e91e63'; }

    // Reverted layout: title + count, like original implementation
    function makeTooltipHtml(s){
      var title = s.custom_title || s.display_label || '';
      var html  = '<span class="dev-tt-title">'+ title +'</span>';
      if(s.free_count !== undefined){
        var free = parseInt(s.free_count, 10);
        var text = (free > 0) ? (free + ' voľných') : 'Obsadený';
        html += '<span class="dev-tt-count">'+ text +'</span>';
      }
      return html;
    }

    function toPoints(points){ return points.map(function(p){return p[0]+','+p[1];}).join(' '); }

    (payload.shapes||[]).forEach(function(s){
      if(!s.points || !s.points.length) return;
      var poly=document.createElementNS(svgNS,'polygon');
      poly.setAttribute('points', toPoints(s.points));
      poly.setAttribute('fill', shapeColor(s)); poly.setAttribute('fill-opacity','0.35');
      poly.setAttribute('stroke','#fff'); poly.setAttribute('stroke-width','1');
      poly.style.cursor='pointer'; svg.appendChild(poly);

      poly.addEventListener('mousemove', function(e){ var r=svg.getBoundingClientRect(); showTooltip(makeTooltipHtml(s), e.clientX-r.left, e.clientY-r.top); });
      poly.addEventListener('mouseleave', hideTooltip);
      poly.addEventListener('click', function(){ if(payload.action==='navigate' && s.link){ window.location.href = s.link; } else { window.dispatchEvent(new CustomEvent('devTermSelect', { detail: { termId: s.target_id || null } })); } });
    });
  }

  function scan(){ document.querySelectorAll('.dev-apt-map[data-payload]').forEach(initMap); }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', scan); } else { scan(); }
})();
