<?php
if (!defined('ABSPATH')) exit;

// Editor máp – stránka + HARD-FIX pre médiá (wp.media aj Thickbox fallback)
add_action('admin_menu', function(){
  add_submenu_page('edit.php?post_type=apartment', __('Editor máp','developer-apartments'), __('Editor máp','developer-apartments'), 'manage_options','dev-map-editor','dev_apt_render_map_editor_page');
  add_submenu_page(null, __('Editor máp','developer-apartments'), __('Editor máp','developer-apartments'), 'manage_options','dev-map-editor','dev_apt_render_map_editor_page');
});

add_action('admin_init', function(){
  if (isset($_GET['page']) && $_GET['page']==='dev-map-editor' && (!isset($_GET['post_type']) || $_GET['post_type']!=='apartment')){
    $args = ['post_type'=>'apartment','page'=>'dev-map-editor'];
    if(isset($_GET['term_id'])) $args['term_id'] = intval($_GET['term_id']);
    wp_redirect( add_query_arg($args, admin_url('edit.php')) );
    exit;
  }
});

function dev_apt_render_map_editor_page(){
  if(!current_user_can('manage_options')) return;

  // >>> HARD-FIX: vynútime načítanie médií priamo tu (nezávisle od screen id)
  if ( function_exists('wp_enqueue_media') ) wp_enqueue_media();
  if ( function_exists('add_thickbox') ) add_thickbox(); // fallback TB
  wp_enqueue_script('jquery');

  $term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
  $uid = get_current_user_id();
  if($term_id){ update_user_meta($uid, 'devapt_last_term_id', $term_id); }
  $last = intval(get_user_meta($uid, 'devapt_last_term_id', true));
  $nonce = wp_create_nonce('devapt_floor_plan');

  echo '<div class="wrap">';
  echo '<h1>'.esc_html__('Editor máp','developer-apartments').'</h1>';

  // Term chooser (poschodia/projekty)
  echo '<div class="dev-term-picker" style="margin:12px 0;padding:10px;border:1px solid #ccd0d4;background:#fff;">';
  echo '<form method="get" action="'.esc_url(admin_url('edit.php')).'" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
  echo '<input type="hidden" name="post_type" value="apartment" />';
  echo '<input type="hidden" name="page" value="dev-map-editor" />';
  echo '<label for="dev_term_select" style="font-weight:600;">'.esc_html__('Vybrať poschodie/projekt','developer-apartments').':</label>';
  echo '<input type="search" id="dev_term_search" class="regular-text" placeholder="'.esc_attr__('Filtrovať podľa názvu…','developer-apartments').'" />';
  echo '<select name="term_id" id="dev_term_select" class="widefat" style="min-width:320px">';
  echo '<option value="">— '.esc_html__('vyberte','developer-apartments').' —</option>';
  $terms = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids']);
  if(!is_wp_error($terms)){
    foreach($terms as $tid){
      $t = get_term($tid, 'project_structure'); if(!$t || is_wp_error($t)) continue;
      $anc = array_reverse(get_ancestors($tid,'project_structure'));
      $parts = [];
      foreach($anc as $a){ $ta=get_term($a,'project_structure'); if($ta && !is_wp_error($ta)) $parts[]=$ta->name; }
      $parts[] = $t->name; $path = implode(' / ', $parts);
      printf('<option value="%d" %s data-label="%s">%s</option>', intval($tid), selected($term_id?:$last, $tid, false), esc_attr(strtolower($path)), esc_html($path));
    }
  }
  echo '</select>';
  echo '<button type="submit" class="button button-primary">'.esc_html__('Otvoriť','developer-apartments').'</button>';
  echo '</form>';
  echo '<script>(function(){ var s=document.getElementById("dev_term_search"), sel=document.getElementById("dev_term_select"); if(!s||!sel) return; s.addEventListener("input", function(){ var q=this.value.trim().toLowerCase(); Array.prototype.forEach.call(sel.options, function(op,idx){ if(idx===0) return; var lab=op.getAttribute("data-label")||op.textContent.toLowerCase(); op.hidden = q && lab.indexOf(q)===-1; }); }); })();</script>';
  echo '</div>';

  if(!$term_id && $last){ echo '<div class="notice notice-info"><p>'.sprintf(esc_html__('Tip: použite posledné poschodie (term_id=%d) – vyberte ho vyššie.','developer-apartments'), $last).'</p></div>'; }
  if(!$term_id){ echo '</div>'; return; }

  // Dáta termu
  $json = '[]'; $raw = get_term_meta($term_id,'dev_map_data', true); if(is_string($raw) && $raw!=='') $json=$raw;
  $img_id = intval(get_term_meta($term_id,'dev_floor_plan_id', true));
  $img_url = $img_id ? wp_get_attachment_url($img_id) : '';

  // Floor plan – UI
  echo '<div class="dev-floor-picker" style="margin:12px 0;padding:10px;border:1px solid #ccd0d4;background:#fff;display:flex;gap:16px;align-items:center;flex-wrap:wrap">';
  echo '  <strong>'.esc_html__('Podkladový obrázok','developer-apartments').':</strong>';
  echo '  <img id="dev_floor_thumb" src="'.esc_url($img_url).'" style="max-height:60px;border:1px solid #ddd;'.($img_url?'':'display:none;').'" alt="" />';
  echo '  <button type="button" class="button" id="dev_floor_pick">'.esc_html__('Vybrať obrázok','developer-apartments').'</button>';
  echo '  <button type="button" class="button" id="dev_floor_clear" '.($img_url?'':'disabled').'>'.esc_html__('Odstrániť','developer-apartments').'</button>';
  echo '  <span id="dev_floor_status" style="color:#2271b1"></span>';
  echo '</div>';

  // Canvas
  echo '<div class="dev-map-editor" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start;">';
  echo '  <div class="dev-canvas-col">';
  echo '    <div id="dev_canvas_wrap" style="position:relative;max-width:100%;border:1px solid #e3e3e3;background:#fff;overflow:hidden">';
  echo '      <img id="dev_floor_img" src="'.esc_url($img_url).'" alt="" style="width:100%;display:block;transform-origin:0 0;" />';
  echo '      <svg id="dev_svg" width="100%" height="100%" preserveAspectRatio="none" style="position:absolute;left:0;top:0;transform-origin:0 0;"></svg>';
  echo '    </div>';
  echo '    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">';
  echo '      <a href="#" class="button button-primary" id="dev_new_poly">'.esc_html__('Nový polygon','developer-apartments').'</a>';
  echo '      <a href="#" class="button" id="dev_finish_poly">'.esc_html__('Ukončiť kreslenie','developer-apartments').'</a>';
  echo '      <a href="#" class="button button-link-delete" id="dev_delete_poly">'.esc_html__('Vymazať vybraný','developer-apartments').'</a>';
  echo '      <a href="#" class="button button-secondary" id="dev_save_map" data-term="'.esc_attr($term_id).'">'.esc_html__('Uložiť mapu','developer-apartments').'</a>';
  echo '      <span id="dev_save_result" style="line-height:28px"></span>';
  echo '    </div>';
  echo '  </div>';
  echo '  <div class="dev-side-col" style="border:1px solid #e3e3e3;padding:10px;background:#fff">';
  echo '    <h2 style="margin-top:0">'.esc_html__('Vlastnosti','developer-apartments').'</h2>';
  echo '    <p><label>'.esc_html__('Nadpis','developer-apartments').'</label><br><input type="text" id="dev_title" class="widefat" /></p>';
  echo '    <p><label>'.esc_html__('Farba','developer-apartments').'</label><br><input type="color" id="dev_color" value="#e91e63" /></p>';
  echo '    <hr />';
  echo '    <div id="dev_targets_mount"></div>';
  echo '  </div>';
  echo '</div>';

  echo '<textarea id="dev_map_json" style="width:100%;height:160px;margin-top:14px" hidden>'.esc_textarea($json).'</textarea>';

?>
<script type="text/javascript">
(function($){
  var termId = <?php echo intval($term_id); ?>;
  var nonce  = "<?php echo esc_js($nonce); ?>";

  function msg(t){
    $("#dev_floor_status").text(t);
    setTimeout(function(){ $("#dev_floor_status").text(""); }, 1800);
  }

  $("#dev_floor_pick").on("click", function(e){
    e.preventDefault();

    // Moderný modal
    if (window.wp && wp.media && wp.media.editor){
      var backup = wp.media.editor.send.attachment;
      wp.media.editor.send.attachment = function(props, att){
        if(att && att.id){
          $("#dev_floor_img").attr("src", att.url);
          $("#dev_floor_thumb").attr("src", att.url).show();
          $("#dev_floor_clear").prop("disabled", false);
          $.post(ajaxurl, {
            action:"devapt_set_floor_plan",
            _ajax_nonce: nonce,
            term_id: termId,
            attachment_id: att.id
          }).done(function(){ msg("Uložené"); })
            .fail(function(){ msg("Chyba ukladania"); });
        }
        wp.media.editor.send.attachment = backup;
      };
      wp.media.editor.open("dev_floor_pick");
      return;
    }

    // Fallback: Thickbox (ak wp.media nie je k dispozícii)
    window.send_to_editor = function(html){
      try{
        var id = 0, url = "";
        var idMatch  = /attachment_id\s*=\s*\"?(\d+)/i.exec(html);
        if(idMatch){ id = parseInt(idMatch[1],10) || 0; }
        var urlMatch = /src=\"([^\"]+)/i.exec(html);
        if(urlMatch){ url = urlMatch[1]; }

        if(id){
          $("#dev_floor_img").attr("src", url);
          $("#dev_floor_thumb").attr("src", url).show();
          $("#dev_floor_clear").prop("disabled", false);
          $.post(ajaxurl, {
            action:"devapt_set_floor_plan",
            _ajax_nonce: nonce,
            term_id: termId,
            attachment_id: id
          }).done(function(){ msg("Uložené"); })
            .fail(function(){ msg("Chyba ukladania"); });
        }
      }catch(e){}
      tb_remove();
    };

    tb_show("Vybrať obrázok", "media-upload.php?type=image&TB_iframe=true");
  });

  $("#dev_floor_clear").on("click", function(e){
    e.preventDefault();
    $("#dev_floor_img").attr("src", "");
    $("#dev_floor_thumb").hide().attr("src", "");
    $("#dev_floor_clear").prop("disabled", true);
    $.post(ajaxurl, {
      action:"devapt_set_floor_plan",
      _ajax_nonce: nonce,
      term_id: termId,
      attachment_id: 0
    }).done(function(){ msg("Odstránené"); })
      .fail(function(){ msg("Chyba ukladania"); });
  });
})(jQuery);
</script>
<script>
(function(){
  function onReady(cb){
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cb);
    else cb();
  }

  onReady(function(){
    function hasEditor(){
      return window.DEV_MAP_EDITOR && window.DEV_MAP_EDITOR.state && typeof window.DEV_MAP_EDITOR.render === 'function';
    }

    function startDrawing(){
      var ed = window.DEV_MAP_EDITOR;
      var S  = ed.state;
      // načítaj aktuálne polia z bočnice (ak tam nie sú, použijeme defaulty)
      var get = function(id){ var el = document.getElementById(id); return el ? el.value : ''; };
      var color    = get('dev_color') || '#e91e63';
      var title    = get('dev_title') || '';
      var hover    = (document.getElementById('dev_hover_color') || {value:'#26a69a'}).value || '#26a69a';
      var noStroke = !!(document.getElementById('dev_no_stroke') || {}).checked;

      // vlož nový prázdny tvar a prepneme do kreslenia
      S.shapes.push({
        uid: 'p' + Date.now(),
        points: [],
        color: color,
        hover: hover,
        no_stroke: noStroke,
        target_id: null,
        target_type: null,
        custom_title: title
      });
      S.current = S.shapes.length - 1;
      S.mode    = 'drawing';
      ed.render();
      console.log('[editor-rescue] Začiatok kreslenia: shape index', S.current);
    }

    // Delegovaný klik na „Nový polygon“ (funguje aj keď pôvodný handler nenabehol)
    document.addEventListener('click', function(e){
      var btn = e.target.closest && e.target.closest('#dev_new_poly');
      if (!btn) return;
      e.preventDefault();
      if (!hasEditor()){
        console.warn('[editor-rescue] Editor nie je pripravený');
        return;
      }
      startDrawing();
    }, true);

    // (Voliteľné) Delegovaný klik na „Ukončiť kreslenie“ – nech to vieš zatvoriť aj bez pôvodného handlera
    document.addEventListener('click', function(e){
      var btn = e.target.closest && e.target.closest('#dev_finish_poly');
      if (!btn) return;
      e.preventDefault();
      if (!hasEditor()) return;
      var ed = window.DEV_MAP_EDITOR, S = ed.state, s = S.shapes[S.current];
      if (s && s.points && s.points.length >= 3){
        S.mode = 'idle';
        ed.render();
        console.log('[editor-rescue] Ukončené kreslenie');
      } else {
        alert('Polygon musí mať aspoň 3 body.');
      }
    }, true);
  });
})();
</script>
<script>
(function(){
  function onReady(cb){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', cb); else cb(); }
  onReady(function(){
    if(!window.DEV_MAP_EDITOR || !window.DEV_MAP_EDITOR.state){ console.warn('[quick-tools] Editor nie je pripravený'); return; }
    var ed = window.DEV_MAP_EDITOR, S = ed.state;

    // --- 0) Mini-toolbar (plávajúca lišta vpravo hore) ---
    var bar = document.createElement('div');
    bar.style.position='fixed'; bar.style.top='80px'; bar.style.right='16px';
    bar.style.display='flex'; bar.style.flexDirection='column'; bar.style.gap='6px';
    bar.style.zIndex='9999'; bar.style.pointerEvents='none';
    function btn(txt, id){ var b=document.createElement('button'); b.textContent=txt; b.id=id; b.className='button'; b.style.pointerEvents='auto'; return b; }
    var bUndo = btn('↶ Krok späť','qt_undo'),
        bDone = btn('✓ Ukončiť','qt_done'),
        bZIn  = btn('＋ Priblížiť','qt_zoomin'),
        bZOut = btn('－ Vzdialiť','qt_zoomout'),
        bSmooth = btn('◔ Zaobliť','qt_smooth');
    [bUndo,bDone,bZIn,bZOut,bSmooth].forEach(function(b){ b.style.minWidth='120px'; bar.appendChild(b); });
    document.body.appendChild(bar);

    // --- 1) Helpery ---
    function isDrawing(){ return S && S.mode==='drawing'; }
    function stepBack(){
      if(!isDrawing()) return;
      var s = S.shapes[S.current]; if(!s) return;
      s.points.pop(); ed.render();
    }
    function finishDraw(){
      if(!isDrawing()) return;
      var s = S.shapes[S.current]; if(!s) return;
      if(s.points.length<3){ alert('Polygon musí mať aspoň 3 body.'); return; }
      S.mode='idle'; ed.render();
    }
    function zoom(by){
      S.zoom = Math.max(0.25, Math.min(4, (S.zoom||1)*by));
      var Z=S.zoom||1, img=document.getElementById('dev_floor_img'), svg=document.getElementById('dev_svg');
      if(img) img.style.transform='scale('+Z+')';
      if(svg) svg.style.transform='scale('+Z+')';
    }
    function chaikinOnce(){
      var s=S.shapes[S.current]; if(!s || s.points.length<3) return;
      var pts=s.points, out=[];
      for(var i=0;i<pts.length;i++){
        var p=pts[i], q=pts[(i+1)%pts.length];
        out.push({x:0.75*p.x+0.25*q.x,y:0.75*p.y+0.25*q.y});
        out.push({x:0.25*p.x+0.75*q.x,y:0.25*p.y+0.75*q.y});
      }
      s.points=out; ed.render();
    }
    function preventSelectWhileDrawing(enable){
      var svg = document.getElementById('dev_svg'); if(!svg) return;
      Array.prototype.forEach.call(svg.querySelectorAll('polygon'), function(p){
        p.style.pointerEvents = enable ? 'none' : '';
      });
    }

    // --- 2) Kliky toolbaru ---
    bUndo.addEventListener('click', function(e){ e.preventDefault(); stepBack(); });
    bDone.addEventListener('click', function(e){ e.preventDefault(); finishDraw(); });
    bZIn.addEventListener('click', function(e){ e.preventDefault(); zoom(1.2); });
    bZOut.addEventListener('click', function(e){ e.preventDefault(); zoom(1/1.2); });
    bSmooth.addEventListener('click', function(e){ e.preventDefault(); chaikinOnce(); });

    // --- 3) Klávesové skratky ---
    document.addEventListener('keydown', function(e){
      // ukladanie (Ctrl/Cmd + S)
      if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='s'){
        e.preventDefault();
        var save = document.getElementById('dev_save_map'); if(save) save.click();
      }
      // počas kreslenia
      if(isDrawing()){
        if(e.key==='Backspace' || e.key==='Delete'){ e.preventDefault(); stepBack(); }
        if(e.key==='Enter'){ e.preventDefault(); finishDraw(); }
      }
      // smoothing
      if(e.shiftKey && (e.key.toLowerCase()==='s')){ e.preventDefault(); chaikinOnce(); }
      // zoom
      if(e.key==='+' || e.key==='='){ e.preventDefault(); zoom(1.2); }
      if(e.key==='-'){ e.preventDefault(); zoom(1/1.2); }
    }, true);

    // --- 4) Počas kreslenia vypni „klikateľnosť“ existujúcich polygónov (neprehadzuje výber) ---
    var origRender = ed.render;
    ed.render = function(){
      try{ origRender.apply(ed, arguments); }
      finally{ preventSelectWhileDrawing(isDrawing()); }
    };
    // prvotné uplatnenie (ak už kreslíš)
    preventSelectWhileDrawing(isDrawing());
  });
})();
</script>
<?php
  echo '</div>';
}

