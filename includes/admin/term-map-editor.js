jQuery(function($){
  if(typeof DevAptAdmin==='undefined') return;
  var term = new URLSearchParams(location.search).get('term_id');
  var $img = $('#dev_floor_img');
  var $svg = $('#dev_svg');
  var svgNS = 'http://www.w3.org/2000/svg';

  var shapes = []; // {points:[[x,y],...], color:'#hex', target_id, target_type, custom_title}
  var current = null; // index current shape
  var drag = null;   // {type:'vertex', si, vi}

  // Init from JSON
  try{ var initial = $('#dev_map_json').val(); if(initial){ shapes = JSON.parse(initial); } }catch(e){ shapes=[]; }

  function redraw(){
    $svg.empty();
    var vbSet=false;
    if($img[0] && $img[0].naturalWidth){
      $svg.attr('viewBox','0 0 '+$img[0].naturalWidth+' '+$img[0].naturalHeight);
      vbSet=true;
    }
    shapes.forEach(function(s,idx){
      var poly = document.createElementNS(svgNS,'polygon');
      poly.setAttribute('points', s.points.map(function(p){return p.join(',');}).join(' '));
      poly.setAttribute('fill', s.color || '#e91e63');
      poly.setAttribute('fill-opacity','0.35');
      poly.setAttribute('stroke', (idx===current)?'#000':'#fff');
      poly.setAttribute('stroke-width', (idx===current)?'2':'1');
      poly.setAttribute('data-idx', idx);
      $svg.append(poly);

      // Draw handles for current shape
      if(idx===current){
        (s.points||[]).forEach(function(pt,vi){
          var c=document.createElementNS(svgNS,'circle');
          c.setAttribute('cx',pt[0]); c.setAttribute('cy',pt[1]);
          c.setAttribute('r',6); c.setAttribute('fill','#fff'); c.setAttribute('stroke','#000'); c.setAttribute('stroke-width','1.5');
          c.setAttribute('class','dev-handle');
          c.setAttribute('data-vi',vi); c.setAttribute('data-idx',idx);
          $svg.append(c);
        });
      }
    });
  }

  function relCoords(evt){
    var rect = $svg[0].getBoundingClientRect();
    var x = evt.clientX - rect.left; var y = evt.clientY - rect.top;
    var rw = $img[0].naturalWidth / $img.width();
    var rh = $img[0].naturalHeight / $img.height();
    return [ Math.round(x*rw), Math.round(y*rh) ];
  }

  function setFormFrom(idx){
    var s = shapes[idx]||{}; current = idx;
    $('#dev_title').val(s.custom_title||'');
    $('#dev_color').val(s.color||'#e91e63');
    if(s.target_id){ $('#dev_target').val(s.target_type+':'+s.target_id); }
    redraw();
  }

  function serialize(){ return JSON.stringify(shapes); }

  // create new polygon
  $('#dev_new_poly').on('click', function(e){ e.preventDefault();
    shapes.push({ points:[], color:'#e91e63', target_id:null, target_type:null, custom_title:'' });
    setFormFrom(shapes.length-1);
  });

  // finish drawing
  $('#dev_finish_poly').on('click', function(e){ e.preventDefault(); current=null; redraw(); });

  // delete selected shape
  $('#dev_delete_poly').on('click', function(e){ e.preventDefault(); if(current===null) return; shapes.splice(current,1); current=null; redraw(); });

  // click to add point when drawing (only if current selected)
  $svg.on('click', function(e){ if($(e.target).is('circle')) return; if(current===null) return; var pt=relCoords(e); shapes[current].points.push(pt); redraw(); });

  // select shape by polygon mousedown
  $svg.on('mousedown','polygon', function(e){ var idx=parseInt(this.getAttribute('data-idx')); setFormFrom(idx); });

  // start dragging a handle
  $svg.on('mousedown','circle.dev-handle', function(e){
    var si = parseInt(this.getAttribute('data-idx'));
    var vi = parseInt(this.getAttribute('data-vi'));
    drag = {type:'vertex', si:si, vi:vi};
    e.preventDefault();
  });

  // dragging
  $(document).on('mousemove', function(e){
    if(!drag) return;
    if(drag.type==='vertex'){
      var pt = relCoords(e);
      if(shapes[drag.si] && shapes[drag.si].points[drag.vi]){
        shapes[drag.si].points[drag.vi] = pt;
        redraw();
      }
    }
  }).on('mouseup', function(){ drag=null; });

  // color/title binding
  $('#dev_color').on('input', function(){ if(current===null) return; shapes[current].color = $(this).val(); redraw(); });
  $('#dev_title').on('input', function(){ if(current===null) return; shapes[current].custom_title = $(this).val(); });

  // targets loader
  function loadTargets(){
    $('#dev_target').empty().append('<option value="">—</option>');
    $.post(DevAptAdmin.ajaxurl,{action:'dev_map_targets',term_id:term}, function(resp){
      if(resp && resp.success){
        resp.data.forEach(function(o){ var val=o.type+':'+o.id; var txt='['+o.type+'] '+o.name; $('#dev_target').append($('<option>').val(val).text(txt)); });
      }
    });
  }
  $('#dev_reload_targets').on('click', function(e){ e.preventDefault(); loadTargets(); });
  $('#dev_target').on('change', function(){ if(current===null) return; var v=$(this).val(); if(!v){shapes[current].target_id=null; shapes[current].target_type=null; return;} var parts=v.split(':'); shapes[current].target_type=parts[0]; shapes[current].target_id=parseInt(parts[1]); });

  // Import polygon <polygon points="...">
  $('#dev_import_btn').on('click', function(e){ e.preventDefault(); if(current===null) return; var txt=$('#dev_svg_import').val(); var m = txt.match(/points\s*=\s*"([^"]+)"/i); if(!m){ alert('Nenašiel som atribút points="..."'); return; } var pts=m[1].trim().split(/\s+/).map(function(pair){ var xy=pair.split(','); return [ parseInt(xy[0],10), parseInt(xy[1],10) ]; }); shapes[current].points=pts; redraw(); });

  // Import PATH d="..." using native SVG sampling
  function pathToPoints(d, step){
    step = step || 6; // px
    var p=document.createElementNS(svgNS,'path');
    p.setAttribute('d', d);
    $svg.append(p); // must be in DOM for getTotalLength
    try{
      var len = p.getTotalLength();
      var out=[]; for(var s=0; s<=len; s+=step){ var pt=p.getPointAtLength(s); out.push([ Math.round(pt.x), Math.round(pt.y) ]); }
      // ensure closed
      if(out.length && (out[0][0]!==out[out.length-1][0] || out[0][1]!==out[out.length-1][1])) out.push(out[0]);
      return out;
    }finally{ p.remove(); }
  }

  $('#dev_import_path_btn').on('click', function(e){ e.preventDefault(); if(current===null) return; var raw=$('#dev_path_import').val().trim(); if(!raw){ alert('Prilep sem <path ...> alebo atribút d=...'); return; }
    // extract d="..." or if starts with d=, parse it; or whole <path ...>
    var d='';
    var m=raw.match(/d\s*=\s*"([^"]+)"/i); if(m) d=m[1]; else if(raw[0]==='M' || raw[0]==='m') d=raw; else { alert('Nenašiel som d="..."'); return; }
    var pts = pathToPoints(d, 6);
    if(!pts || pts.length<3){ alert('PATH sa nepodarilo previesť.'); return; }
    shapes[current].points = pts;
    redraw();
  });

  // Save JSON
  $('#dev_save_map').on('click', function(){ $('#dev_save_result').text('Ukladám...'); $.post(DevAptAdmin.ajaxurl,{ action:'dev_save_term_map', term_id:term, json:serialize(), nonce:DevAptAdmin.nonce }, function(resp){ $('#dev_save_result').text( (resp&&resp.success)?'Uložené ✓':'Chyba: '+(resp&&resp.data?resp.data:'unknown') ); }); });

  function init(){ if($img[0]){ if($img[0].complete){ redraw(); } else { $img.on('load', redraw); } } loadTargets(); if(shapes.length>0) current=0; }
  init();
});
