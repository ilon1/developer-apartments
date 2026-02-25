<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class DEV_Map_Module_V2 extends ET_Builder_Module {
  public $slug = 'dev_apartment_map_v2'; public $vb_support = 'on';
  public function init(){ $this->name = 'Mapa Bytov (v2)'; $this->settings_modal_toggles = array('general'=>array('toggles'=>array('source'=>array('title'=>'Zdroj'),'style'=>array('title'=>'Štýl'),'tooltip'=>array('title'=>'Tooltip'),'badge'=>array('title'=>'Obsadenosť'),'hover'=>array('title'=>'Hover efekty'),'behavior'=>array('title'=>'Správanie'),'perf'=>array('title'=>'Výkon / Cache'),)),); }
  public function get_fields(){ $terms = get_terms(array('taxonomy'=>'project_structure','hide_empty'=>false)); $opts = array('' => '— Vyberte —'); if(!is_wp_error($terms)) foreach($terms as $t){ $opts[$t->term_id] = $t->name; }
    return array(
      'source_mode'=>array('label'=>'Režim zdroja','type'=>'select','options'=>array('manual'=>'Manuálny výber','context'=>'Z kontextu stránky'),'default'=>'context','tab_slug'=>'general','toggle_slug'=>'source'),
      'source_id'=>array('label'=>'Zdroj (projekt/budova/podlažie)','type'=>'select','options'=>$opts,'tab_slug'=>'general','toggle_slug'=>'source'),
      'height'=>array('label'=>'Výška mapy desktop (px)','type'=>'text','default'=>'640','tab_slug'=>'general','toggle_slug'=>'style'),
      'height_tablet'=>array('label'=>'Výška tablet (px)','type'=>'text','default'=>'520','tab_slug'=>'general','toggle_slug'=>'style'),
      'height_mobile'=>array('label'=>'Výška mobil (px)','type'=>'text','default'=>'420','tab_slug'=>'general','toggle_slug'=>'style'),
      'overlay_color_mode'=>array('label'=>'Farby overlay (politika)','type'=>'select','options'=>array('shape'=>'Rešpektovať farby z editoru','override'=>'Vynútiť farbu z modulu'),'default'=>'shape','tab_slug'=>'general','toggle_slug'=>'style'),
      'overlay_color'=>array('label'=>'Vynútená farba overlay','type'=>'color-alpha','default'=>'#1e88e5','tab_slug'=>'general','toggle_slug'=>'style'),
      'tooltip_bg'=>array('label'=>'Tooltip: pozadie','type'=>'color-alpha','default'=>'#222','tab_slug'=>'general','toggle_slug'=>'tooltip'),
      'tooltip_color'=>array('label'=>'Tooltip: farba textu','type'=>'color-alpha','default'=>'#fff','tab_slug'=>'general','toggle_slug'=>'tooltip'),
      'tooltip_padding'=>array('label'=>'Tooltip: padding (px)','type'=>'text','default'=>'8','tab_slug'=>'general','toggle_slug'=>'tooltip'),
      'tooltip_size'=>array('label'=>'Tooltip: veľkosť písma (px)','type'=>'text','default'=>'14','tab_slug'=>'general','toggle_slug'=>'tooltip'),
      'tooltip_offset'=>array('label'=>'Tooltip: odsadenie nad polygónom (px)','type'=>'text','default'=>'12','tab_slug'=>'general','toggle_slug'=>'tooltip'),
      'available_bg'=>array('label'=>'Badge voľné: pozadie','type'=>'color-alpha','default'=>'#2e7d32','tab_slug'=>'general','toggle_slug'=>'badge'),
      'available_color'=>array('label'=>'Badge voľné: text','type'=>'color-alpha','default'=>'#fff','tab_slug'=>'general','toggle_slug'=>'badge'),
      'unavailable_bg'=>array('label'=>'Badge nedostupné: pozadie','type'=>'color-alpha','default'=>'#c62828','tab_slug'=>'general','toggle_slug'=>'badge'),
      'unavailable_color'=>array('label'=>'Badge nedostupné: text','type'=>'color-alpha','default'=>'#fff','tab_slug'=>'general','toggle_slug'=>'badge'),
      'hover_fill'=>array('label'=>'Hover: farba výplne (modul)','type'=>'color-alpha','default'=>'#26a69a','tab_slug'=>'general','toggle_slug'=>'hover'),
      'hover_opacity'=>array('label'=>'Hover: nepriehľadnosť (0-1)','type'=>'text','default'=>'0.5','tab_slug'=>'general','toggle_slug'=>'hover'),
      'stroke_mode'=>array('label'=>'Obrysová línia','type'=>'select','options'=>array('same'=>'Rovnaká ako výplň','none'=>'Bez obrysu'),'default'=>'same','tab_slug'=>'general','toggle_slug'=>'hover'),
      'overlay_click_action'=>array('label'=>'Klik na overlay','type'=>'select','options'=>array('navigate'=>'Prejsť na kategóriu','emit_event'=>'Poslať event do stránky'),'default'=>'navigate','tab_slug'=>'general','toggle_slug'=>'behavior'),
      'disable_cache'=>array('label'=>'Ignorovať cache (TTL)','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'off','tab_slug'=>'general','toggle_slug'=>'perf'),
    ); }
  private function is_builder(){ return function_exists('et_fb_is_builder') && et_fb_is_builder(); }
  private function resolve_source_term(){ $mode = $this->props['source_mode'] ?? 'context'; $raw = $this->props['source_id'] ?? ''; $manual = intval($raw); if($raw !== '' && $manual > 0) return $manual; if($mode === 'manual') return 0; $qo = get_queried_object(); if($qo && isset($qo->taxonomy) && $qo->taxonomy==='project_structure') return intval($qo->term_id); if(is_singular('apartment')){ $terms = wp_get_post_terms(get_the_ID(),'project_structure',array('number'=>1)); if($terms && !is_wp_error($terms)) return intval($terms[0]->term_id); } return 0; }
  private function builder_preview($h,$src){ $h = (int)$h; if($h < 120) $h = 120; ob_start(); ?>
    <div class="dev-map-preview" style="height:<?php echo esc_attr($h); ?>px; display:flex;align-items:center;justify-content:center; background:#f6f7f9;border:1px dashed #cdd3db;border-radius:8px;">
      <div style="text-align:center;color:#6b7480;line-height:1.5">
        <strong>Mapa Bytov (náhľad)</strong><br>
        Zdroj: <?php echo $src ? 'term ID '.$src : 'nevybraný'; ?><br>
        Reálna mapa sa načíta na publiku.
      </div>
    </div>
  <?php return ob_get_clean(); }
  public function render($attrs,$content=null,$render_slug){
    $term_id = $this->resolve_source_term();
    $h_raw = (string) ($this->props['height'] ?? '640');
    $h_val = preg_replace('/[^0-9]/','', $h_raw);
    $h = (int)$h_val; if($h<=0) $h = 640;
    if ($this->is_builder()) return $this->builder_preview($h,$term_id);

    $img_id = $term_id ? get_term_meta($term_id, 'dev_floor_plan_id', true) : 0;
    $img_url = $img_id ? wp_get_attachment_url($img_id) : '';
    $json = $term_id ? get_term_meta($term_id, 'dev_map_data', true) : '';
    $shapes = array(); if ($json){ $tmp=json_decode($json, true); if(json_last_error()===JSON_ERROR_NONE && is_array($tmp)) $shapes=$tmp; }

    if ( ! function_exists('dev_apt_free_counts_cached') ) return '';

    $target_terms = array(); foreach($shapes as $s){ if(!empty($s['target_type']) && $s['target_type']==='term' && !empty($s['target_id'])) $target_terms[] = intval($s['target_id']); }
    $target_terms = array_values(array_unique($target_terms));
    $all_ps_terms = $target_terms; foreach($target_terms as $tid){ $desc = get_terms(array('taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids','child_of'=>$tid)); if(!is_wp_error($desc) && $desc) $all_ps_terms = array_merge($all_ps_terms, $desc); }
    $all_ps_terms = array_values(array_unique($all_ps_terms));

    $no_cache = ($this->props['disable_cache'] ?? 'off') === 'on'; if($no_cache){ $GLOBALS['_DEV_APT_NO_CACHE'] = true; }
    $opts = get_option('dev_apt_options', array()); $free_slug = !empty($opts['free_status_slug']) ? sanitize_title($opts['free_status_slug']) : 'volny';
    $counts = dev_apt_free_counts_cached($all_ps_terms, $target_terms, $free_slug);
    if($no_cache){ unset($GLOBALS['_DEV_APT_NO_CACHE']); }

    $tooltip_offset = (int)($this->props['tooltip_offset'] ?? '12');
    $css_vars = '--dev-tooltip-bg:'.esc_attr((string)$this->props['tooltip_bg']).';'
              .'--dev-tooltip-color:'.esc_attr((string)$this->props['tooltip_color']).';'
              .'--dev-tooltip-pad:'.esc_attr((string)$this->props['tooltip_padding']).'px;'
              .'--dev-tooltip-size:'.esc_attr((string)$this->props['tooltip_size']).'px;'
              .'--dev-available-bg:'.esc_attr((string)$this->props['available_bg']).';'
              .'--dev-available-color:'.esc_attr((string)$this->props['available_color']).';'
              .'--dev-unavailable-bg:'.esc_attr((string)$this->props['unavailable_bg']).';'
              .'--dev-unavailable-color:'.esc_attr((string)$this->props['unavailable_color']).';'
              .'--dev-hover-fill:'.esc_attr((string)$this->props['hover_fill']).';'
              .'--dev-hover-opacity:'.esc_attr((string)$this->props['hover_opacity']).';'
              .'--dev-stroke-mode:'.esc_attr((string)$this->props['stroke_mode']).';'
              .'--dev-tooltip-offset:'.$tooltip_offset.'px;';

    $data = array('termId' => $term_id,'legend' => true,'shapes' => array(),'colorMode' => ($this->props['overlay_color_mode'] ?? 'shape'),'color' => ($this->props['overlay_color'] ?? '#1e88e5'),'action' => ($this->props['overlay_click_action'] ?? 'navigate'));

    foreach ($shapes as $s) {
      if(!empty($s['target_type']) && !empty($s['target_id'])){
        if($s['target_type']==='term'){
          $tid = intval($s['target_id']);
          $s['link'] = get_term_link($tid, 'project_structure');
          $s['free_count'] = isset($counts[$tid]) ? intval($counts[$tid]) : 0;
        }
        if($s['target_type']==='post'){
          $pid = intval($s['target_id']);
          $s['link'] = get_permalink($pid);
        }
      }
      $data['shapes'][] = $s;
    }

    if ( ! is_admin() ) {
      wp_enqueue_style('dev-apt-map-css');
      wp_enqueue_script('dev-apt-map');
    }

    $uid = 'dev-map-'.uniqid();
    $h_tab_raw = (string)($this->props['height_tablet'] ?? '520'); $h_tab_val = preg_replace('/[^0-9]/','', $h_tab_raw); $h_tab = (int)$h_tab_val; if($h_tab<=0) $h_tab = 520;
    $h_mob_raw = (string)($this->props['height_mobile'] ?? '420'); $h_mob_val = preg_replace('/[^0-9]/','', $h_mob_raw); $h_mob = (int)$h_mob_val; if($h_mob<=0) $h_mob = 420;

    ob_start(); ?>
      <style>#<?php echo esc_attr($uid); ?>{height:<?php echo esc_attr($h); ?>px}@media (max-width:980px){#<?php echo esc_attr($uid); ?>{height:<?php echo esc_attr($h_tab); ?>px}}@media (max-width:767px){#<?php echo esc_attr($uid); ?>{height:<?php echo esc_attr($h_mob); ?>px}}</style>
      <div id="<?php echo esc_attr($uid); ?>"
           class="dev-apt-map"
           style="position:relative; width:100%; <?php echo $css_vars; ?>"
           data-img="<?php echo esc_attr($img_url); ?>"
           data-payload='<?php echo wp_json_encode($data); ?>'></div>
    <?php return ob_get_clean();
  }
}
if ( ! function_exists('dev_apt_free_counts_cached') ){
  function dev_apt_free_counts_cached($all_ps_terms, $target_terms, $free_slug){
    sort($all_ps_terms); sort($target_terms);
    $opts = get_option('dev_apt_options', array());
    $bump = isset($opts['cache_bump']) ? intval($opts['cache_bump']) : 1;
    $ttl = isset($opts['cache_ttl']) ? intval($opts['cache_ttl']) : 3600;
    $no_cache = !empty($GLOBALS['_DEV_APT_NO_CACHE']);
    $do_cache = ($ttl>0) && !$no_cache;
    $key = 'dev_apt_fc_'. $bump .'_'. md5(json_encode(array($all_ps_terms,$target_terms,$free_slug)));
    if($do_cache){ $cached = get_transient($key); if ( is_array($cached) ) return $cached; }
    $counts = array(); foreach($target_terms as $t){ $counts[$t]=0; }
    $free_posts = array();
    if (!empty($all_ps_terms)){
      $q = new WP_Query(array(
        'post_type' => 'apartment','posts_per_page' => -1,'fields' => 'ids','no_found_rows' => true,
        'tax_query' => array('relation' => 'AND',
          array('taxonomy' => 'project_structure','field' => 'term_id','terms' => $all_ps_terms,'include_children' => false),
          array('taxonomy' => 'apartment_status','field' => 'slug','terms' => array($free_slug)),
        ),
      ));
      if($q->have_posts()){ $free_posts = $q->posts; }
      wp_reset_postdata();
    }
    foreach($free_posts as $pid){
      $ids = wp_get_post_terms($pid,'project_structure',array('fields'=>'ids'));
      if(is_wp_error($ids) || empty($ids)) continue;
      foreach($ids as $pid_term){
        foreach($target_terms as $t){
          if ($pid_term == $t){ $counts[$t]++; continue; }
          $anc = get_ancestors($pid_term,'project_structure');
          if (in_array($t,$anc,true)) { $counts[$t]++; }
        }
      }
    }
    if($do_cache){ set_transient($key, $counts, max(60,$ttl)); }
    return $counts;
  }
}
new DEV_Map_Module_V2();
