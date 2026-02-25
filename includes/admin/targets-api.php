<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_devapt_get_targets', function(){
  if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'forbidden'], 403);
  $root = isset($_GET['root']) ? intval($_GET['root']) : 0;
  $args = [ 'taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids' ];
  if($root){ $ids = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids','child_of'=>$root]); if(is_wp_error($ids)) $ids=[]; $ids = array_unique(array_merge([$root], $ids)); $args['include']=$ids; }
  $ids = get_terms($args); if(is_wp_error($ids)) $ids=[];
  $out = [];
  foreach($ids as $tid){ $t=get_term($tid,'project_structure'); if(!$t||is_wp_error($t)) continue; $anc=array_reverse(get_ancestors($tid,'project_structure')); $parts=[]; foreach($anc as $a){ $ta=get_term($a,'project_structure'); if($ta && !is_wp_error($ta)) $parts[]=$ta->name; } $parts[]=$t->name; $out[]=['id'=>intval($tid),'type'=>'term','label'=>implode(' / ',$parts)]; }
  usort($out, function($a,$b){ return strcmp($a['label'],$b['label']); });
  wp_send_json_success(['items'=>$out]);
});

add_action('wp_ajax_devapt_get_apartments', function(){
  if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'forbidden'], 403);
  $root = isset($_GET['root']) ? intval($_GET['root']) : 0; $term_ids=[];
  if($root){ $term_ids = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids','child_of'=>$root]); if(is_wp_error($term_ids)) $term_ids=[]; $term_ids=array_unique(array_merge([$root],$term_ids)); }
  $out=[];
  if(!empty($term_ids)){
    $q=new WP_Query(['post_type'=>'apartment','posts_per_page'=>200,'post_status'=>['publish','draft','pending','private'],'tax_query'=>[['taxonomy'=>'project_structure','field'=>'term_id','terms'=>$term_ids,'include_children'=>true]],'orderby'=>'title','order'=>'ASC','fields'=>'ids','no_found_rows'=>true]);
    if($q->have_posts()){ foreach($q->posts as $pid){ $code=get_post_meta($pid,'apartment_code',true); $title=get_the_title($pid); $label=trim(($code?$code.' – ':'').$title); $out[]=['id'=>intval($pid),'type'=>'post','label'=>$label]; } }
    wp_reset_postdata();
  }
  wp_send_json_success(['items'=>$out]);
});

// new: check if term has children (leaf layer)
add_action('wp_ajax_devapt_term_is_leaf', function(){
  if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'forbidden'], 403);
  $tid = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0; if(!$tid) wp_send_json_error(['msg'=>'missing'],400);
  $children = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'fields'=>'ids','parent'=>$tid]);
  $leaf = (is_wp_error($children) || empty($children));
  wp_send_json_success(['leaf'=> $leaf ? 1 : 0]);
});
