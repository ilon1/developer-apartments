<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// Columns
add_filter('manage_apartment_posts_columns', function($cols){
    $new = [];
    foreach($cols as $k=>$v){
        $new[$k]=$v;
        if($k==='title'){
            $new['apartment_label']   = __('Označenie','developer-apartments');
            $new['project_structure'] = __('Projekt','developer-apartments');
            $new['apartment_price']   = __('Cena','developer-apartments');
            $new['apartment_rooms']   = __('Izby','developer-apartments');
            $new['apartment_area']    = __('Plocha','developer-apartments');
            $new['apartment_status']  = __('Status','developer-apartments');
            $new['dev_actions']       = __('Akcie','developer-apartments');
        }
    }
    return $new;
});
// Render
add_action('manage_apartment_posts_custom_column', function($col,$post_id){
    switch($col){
        case 'apartment_label': echo esc_html( get_post_meta($post_id,'apartment_label',true) ); break;
        case 'project_structure':
            $terms = get_the_terms($post_id,'project_structure');
            if($terms && !is_wp_error($terms)){
                $names = wp_list_pluck($terms,'name'); echo esc_html( implode(', ', $names) );
            } else echo '—';
            break;
        case 'apartment_price':
            $p = get_post_meta($post_id,'apartment_price',true);
            echo $p!=='' ? number_format(floatval($p),0,',',' ').' €' : '—';
            break;
        case 'apartment_rooms': echo esc_html( get_post_meta($post_id,'apartment_rooms',true) ?: '—' ); break;
        case 'apartment_area': echo esc_html( get_post_meta($post_id,'apartment_area',true) ?: '—' ); break;
        case 'apartment_status':
            $t = wp_get_post_terms($post_id,'apartment_status',[ 'number'=>1 ]);
            $name = ($t && !is_wp_error($t)) ? $t[0]->name : '—';
            $color='#666'; if($name==='Voľný') $color='green'; if($name==='Predaný') $color='red'; if($name==='Rezervovaný') $color='orange';
            echo '<span style="color:'.$color.';font-weight:bold;">'.esc_html($name).'</span>';
            break;
        case 'dev_actions': echo '<a href="'.esc_url(get_edit_post_link($post_id)).'">Upraviť</a>'; break;
    }
},10,2);
