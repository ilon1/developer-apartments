<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;
$posts = get_posts(['post_type'=>'apartment','numberposts'=>-1,'post_status'=>'any','fields'=>'ids']);
foreach ($posts as $pid) { wp_delete_post($pid, true); }
$taxes = [ 'project_structure', 'apartment_status', 'apartment_type' ];
foreach ($taxes as $tax) {
    $terms = get_terms([ 'taxonomy'=>$tax, 'hide_empty'=>false, 'fields'=>'ids' ]);
    if ( is_wp_error($terms) ) continue;
    foreach ($terms as $tid) { wp_delete_term($tid, $tax); }
}
