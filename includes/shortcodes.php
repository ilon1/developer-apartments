<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Helper – či sme v Divi Visual Builder / Theme Builder live editore */
function dev_apt_is_builder(){
    if ( function_exists('et_fb_is_builder') && et_fb_is_builder() ) return true;
    if ( function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled() ) return true;
    if ( function_exists('et_fb_is_resolve_post_content_callback_ajax') && et_fb_is_resolve_post_content_callback_ajax() ) return true;
    if ( function_exists('et_fb_is_computed_callback_ajax') && et_fb_is_computed_callback_ajax() ) return true;
    if ( function_exists('et_core_is_builder_used_on_current_request') && et_core_is_builder_used_on_current_request() ) return true;
    if ( function_exists('is_et_pb_preview') && is_et_pb_preview() ) return true;
    if ( ! empty( $_GET['et_fb'] ) ) return true;
    if ( function_exists('wp_doing_ajax') && wp_doing_ajax() ) {
        $action = isset( $_REQUEST['action'] ) ? (string) $_REQUEST['action'] : '';
        if ( strpos( $action, 'et_fb' ) !== false || strpos( $action, 'et_pb' ) !== false ) return true;
    }
    return false;
}

/** Placeholder pre Divi moduly v builderi – iba názov modulu (bez ikon, bez kódu) */
function dev_apt_builder_placeholder( $label, $icon = '' ){
    return '<div class="dev-apt-builder-placeholder" style="padding:20px 16px;text-align:center;background:#f6f7f9;border:2px dashed #9C9B7F;border-radius:8px;color:#5E5D5C;font-size:15px;font-weight:600;min-height:50px">'
        .esc_html($label)
        .'</div>';
}

// Helper to resolve post id from optional [shortcode id="123"] or current singular
// Používa viacero fallbackov kvôli Theme Builder / custom post type kontextu
function dev_resolve_apartment_id_from_atts( $atts ){
    $id = 0;
    if ( isset($atts['id']) ) $id = intval($atts['id']);
    if ( $id > 0 ) return $id;
    $id = get_the_ID();
    if ( $id > 0 && get_post_type($id) === 'apartment' ) return $id;
    global $post;
    if ( $post && isset($post->ID) && get_post_type($post->ID) === 'apartment' ) return (int) $post->ID;
    $qo = get_queried_object();
    if ( $qo instanceof WP_Post && $qo->post_type === 'apartment' ) return (int) $qo->ID;
    if ( is_singular('apartment') ) return (int) get_queried_object_id();
    return 0;
}

/**
 * Štruktúra projektu – breadcrumb alebo názov.
 * Na stránke kategórie (term archive project_structure): vypíše cestu napr. "Projekt | Blok A | 1. poschodie Blok A".
 * Na stránke bytu: vypíše štruktúru priradenú k bytu.
 * Použitie v Divi: Modul Kód alebo HTML a vlož [dev_apt_structure_breadcrumb] namiesto dynamického poľa Post Categories.
 */
add_shortcode('dev_apt_structure_breadcrumb', function( $atts ) {
    $atts = shortcode_atts( array(
        'separator' => ' | ',
        'link'      => 'yes',  // yes = odkazy na term archive, no = len text
        'class'     => 'dev-breadcrumbs',
    ), $atts, 'dev_apt_structure_breadcrumb' );

    $is_builder = dev_apt_is_builder();

    $term = null;
    $qo = get_queried_object();

    if ( $qo && isset( $qo->taxonomy ) && $qo->taxonomy === 'project_structure' ) {
        $term = $qo;
    } elseif ( is_singular( 'apartment' ) ) {
        $terms = wp_get_post_terms( get_the_ID(), 'project_structure', array( 'number' => 1 ) );
        if ( $terms && ! is_wp_error( $terms ) ) {
            $deep = $terms[0];
            $depth = count( get_ancestors( $deep->term_id, 'project_structure' ) );
            foreach ( $terms as $t ) {
                $d = count( get_ancestors( $t->term_id, 'project_structure' ) );
                if ( $d > $depth ) { $depth = $d; $deep = $t; }
            }
            $term = $deep;
        }
    }

    if ( ! $term || is_wp_error( $term ) ) {
        if ( $is_builder ) {
            $class = esc_attr( $atts['class'] );
            return '<div class="' . $class . ' dev-breadcrumb-preview" style="padding:8px;background:#f6f7f9;border:1px dashed #cdd3db;border-radius:6px;color:#6b7480;font-size:14px;">Štruktúra (náhľad)</div>';
        }
        return '';
    }

    $ancestors = array_reverse( get_ancestors( $term->term_id, 'project_structure' ) );
    $parts = array();
    $do_link = ( $atts['link'] === 'yes' || $atts['link'] === '1' || $atts['link'] === 'true' );

    foreach ( $ancestors as $aid ) {
        $t = get_term( $aid, 'project_structure' );
        if ( ! $t || is_wp_error( $t ) ) continue;
        $url = get_term_link( $t, 'project_structure' );
        if ( $do_link && $url && ! is_wp_error( $url ) ) {
            $parts[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $t->name ) . '</a>';
        } else {
            $parts[] = esc_html( $t->name );
        }
    }
    if ( $do_link ) {
        $url = get_term_link( $term, 'project_structure' );
        if ( $url && ! is_wp_error( $url ) ) {
            $parts[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
        } else {
            $parts[] = esc_html( $term->name );
        }
    } else {
        $parts[] = esc_html( $term->name );
    }

    $sep = esc_html( $atts['separator'] );
    $class = esc_attr( $atts['class'] );
    return '<div class="' . $class . '">' . implode( $sep, $parts ) . '</div>';
});

// Featured image – fallback keď et_pb_post_featured_image nefunguje s CPT
// Ak nie je featured image, použije prvý obrázok z galérie (apt_gallery_ids)
add_shortcode('dev_apt_featured_image', function($atts){
    $atts = shortcode_atts(array('id'=>0, 'size'=>'large', 'class'=>'', 'fallback_gallery'=>'yes'), $atts, 'dev_apt_featured_image');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '';
    $thumb_id = get_post_thumbnail_id($pid);
    if(!$thumb_id && ($atts['fallback_gallery'] === 'yes' || $atts['fallback_gallery'] === '1')){
        $ids = get_post_meta($pid, 'apt_gallery_ids', true);
        if($ids){
            $arr = array_filter(array_map('intval', explode(',', $ids)));
            if(!empty($arr)) $thumb_id = $arr[0];
        }
    }
    if(!$thumb_id) return '';
    $img = wp_get_attachment_image($thumb_id, $atts['size'], false, array('class'=>'dev-apt-featured-img '.esc_attr($atts['class']), 'style'=>'width:100%;height:auto;display:block'));
    return $img ? '<div class="dev-apt-featured-image">'.$img.'</div>' : '';
});

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

// Gallery: [dev_apartment_gallery] alebo [dev_apartment_gallery id=123 lightbox=yes]
// Pri lightbox=yes používa Divi gallery (prev/next, swipe) – štruktúra et_post_gallery + et_pb_gallery_image
add_shortcode('dev_apartment_gallery', function($atts){
    $atts = shortcode_atts(array('id'=>0, 'lightbox'=>'no'), $atts, 'dev_apartment_gallery');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '';
    $ids = get_post_meta($pid,'apt_gallery_ids',true);
    if(!$ids) return '';
    $arr = array_filter(array_map('intval', explode(',', $ids)));
    if(!$arr) return '';
    $lightbox = ($atts['lightbox'] === 'yes' || $atts['lightbox'] === '1' || $atts['lightbox'] === 'true');
    $uid = 'dev-gal-'.uniqid();
    $html = '<div class="dev-apt-gallery'.($lightbox ? ' et_post_gallery clearfix' : '').'" id="'.esc_attr($uid).'" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px">';
    foreach($arr as $aid){
        $full = wp_get_attachment_image_url($aid, 'large');
        $thumb = wp_get_attachment_image($aid, 'thumbnail', false, array('style'=>'width:100%;height:auto;display:block;border:1px solid #eee;cursor:pointer'));
        if($thumb){
            if($lightbox && $full){
                $html .= '<div class="dev-gal-item et_pb_gallery_image"><a href="'.esc_url($full).'">'.$thumb.'</a></div>';
            } else {
                $html .= '<div class="dev-gal-item">'.$thumb.'</div>';
            }
        }
    }
    $html .= '</div>';
    return $html;
});

/**
 * Cena bytu s komplexnou logikou:
 * - ak apt_price_presale: zobraziť túto cenu
 * - ak apt_price_list a apt_price_discount: list preškrtnutý, discount ako hlavná cena
 * - ak len apt_price_list: zobraziť túto cenu
 * - inak: Na vyžiadanie
 */
add_shortcode('dev_apt_price', function($atts){
    $atts = shortcode_atts(array('id'=>0), $atts, 'dev_apt_price');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '';
    $pres = get_post_meta($pid, 'apt_price_presale', true);
    $disc = get_post_meta($pid, 'apt_price_discount', true);
    $list = get_post_meta($pid, 'apt_price_list', true);
    if($pres !== '' && is_numeric($pres)){
        return '<span class="dev-apt-price">'.number_format((float)$pres, 0, ',', ' ').' €</span>';
    }
    if($list !== '' && $disc !== '' && is_numeric($disc)){
        $list_fmt = number_format((float)$list, 0, ',', ' ').' €';
        $disc_fmt = number_format((float)$disc, 0, ',', ' ').' €';
        return '<span class="dev-apt-price-old" style="text-decoration:line-through;opacity:.7">'.$list_fmt.'</span> <strong class="dev-apt-price">'.$disc_fmt.'</strong>';
    }
    if($list !== '' && is_numeric($list)){
        return '<span class="dev-apt-price">'.number_format((float)$list, 0, ',', ' ').' €</span>';
    }
    return '<span class="dev-apt-price">'.__('Na vyžiadanie','developer-apartments').'</span>';
});

/**
 * Pivnica: ak apt_cellar_yes=1 → "Áno", inak ak apt_cellar_area číslo → výmera m²
 */
add_shortcode('dev_apt_cellar', function($atts){
    $atts = shortcode_atts(array('id'=>0), $atts, 'dev_apt_cellar');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '—';
    $yes = get_post_meta($pid, 'apt_cellar_yes', true);
    $area = get_post_meta($pid, 'apt_cellar_area', true);
    if($yes === '1') return 'Áno';
    if($area !== '' && is_numeric($area)) return number_format((float)$area, 2, ',', ' ').' m²';
    return '—';
});

/**
 * Podlažie: najnižšia vrstva (leaf) v taxonómii project_structure priradenej k bytu
 */
add_shortcode('dev_apt_floor', function($atts){
    $atts = shortcode_atts(array('id'=>0), $atts, 'dev_apt_floor');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '';
    $terms = wp_get_post_terms($pid, 'project_structure', array('number'=>1));
    if(!$terms || is_wp_error($terms)) return '';
    $term = $terms[0];
    $children = get_terms(array('taxonomy'=>'project_structure','parent'=>$term->term_id,'hide_empty'=>false));
    while($children && !is_wp_error($children) && !empty($children)){
        $term = $children[0];
        $children = get_terms(array('taxonomy'=>'project_structure','parent'=>$term->term_id,'hide_empty'=>false));
    }
    return esc_html($term->name);
});

/**
 * Štatistiky single bytu – Cena, Počet izieb, Užitková plocha, Stav, Podlažie, Interiér, Exteriér, Pivnica
 * layout: inline = riadok, blocks = mriežka blokov (ako Cukrovar)
 */
add_shortcode('dev_apt_single_stats', function($atts){
    $atts = shortcode_atts(array('id'=>0, 'layout'=>'blocks'), $atts, 'dev_apt_single_stats');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '';
    $items = array();
    $items[] = array('label'=>'Cena', 'value' => do_shortcode('[dev_apt_price id="'.$pid.'"]'), 'prominent'=>true);
    $type = wp_get_post_terms($pid, 'apartment_type', array('number'=>1));
    $items[] = array('label'=>'Počet izieb', 'value' => ($type && !is_wp_error($type)) ? esc_html($type[0]->name) : '—', 'prominent'=>false);
    $tot = get_post_meta($pid, 'apt_area_total', true);
    $items[] = array('label'=>'Užitková plocha', 'value' => ($tot !== '' && is_numeric($tot)) ? number_format((float)$tot, 2, ',', ' ').' m²' : '—', 'prominent'=>false);
    $st = wp_get_post_terms($pid, 'apartment_status', array('number'=>1));
    $items[] = array('label'=>'Stav', 'value' => ($st && !is_wp_error($st)) ? esc_html($st[0]->name) : '—', 'prominent'=>false);
    $items[] = array('label'=>'Podlažie', 'value' => do_shortcode('[dev_apt_floor id="'.$pid.'"]'), 'prominent'=>false);
    $int = get_post_meta($pid, 'apt_area_interior', true);
    $items[] = array('label'=>'Plocha interiér', 'value' => ($int !== '' && is_numeric($int)) ? number_format((float)$int, 2, ',', ' ').' m²' : '—', 'prominent'=>false);
    $ext = get_post_meta($pid, 'apt_area_exterior', true);
    $items[] = array('label'=>'Plocha exteriér', 'value' => ($ext !== '' && is_numeric($ext)) ? number_format((float)$ext, 2, ',', ' ').' m²' : '—', 'prominent'=>false);
    $items[] = array('label'=>'Pivnica', 'value' => do_shortcode('[dev_apt_cellar id="'.$pid.'"]'), 'prominent'=>false);

    if($atts['layout'] === 'inline'){
        $out = '';
        foreach($items as $i){
            $out .= '<span class="dev-stat-item"><strong>'.$i['label'].':</strong> '.$i['value'].'</span>';
            if($i !== end($items)) $out .= ' &nbsp;|&nbsp; ';
        }
        return '<div class="dev-apt-single-stats dev-apt-stats-inline">'.$out.'</div>';
    }

    // layout=blocks – mriežka blokov ako na Cukrovari
    $prominent = array_filter($items, function($x){ return !empty($x['prominent']); });
    $rest = array_filter($items, function($x){ return empty($x['prominent']); });
    $out = '<div class="dev-apt-single-stats dev-apt-stats-blocks">';
    foreach($prominent as $i){
        $out .= '<div class="dev-apt-stat-block dev-apt-stat-prominent"><div class="dev-apt-stat-label">'.esc_html($i['label']).'</div><div class="dev-apt-stat-value">'.$i['value'].'</div></div>';
    }
    $out .= '<div class="dev-apt-stat-grid">';
    foreach($rest as $i){
        $out .= '<div class="dev-apt-stat-block"><div class="dev-apt-stat-label">'.esc_html($i['label']).'</div><div class="dev-apt-stat-value">'.$i['value'].'</div></div>';
    }
    $out .= '</div></div>';
    $out .= function_exists('dev_apt_stats_blocks_style_once') ? dev_apt_stats_blocks_style_once() : '';
    return $out;
});

/**
 * Podobné byty – 4/3/2 z rovnakej apartment_type, status voľný, bez aktuálneho
 */
add_shortcode('dev_apt_similar', function($atts){
    $atts = shortcode_atts(array('id'=>0, 'limit_desktop'=>4, 'limit_tablet'=>3, 'limit_mobile'=>2), $atts, 'dev_apt_similar');
    $pid = dev_resolve_apartment_id_from_atts($atts);
    if(!$pid) return '';
    $opts = function_exists('dev_apt_get_options') ? dev_apt_get_options() : array();
    $free_slug = !empty($opts['free_status_slug']) ? sanitize_title($opts['free_status_slug']) : 'volny';
    $type_terms = wp_get_post_terms($pid, 'apartment_type', array('fields'=>'ids'));
    $type_ids = $type_terms && !is_wp_error($type_terms) ? array_map('intval', $type_terms) : array();
    $args = array(
        'post_type'=>'apartment',
        'post_status'=>'publish',
        'posts_per_page'=>max(4, (int)$atts['limit_desktop']),
        'post__not_in'=>array($pid),
        'orderby'=>'title',
        'order'=>'ASC',
    );
    $tax_query = array(
        array('taxonomy'=>'apartment_status','field'=>'slug','terms'=>array($free_slug)),
    );
    if(!empty($type_ids)){
        $tax_query[] = array('taxonomy'=>'apartment_type','field'=>'term_id','terms'=>$type_ids);
    }
    $args['tax_query'] = $tax_query;
    $q = new WP_Query($args);
    if(!$q->have_posts()) return '';
    $fmt_price = function($p){
        $pr = get_post_meta($p->ID,'apt_price_presale',true);
        $di = get_post_meta($p->ID,'apt_price_discount',true);
        $li = get_post_meta($p->ID,'apt_price_list',true);
        if($pr !== '' && is_numeric($pr)) return number_format((float)$pr, 0, ',', ' ').' €';
        if($li !== '' && $di !== '' && is_numeric($di)) return number_format((float)$di, 0, ',', ' ').' €';
        if($li !== '' && is_numeric($li)) return number_format((float)$li, 0, ',', ' ').' €';
        return __('Na vyžiadanie','developer-apartments');
    };
    ob_start();
    echo '<div class="dev-apt-similar" data-limit-desktop="'.esc_attr($atts['limit_desktop']).'" data-limit-tablet="'.esc_attr($atts['limit_tablet']).'" data-limit-mobile="'.esc_attr($atts['limit_mobile']).'">';
    echo '<div class="dev-apt-similar-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px">';
    while($q->have_posts()){ $q->the_post();
        echo '<a href="'.esc_url(get_permalink()).'" class="dev-apt-similar-card" style="display:block;border:1px solid #ddd;border-radius:8px;overflow:hidden;text-decoration:none;color:inherit">';
        if(has_post_thumbnail()){
            echo '<div style="aspect-ratio:4/3;overflow:hidden">';
            the_post_thumbnail('medium', array('style'=>'width:100%;height:100%;object-fit:cover'));
            echo '</div>';
        }
        echo '<div style="padding:12px">';
        echo '<strong>'.esc_html(get_the_title()).'</strong><br>';
        echo '<span class="dev-apt-similar-price">'.$fmt_price($q->post).'</span>';
        echo '</div></a>';
    }
    wp_reset_postdata();
    echo '</div></div>';
    echo '<style>@media(max-width:980px){.dev-apt-similar-grid{grid-template-columns:repeat(3,1fr)!important}}@media(max-width:767px){.dev-apt-similar-grid{grid-template-columns:repeat(2,1fr)!important;gap:12px!important}}</style>';
    return ob_get_clean();
});
