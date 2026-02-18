<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Helper to resolve post id from optional [shortcode id="123"] or current singular
function dev_resolve_apartment_id_from_atts( $atts ){
    $id = 0;
    if ( isset($atts['id']) ) $id = intval($atts['id']);
    if ( $id > 0 ) return $id;
    if ( is_singular('apartment') ) return get_the_ID();
    return 0;
}

// Floorplan button: [dev_floorplan_button] or [dev_floorplan_button id=123]
add_shortcode('dev_floorplan_button', function($atts){
    $atts = shortcode_atts(array('id'=>0,'label'=>''), $atts, 'dev_floorplan_button');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '';
    $file_id = (int)get_post_meta($pid,'apt_floorplan_file_id',true);
    if(!$file_id) return '';
    $label = $atts['label'] !== '' ? $atts['label'] : get_post_meta($pid,'apt_floorplan_label',true);
    if(!$label) $label = __('Stiahnuť pôdorys','developer-apartments');
    $url = wp_get_attachment_url($file_id);
    if(!$url) return '';
    return '<a class="button dev-btn dev-btn-floorplan" href="'.esc_url($url).'" target="_blank" rel="noopener">'.esc_html($label).'</a>';
});

// Gallery: [dev_apartment_gallery] alebo [dev_apartment_gallery id=123]
add_shortcode('dev_apartment_gallery', function($atts){
    $atts = shortcode_atts(array('id'=>0), $atts, 'dev_apartment_gallery');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '';
    $ids = get_post_meta($pid,'apt_gallery_ids',true);
    if(!$ids) return '';
    $arr = array_filter(array_map('intval', explode(',', $ids)));
    if(!$arr) return '';
    $html = '<div class="dev-apt-gallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px">';
    foreach($arr as $aid){
        $thumb = wp_get_attachment_image($aid, 'medium', false, array('style'=>'width:100%;height:auto;display:block;border:1px solid #eee'));
        if($thumb) $html .= '<div>'.$thumb.'</div>';
    }
    $html .= '</div>';
    return $html;
});
