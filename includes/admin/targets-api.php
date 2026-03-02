<?php
if (!defined('ABSPATH')) exit;

/**
 * Cieľ polygónu – len priame deti (nie samotný termín ani všetci potomkovia).
 * Pri mape na Bodový: polygóny → Poschodia (direct children).
 * Pri mape na Poschodie: žiadne termíny → prepnite na režim „Byt“.
 */
add_action('wp_ajax_devapt_get_targets', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'forbidden'],403);
    $root = isset($_GET['root']) ? intval($_GET['root']) : 0;
    $out = [];
    if ($root){
        $children = get_terms([
            'taxonomy'=>'project_structure',
            'hide_empty'=>false,
            'parent'=>$root,
            'orderby'=>'name',
            'order'=>'ASC',
        ]);
        if (!is_wp_error($children)){
            foreach($children as $t){
                $anc = array_reverse(get_ancestors($t->term_id,'project_structure'));
                $parts = [];
                foreach($anc as $a){
                    $ta = get_term($a,'project_structure');
                    if ($ta && !is_wp_error($ta)) $parts[] = $ta->name;
                }
                $parts[] = $t->name;
                $out[] = ['id'=>intval($t->term_id), 'type'=>'term', 'label'=>implode(' / ',$parts)];
            }
        }
    }
    wp_send_json_success(['items'=>$out]);
});

add_action('wp_ajax_devapt_get_apartments', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'forbidden'],403);
    $root = isset($_GET['root']) ? intval($_GET['root']) : 0; $term_ids=[];
    if($root){ $term_ids=get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids','child_of'=>$root]); if(is_wp_error($term_ids)) $term_ids=[]; $term_ids=array_unique(array_merge([$root],$term_ids)); }
    $out=[]; if(!empty($term_ids)){ $q=new WP_Query(['post_type'=>'apartment','posts_per_page'=>200,'post_status'=>['publish','draft','pending','private'],'tax_query'=>[['taxonomy'=>'project_structure','field'=>'term_id','terms'=>$term_ids,'include_children'=>true]],'orderby'=>'title','order'=>'ASC','fields'=>'ids','no_found_rows'=>true]); if($q->have_posts()){ foreach($q->posts as $pid){ $code=get_post_meta($pid,'apartment_code',true); $title=get_the_title($pid); $label=trim(($code?$code.' – ':'').$title); $out[]=['id'=>intval($pid),'type'=>'post','label'=>$label]; }} wp_reset_postdata(); }
    wp_send_json_success(['items'=>$out]);
});

add_action('wp_ajax_devapt_term_is_leaf', function(){ if(!current_user_can('manage_options')) wp_send_json_error(['msg'=>'forbidden'],403); $tid=isset($_GET['term_id'])?intval($_GET['term_id']):0; if(!$tid) wp_send_json_error(['msg'=>'missing'],400); $children=get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids','parent'=>$tid]); $leaf=(is_wp_error($children)||empty($children)); wp_send_json_success(['leaf'=>$leaf?1:0]); });

/**
 * Vyhľadávanie bytov – pre pole „Hľadať byt“ v editore.
 */
add_action('wp_ajax_devapt_search_apartments', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'forbidden'],403);
    $q = sanitize_text_field($_GET['q'] ?? $_POST['q'] ?? '');
    $root = isset($_GET['root']) ? intval($_GET['root']) : 0;
    $limit = min(100, max(5, intval($_GET['limit'] ?? 50)));
    $term_ids = [];
    if ($root){
        $term_ids = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids','child_of'=>$root]);
        if (is_wp_error($term_ids)) $term_ids = [];
        $term_ids = array_unique(array_merge([$root], $term_ids));
    }
    $args = [
        'post_type'=>'apartment',
        'posts_per_page'=>$limit,
        'post_status'=>['publish','draft','pending','private'],
        'orderby'=>'title',
        'order'=>'ASC',
        'fields'=>'ids',
        'no_found_rows'=>true,
    ];
    if (!empty($term_ids)){
        $args['tax_query'] = [['taxonomy'=>'project_structure','field'=>'term_id','terms'=>$term_ids,'include_children'=>true]];
    }
    if ($q !== ''){
        $args['s'] = $q;
    }
    $query = new WP_Query($args);
    $out = [];
    if ($query->have_posts()){
        foreach ($query->posts as $pid){
            $code = get_post_meta($pid,'apartment_code',true);
            $title = get_the_title($pid);
            $label = trim(($code ? $code.' – ' : '').$title);
            $out[] = ['id'=>intval($pid), 'type'=>'post', 'label'=>$label];
        }
        wp_reset_postdata();
    }
    wp_send_json_success(['items'=>$out]);
});
