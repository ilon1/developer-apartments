<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;
$opts = get_option('dev_apt_options', []);
$del_posts    = !empty($opts['uninstall_delete_posts']);
$del_terms    = !empty($opts['uninstall_delete_terms']);
$del_termmeta = !empty($opts['uninstall_delete_term_meta']);
$del_options  = !empty($opts['uninstall_delete_options']);
if ( $del_posts ){
  $posts = get_posts(['post_type'=>'apartment','numberposts'=>-1,'post_status'=>'any','fields'=>'ids']);
  if ( ! is_wp_error($posts) ) foreach($posts as $pid) wp_delete_post($pid,true);
}
$taxes = ['project_structure','apartment_status','apartment_type'];
if ( $del_terms || $del_termmeta ){
  foreach($taxes as $tax){
    $terms = get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'fields'=>'ids']); if ( is_wp_error($terms) ) continue;
    foreach($terms as $tid){ if($del_termmeta){ $all=get_term_meta($tid); if(is_array($all)) foreach(array_keys($all) as $k) delete_term_meta($tid,$k);} if($del_terms) wp_delete_term($tid,$tax); }
  }
}
if ($del_options) delete_option('dev_apt_options');
