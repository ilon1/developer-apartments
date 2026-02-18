<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'add_meta_boxes', function(){
    add_meta_box('dev_apartment_meta_v2', __('Detaily bytu','developer-apartments'), 'dev_apartment_meta_box_v2_render', 'apartment', 'normal', 'high');
});
function dev_apartment_meta_box_v2_render( $post ){
    wp_nonce_field( 'save_apartment_meta_v2', 'apartment_nonce_v2' );
    $get = function($k,$def=''){ $v=get_post_meta(get_the_ID(),$k,true); return ($v!==''?$v:$def); };
    $i_area=$get('apt_area_interior'); $e_area=$get('apt_area_exterior'); $cellar=$get('apt_cellar_area'); $cellar_yes=$get('apt_cellar_yes');
    $t_area=$get('apt_area_total'); $p_list=$get('apt_price_list'); $p_disc=$get('apt_price_discount'); $p_pres=$get('apt_price_presale');
    $floorplan_id=(int)$get('apt_floorplan_file_id',0); $floorplan_label=$get('apt_floorplan_label', __('Stiahnuť pôdorys','developer-apartments')); $gallery_ids=$get('apt_gallery_ids','');
?>
<style>
.dev-grid{display:grid;grid-template-columns:220px 1fr 140px;gap:8px;align-items:center}
.dev-grid .help{grid-column:2/span 2;color:#666;font-size:12px}
.dev-grid input[type=number]{max-width:220px}
.dev-grid .row{display:contents}
.dev-switch{display:flex;align-items:center;gap:8px}
.dev-media-row{display:flex;gap:8px;align-items:center}
.dev-gallery{display:flex;flex-wrap:wrap;gap:6px}
.dev-gallery img{height:60px;width:auto;border:1px solid #ddd}
</style>
<div class="dev-grid">
  <div class="row">
    <label for="apt_area_interior"><?php _e('Plocha interiér (m²)','developer-apartments');?></label>
    <input type="number" step="0.01" id="apt_area_interior" name="apt_area_interior" value="<?php echo esc_attr($i_area);?>" />
    <span></span>
  </div>
  <div class="row">
    <label for="apt_area_exterior"><?php _e('Plocha exteriér (m²)','developer-apartments');?></label>
    <input type="number" step="0.01" id="apt_area_exterior" name="apt_area_exterior" value="<?php echo esc_attr($e_area);?>" />
    <span></span>
  </div>
  <div class="row">
    <label for="apt_cellar_area"><?php _e('Pivnica (m²)','developer-apartments');?></label>
    <div class="dev-media-row">
      <input type="number" step="0.01" id="apt_cellar_area" name="apt_cellar_area" value="<?php echo esc_attr($cellar);?>" />
      <label class="dev-switch"><input type="checkbox" id="apt_cellar_yes" name="apt_cellar_yes" value="1" <?php checked($cellar_yes,'1');?> /> <?php _e('Iba Áno','developer-apartments');?></label>
    </div>
  </div>
  <div class="row">
    <label for="apt_area_total"><?php _e('Plocha celkom (m²)','developer-apartments');?></label>
    <input type="number" step="0.01" id="apt_area_total" name="apt_area_total" value="<?php echo esc_attr($t_area);?>" />
    <span></span>
  </div>
  <div class="row">
    <label for="apt_price_list"><?php _e('Cenníková cena (€)','developer-apartments');?></label>
    <input type="number" step="1" id="apt_price_list" name="apt_price_list" value="<?php echo esc_attr($p_list);?>" />
    <span></span>
  </div>
  <div class="row">
    <label for="apt_price_discount"><?php _e('Zvýhodnená cena (€)','developer-apartments');?></label>
    <input type="number" step="1" id="apt_price_discount" name="apt_price_discount" value="<?php echo esc_attr($p_disc);?>" />
    <span></span>
  </div>
  <div class="row">
    <label for="apt_price_presale"><?php _e('Cena predpredaj (€)','developer-apartments');?></label>
    <input type="number" step="1" id="apt_price_presale" name="apt_price_presale" value="<?php echo esc_attr($p_pres);?>" />
    <span></span>
  </div>
</div>
<hr/>
<h3><?php _e('Súbory a galéria','developer-apartments');?></h3>
<div class="dev-media-row">
  <label style="width:220px;" for="apt_floorplan_file_id"><?php _e('Pôdorys (PDF/obrázok)','developer-apartments');?></label>
  <input type="hidden" id="apt_floorplan_file_id" name="apt_floorplan_file_id" value="<?php echo esc_attr($floorplan_id);?>"/>
  <button type="button" class="button dev-pick-file" data-target="#apt_floorplan_file_id"><?php _e('Vybrať','developer-apartments');?></button>
  <button type="button" class="button dev-clear-file" data-target="#apt_floorplan_file_id"><?php _e('Vymazať','developer-apartments');?></button>
</div>
<div class="dev-media-row">
  <label for="apt_floorplan_label" style="width:220px;"><?php _e('Text tlačidla pôdorys','developer-apartments');?></label>
  <input type="text" id="apt_floorplan_label" name="apt_floorplan_label" value="<?php echo esc_attr($floorplan_label);?>" style="max-width:360px;"/>
</div>
<div id="dev_floorplan_preview" style="margin:6px 0 12px 220px;">
<?php if($floorplan_id){ $url=wp_get_attachment_url($floorplan_id); if($url){
  $is_img = strpos(get_post_mime_type($floorplan_id),'image/')===0;
  if($is_img){ echo '<img src="'.esc_url($url).'" style="max-height:80px;border:1px solid #ddd"/>'; }
  else { echo '<a href="'.esc_url($url).'" target="_blank" rel="noopener">'.esc_html(basename($url)).'</a>'; }
} } ?>
</div>
<div style="margin-top:10px;">
  <strong><?php _e('Galéria obrázkov:','developer-apartments');?></strong>
  <input type="hidden" id="apt_gallery_ids" name="apt_gallery_ids" value="<?php echo esc_attr($gallery_ids);?>"/>
  <button type="button" class="button dev-pick-gallery" data-target="#apt_gallery_ids"><?php _e('Vybrať obrázky','developer-apartments');?></button>
  <button type="button" class="button dev-clear-gallery" data-target="#apt_gallery_ids"><?php _e('Vymazať','developer-apartments');?></button>
  <div class="dev-gallery" id="dev_gallery_preview"></div>
<?php
// SERVER-SIDE náhľad galérie – vyrenderuje už uložené obrázky aj bez JS
$ids_raw = get_post_meta(get_the_ID(), 'apt_gallery_ids', true);
if ($ids_raw) {
    $ids_arr = array_filter(array_map('intval', explode(',', $ids_raw)));
    if ($ids_arr) {
        echo '<div class="dev-gallery" style="margin-top:6px;">';
        foreach ($ids_arr as $aid) {
            $thumb = wp_get_attachment_image($aid, 'thumbnail', false, ['style'=>'height:60px;width:auto;border:1px solid #ddd;margin-right:6px;']);
            if ($thumb) echo $thumb;
        }
        echo '</div>';
    }
}
?>

</div>
<script>
jQuery(function($){
  $('#apt_floorplan_file_id').on('change', function(){ var id=parseInt($(this).val(),10)||0; var $p=$('#dev_floorplan_preview'); $p.empty(); if(!id) return; var att=wp.media.attachment(id); att.fetch().then(function(){ var at=att.toJSON(); var url=at.url; var isImg=(at.type==='image'); if(isImg){ $p.append($('<img>').attr('src',url).css({maxHeight:'80px',border:'1px solid #ddd'})); } else { $p.append($('<a>').attr({href:url,target:'_blank',rel:'noopener'}).text(at.filename)); } }); });
});
</script>
<?php }

add_action('save_post_apartment', function($post_id){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( ! isset($_POST['apartment_nonce_v2']) || ! wp_verify_nonce($_POST['apartment_nonce_v2'],'save_apartment_meta_v2') ) return;
    if ( ! current_user_can('edit_post', $post_id ) ) return;
    $num = function($k){ return isset($_POST[$k]) && $_POST[$k]!=='' ? (float)$_POST[$k] : ''; };
    $txt = function($k){ return isset($_POST[$k]) ? sanitize_text_field($_POST[$k]) : ''; };
    $map = [
        'apt_area_interior' => $num('apt_area_interior'),
        'apt_area_exterior' => $num('apt_area_exterior'),
        'apt_cellar_area'   => $num('apt_cellar_area'),
        'apt_cellar_yes'    => isset($_POST['apt_cellar_yes']) ? '1' : '',
        'apt_area_total'    => $num('apt_area_total'),
        'apt_price_list'    => $num('apt_price_list'),
        'apt_price_discount'=> $num('apt_price_discount'),
        'apt_price_presale' => $num('apt_price_presale'),
        'apt_floorplan_file_id' => isset($_POST['apt_floorplan_file_id']) ? intval($_POST['apt_floorplan_file_id']) : '',
        'apt_floorplan_label'   => $txt('apt_floorplan_label'),
        'apt_gallery_ids'       => $txt('apt_gallery_ids'),
    ];
    foreach($map as $k=>$v){ if($v=== '' || $v===0){ delete_post_meta($post_id,$k); } else { update_post_meta($post_id,$k,$v); } }
});
add_action('admin_enqueue_scripts', function(){
    // Zisti post type bytovej obrazovky bez ohľadu na editor
    global $typenow;
    $pt = $typenow ?: (isset($_GET['post']) ? get_post_type((int)$_GET['post']) : (isset($_POST['post_ID']) ? get_post_type((int)$_POST['post_ID']) : ''));

    if ($pt === 'apartment') {
        // 1) Media API (nutné pre wp.media)
        wp_enqueue_media();

        // 2) Náš skript do footeru
        wp_enqueue_script(
            'dev-apt-meta-js',
            DEV_APT_URL . 'includes/admin/apt-meta.js',
            ['jquery'],
            DEV_APT_VERSION,
            true
        );

        // 3) Poistenie iniciácie aj po reload/concat – zavolá devAptMetaInit, ak existuje
        wp_add_inline_script(
            'dev-apt-meta-js',
            'console.log("dev-apt-meta: init"); jQuery(function(){ if(window.devAptMetaInit){ window.devAptMetaInit(); } });'
        );
    }
});
add_action('admin_print_footer_scripts', function(){
    global $typenow;
    $pt = $typenow ?: (isset($_GET['post']) ? get_post_type((int)$_GET['post']) : '');
    if ($pt === 'apartment') {
        echo '<script>if (typeof window.devAptMetaInit==="function"){ window.devAptMetaInit(); }</script>';
    }
});
