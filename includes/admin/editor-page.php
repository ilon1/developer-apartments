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

/**
 * Úvodná stránka Editora máp: zoznam všetkých termínov Štruktúry projektu
 * s indikáciou podkladového obrázka, počtu polygónov a tlačidlom Editovať.
 */
function dev_apt_render_map_editor_list(){
  $terms = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
  if (is_wp_error($terms)) $terms = array();

  $list = array();
  foreach ($terms as $t) {
    $tid = (int) $t->term_id;
    $anc = array_reverse(get_ancestors($tid, 'project_structure'));
    $parts = array();
    foreach ($anc as $a) {
      $ta = get_term($a, 'project_structure');
      if ($ta && !is_wp_error($ta)) $parts[] = $ta->name;
    }
    $parts[] = $t->name;
    $path = implode(' / ', $parts);

    $img_id = (int) get_term_meta($tid, 'dev_floor_plan_id', true);
    $raw = get_term_meta($tid, 'dev_map_data', true);
    $count = 0;
    if (is_string($raw) && $raw !== '') {
      $arr = json_decode($raw, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) $count = count($arr);
    }

    $list[] = array(
      'term_id' => $tid,
      'path'    => $path,
      'name'    => $t->name,
      'has_img' => $img_id > 0,
      'poly_count' => $count,
    );
  }

  $base = add_query_arg(array('post_type'=>'apartment','page'=>'dev-map-editor'), admin_url('edit.php'));
  echo '<div class="wrap">';
  echo '<h1>'.esc_html__('Editor máp','developer-apartments').'</h1>';
  echo '<p class="description">'.esc_html__('Vyberte objekt (poschodie / projekt) zo Štruktúry projektu a kliknite na Editovať.','developer-apartments').'</p>';

  if (empty($list)) {
    echo '<p>'.esc_html__('Žiadne termíny v Štruktúre projektu.','developer-apartments').'</p>';
    echo '</div>';
    return;
  }

  echo '<table class="widefat striped" style="max-width:720px;margin-top:12px;">';
  echo '<thead><tr>';
  echo '<th>'.esc_html__('Objekt','developer-apartments').'</th>';
  echo '<th>'.esc_html__('Podklad mapy','developer-apartments').'</th>';
  echo '<th>'.esc_html__('Polygóny','developer-apartments').'</th>';
  echo '<th></th>';
  echo '</tr></thead><tbody>';
  foreach ($list as $row) {
    $edit_url = add_query_arg('term_id', $row['term_id'], $base);
    echo '<tr>';
    echo '<td>'.esc_html($row['path']).'</td>';
    echo '<td>'.($row['has_img'] ? '✓' : '—').'</td>';
    echo '<td>'.(int)$row['poly_count'].'</td>';
    echo '<td><a href="'.esc_url($edit_url).'" class="button button-primary">'.esc_html__('Editovať','developer-apartments').'</a></td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo '</div>';
}

/**
 * Prepínač termínu hore v edit view – výber iného poschodia/projektu bez vychádzania.
 */
function dev_apt_render_term_switcher($current_term_id){
  $last = (int) get_user_meta(get_current_user_id(), 'devapt_last_term_id', true);
  $terms = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids']);
  if (is_wp_error($terms)) $terms = array();

  echo '<div class="dev-term-picker" style="margin:12px 0;padding:10px;border:1px solid #ccd0d4;background:#fff;">';
  echo '<form method="get" action="'.esc_url(admin_url('edit.php')).'" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
  echo '<input type="hidden" name="post_type" value="apartment" />';
  echo '<input type="hidden" name="page" value="dev-map-editor" />';
  echo '<label for="dev_term_select" style="font-weight:600;">'.esc_html__('Poschodie / projekt','developer-apartments').':</label>';
  echo '<input type="search" id="dev_term_search" class="regular-text" placeholder="'.esc_attr__('Filtrovať podľa názvu…','developer-apartments').'" />';
  echo '<select name="term_id" id="dev_term_select" class="widefat" style="min-width:320px">';
  echo '<option value="">— '.esc_html__('vyberte','developer-apartments').' —</option>';
  foreach ($terms as $tid) {
    $t = get_term($tid, 'project_structure');
    if (!$t || is_wp_error($t)) continue;
    $anc = array_reverse(get_ancestors($tid, 'project_structure'));
    $parts = array();
    foreach ($anc as $a) { $ta = get_term($a, 'project_structure'); if ($ta && !is_wp_error($ta)) $parts[] = $ta->name; }
    $parts[] = $t->name;
    $path = implode(' / ', $parts);
    printf('<option value="%d" %s data-label="%s">%s</option>', $tid, selected($current_term_id ?: $last, $tid, false), esc_attr(strtolower($path)), esc_html($path));
  }
  echo '</select>';
  echo '<button type="submit" class="button button-primary">'.esc_html__('Prepnúť','developer-apartments').'</button>';
  echo ' <a href="'.esc_url(admin_url('edit.php?post_type=apartment&page=dev-map-editor')).'" class="button">'.esc_html__('Späť na zoznam','developer-apartments').'</a>';
  echo '</form>';
  echo '<script>(function(){ var s=document.getElementById("dev_term_search"), sel=document.getElementById("dev_term_select"); if(!s||!sel) return; s.addEventListener("input", function(){ var q=this.value.trim().toLowerCase(); Array.prototype.forEach.call(sel.options, function(op,idx){ if(idx===0) return; var lab=op.getAttribute("data-label")||op.textContent.toLowerCase(); op.hidden = q && lab.indexOf(q)===-1; }); }); })();</script>';
  echo '</div>';
}

function dev_apt_render_map_editor_page(){
  if(!current_user_can('manage_options')) return;

  $term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;

  // ---------- Úvodná stránka: zoznam všetkých objektov (Štruktúra projektu) ----------
  if (!$term_id) {
    dev_apt_render_map_editor_list();
    return;
  }

  // ---------- Edit view: konkrétny termín ----------
  if ( function_exists('wp_enqueue_media') ) wp_enqueue_media();
  if ( function_exists('add_thickbox') ) add_thickbox();
  wp_enqueue_script('jquery');

  $uid = get_current_user_id();
  update_user_meta($uid, 'devapt_last_term_id', $term_id);
  $nonce = wp_create_nonce('devapt_floor_plan');

  echo '<div class="wrap">';
  echo '<h1>'.esc_html__('Editor máp','developer-apartments').'</h1>';

  // Prepínač termínu – vždy hore, aby sa dalo meniť bez vychádzania
  dev_apt_render_term_switcher($term_id);

  // Dáta termu
  $json = '[]'; $raw = get_term_meta($term_id,'dev_map_data', true); if(is_string($raw) && $raw!=='') $json=$raw;
  $img_id = intval(get_term_meta($term_id,'dev_floor_plan_id', true));
  $img_url = $img_id ? wp_get_attachment_url($img_id) : '';

  // Podklad mapy (názov: Podklad mapy = obrázok, na ktorom sa kreslia polygóny)
  echo '<div class="dev-floor-picker" style="margin:12px 0;padding:10px;border:1px solid #ccd0d4;background:#fff;display:flex;gap:16px;align-items:center;flex-wrap:wrap">';
  echo '  <strong>'.esc_html__('Podklad mapy','developer-apartments').':</strong>';
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
  echo '    <div class="dev-import-row" style="margin-top:12px;padding:10px;border:1px solid #e3e3e3;background:#f9f9f9;">';
  echo '      <strong style="display:block;margin-bottom:6px;">'.esc_html__('Import polygónov','developer-apartments').'</strong>';
  echo '      <input type="file" id="dev_import_svg" accept=".svg" style="display:none">';
  echo '      <input type="file" id="dev_import_json" accept=".json" style="display:none">';
  echo '      <button type="button" class="button button-small" id="dev_btn_import_svg">'.esc_html__('Import zo SVG','developer-apartments').'</button> ';
  echo '      <button type="button" class="button button-small" id="dev_btn_import_json">'.esc_html__('Import z JSON','developer-apartments').'</button>';
  echo '      <span id="dev_import_result" style="margin-left:8px;line-height:26px;"></span>';
  echo '    </div>';
  echo '  </div>';
  echo '  <div class="dev-side-col" style="border:1px solid #e3e3e3;padding:12px;background:#fff;">';
  echo '    <div class="dev-global-colors" style="margin-bottom:16px;padding:10px;border:1px solid #ccd0d4;background:#f6f7f7;">';
  echo '      <h3 style="margin:0 0 8px;font-size:14px;">'.esc_html__('Globálne nastavenie farieb','developer-apartments').'</h3>';
  echo '      <p style="margin:0 0 6px;"><label for="dev_global_color">'.esc_html__('Predvolená farba polygónu','developer-apartments').'</label><br><input type="color" id="dev_global_color" value="#e91e63" style="width:100%;max-width:120px;" /><br><input type="text" id="dev_global_color_hex" class="widefat" placeholder="'.esc_attr__('Hex (#rrggbb) alebo rgb(r,g,b)','developer-apartments').'" style="margin-top:4px;max-width:200px;font-family:monospace;" /></p>';
  echo '      <p style="margin:0;"><label for="dev_global_hover">'.esc_html__('Predvolená hover farba','developer-apartments').'</label><br><input type="color" id="dev_global_hover" value="#26a69a" style="width:100%;max-width:120px;" /><br><input type="text" id="dev_global_hover_hex" class="widefat" placeholder="'.esc_attr__('Hex (#rrggbb) alebo rgb(r,g,b)','developer-apartments').'" style="margin-top:4px;max-width:200px;font-family:monospace;" /></p>';
  echo '    </div>';
  echo '    <h3 style="margin:0 0 8px;font-size:14px;">'.esc_html__('Zoznam polygónov','developer-apartments').'</h3>';
  echo '    <div id="dev_poly_list" class="dev-poly-list" style="max-height:240px;overflow-y:auto;margin-bottom:16px;border:1px solid #e3e3e3;background:#fff;"></div>';
  echo '    <h3 style="margin:0 0 8px;font-size:14px;">'.esc_html__('Vlastnosti vybraného polygónu','developer-apartments').'</h3>';
  echo '    <p><label for="dev_title">'.esc_html__('Nadpis','developer-apartments').'</label><br><input type="text" id="dev_title" class="widefat" placeholder="'.esc_attr__('Názov polygónu','developer-apartments').'" /></p>';
  echo '    <p><label for="dev_color">'.esc_html__('Farba','developer-apartments').'</label><br><input type="color" id="dev_color" value="#e91e63" /><br><input type="text" id="dev_color_hex" class="widefat" placeholder="'.esc_attr__('Hex (#rrggbb) alebo rgb(r,g,b)','developer-apartments').'" style="margin-top:4px;max-width:200px;font-family:monospace;" /></p>';
  echo '    <p><label for="dev_hover_color">'.esc_html__('Hover farba','developer-apartments').'</label><br><input type="color" id="dev_hover_color" value="#26a69a" /><br><input type="text" id="dev_hover_color_hex" class="widefat" placeholder="'.esc_attr__('Hex (#rrggbb) alebo rgb(r,g,b)','developer-apartments').'" style="margin-top:4px;max-width:200px;font-family:monospace;" /></p>';
  echo '    <p><label for="dev_tooltip">'.esc_html__('Tooltip','developer-apartments').'</label><br><input type="text" id="dev_tooltip" class="widefat" placeholder="'.esc_attr__('Vlastný text pre tooltip','developer-apartments').'" /></p>';
  echo '    <p style="margin:12px 0 0;"><button type="button" class="button button-primary" id="dev_save_poly">'.esc_html__('Uložiť polygón','developer-apartments').'</button>';
  echo ' <span class="description">'.esc_html__('Zapíše zmeny vybraného polygónu a uloží mapu.','developer-apartments').'</span></p>';
  echo '    <div id="dev_targets_mount"></div>';
  echo '    <details class="dev-help-shortcuts" style="margin-top:20px;padding:10px;border:1px solid #ccd0d4;background:#f9f9f9;font-size:12px;line-height:1.5;">';
  echo '      <summary style="cursor:pointer;font-weight:600;margin-bottom:6px;">'.esc_html__('Nápoveda — skratky a skryté funkcie','developer-apartments').'</summary>';
  echo '      <ul style="margin:0;padding-left:18px;">';
  echo '        <li><strong>Alt + klik</strong> na bod rohu vybraného polygónu — zaoblí len ten roh (reaguje na intenzitu v plávajúcej lište)</li>';
  echo '        <li><strong>Ctrl + klik</strong> na hranu vybraného polygónu — pridá bod na hranu (pre jemnejšie zaoblenie)</li>';
  echo '        <li><strong>Backspace / Delete</strong> — počas kreslenia: odstráni posledný bod; v režime úprav: vráti posledné zaoblenie alebo pridanie bodu</li>';
  echo '        <li><strong>Shift + S</strong> — zaobliť celý vybraný polygón (reaguje na intenzitu)</li>';
  echo '        <li><strong>Enter</strong> — počas kreslenia ukončí polygón</li>';
  echo '        <li><strong>Ctrl + S</strong> — uloží mapu</li>';
  echo '        <li><strong>+ / −</strong> — priblížiť / oddialiť</li>';
  echo '      </ul>';
  echo '      <p style="margin:8px 0 0;color:#50575e;">'.esc_html__('Intenzita zaoblenia v plávajúcej lište vpravo ovplyvňuje silu oblúka pri Alt+Klik na roh aj pri „Zaobliť všetko“.','developer-apartments').'</p>';
  echo '    </details>';
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

  function importResult(txt){ $("#dev_import_result").text(txt); setTimeout(function(){ $("#dev_import_result").text(""); }, 3000); }
  $("#dev_btn_import_svg").on("click", function(e){ e.preventDefault(); $("#dev_import_svg").trigger("click"); });
  $("#dev_import_svg").on("change", function(){
    var f = this.files && this.files[0]; this.value = "";
    if(!f){ return; }
    var reader = new FileReader();
    reader.onload = function(){
      var ed = window.DEV_MAP_EDITOR;
      if(!ed || typeof ed.importFromSVG !== "function"){ importResult("Editor ešte nie je pripravený."); return; }
      var n = ed.importFromSVG(reader.result);
      importResult(n ? "Importované polygónov: " + n : "V SVG neboli nájdené polygóny.");
    };
    reader.readAsText(f);
  });
  $("#dev_btn_import_json").on("click", function(e){ e.preventDefault(); $("#dev_import_json").trigger("click"); });
  $("#dev_import_json").on("change", function(){
    var f = this.files && this.files[0]; this.value = "";
    if(!f){ return; }
    var reader = new FileReader();
    reader.onload = function(){
      var ed = window.DEV_MAP_EDITOR;
      if(!ed || typeof ed.importFromJSON !== "function"){ importResult("Editor ešte nie je pripravený."); return; }
      var n = ed.importFromJSON(reader.result);
      importResult(n ? "Importované polygónov: " + n : "Neplatný alebo prázdny JSON.");
    };
    reader.readAsText(f);
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
    if(!window.DEV_MAP_EDITOR || !window.DEV_MAP_EDITOR.state){ return; }
    var ed = window.DEV_MAP_EDITOR, S = ed.state;
    // Počas kreslenia vypni klikateľnosť existujúcich polygónov (rozšírenie správania z term-map-editor.js)
    var origRender = ed.render;
    ed.render = function(){
      try{ return origRender.apply(ed, arguments); }
      finally{
        var svg = document.getElementById('dev_svg'); if(!svg) return;
        var drawing = S && S.mode==='drawing';
        Array.prototype.forEach.call(svg.querySelectorAll('polygon'), function(p){ p.style.pointerEvents = drawing ? 'none' : ''; });
      }
    };
    if(S.mode==='drawing') origRender();
  });
})();
</script>
<?php
  echo '</div>';
}

