<?php
if (!defined('ABSPATH')) exit;

// One-time UID migration for polygons in term meta 'dev_map_data'
add_action('admin_init', function(){
    if (!current_user_can('manage_options')) return;
    if (get_option('devapt_polygons_uid_migrated')) return;
    $terms = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false]); if(is_wp_error($terms)) return;
    foreach($terms as $t){
        $raw = get_term_meta($t->term_id, 'dev_map_data', true); if(!$raw) continue;
        $arr = json_decode($raw, true); if(!is_array($arr)) continue; $changed=false;
        foreach($arr as &$shape){ if(empty($shape['uid'])){ $shape['uid']='p'.mt_rand(100000000,999999999); $changed=true; } }
        if($changed){ update_term_meta($t->term_id,'dev_map_data', wp_slash(json_encode($arr))); }
    }
    update_option('devapt_polygons_uid_migrated', 1, true);
});

// When saving an apartment, if overlay UID is empty but there exists a polygon on its floor that targets this post, auto-fill it
add_action('save_post_apartment', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Meta key for overlay uid (adjust if different)
    $overlay_key = 'apt_overlay_uid';
    $cur = get_post_meta($post_id, $overlay_key, true);
    if(!empty($cur)) return; // already set

    // find deepest project_structure term of this apartment
    $terms = wp_get_post_terms($post_id, 'project_structure'); if(empty($terms) || is_wp_error($terms)) return;
    $deep=null; $maxDepth=-1; foreach($terms as $t){ $d=count(get_ancestors($t->term_id,'project_structure')); if($d>$maxDepth){$maxDepth=$d;$deep=$t;} }
    if(!$deep) return;

    // read map for that term
    $raw = get_term_meta($deep->term_id, 'dev_map_data', true); if(!$raw) return; $arr=json_decode($raw,true); if(!is_array($arr)) return;
    foreach($arr as $shape){ if(isset($shape['target_type'],$shape['target_id']) && $shape['target_type']==='post' && intval($shape['target_id'])===$post_id){ if(!empty($shape['uid'])){ update_post_meta($post_id,$overlay_key,$shape['uid']); } break; } }
});
