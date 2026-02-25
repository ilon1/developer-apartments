<?php
if (!defined('ABSPATH')) exit;

// Save/clear floor plan attachment for a project_structure term
add_action('wp_ajax_devapt_set_floor_plan', function(){
  if ( ! current_user_can('manage_options') ) wp_send_json_error(array('msg'=>'forbidden'), 403);
  check_ajax_referer('devapt_floor_plan');
  $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
  $att_id  = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
  if(!$term_id) wp_send_json_error(array('msg'=>'missing term_id'),400);
  if($att_id>0){
    update_term_meta($term_id, 'dev_floor_plan_id', $att_id);
    $url = wp_get_attachment_url($att_id);
    wp_send_json_success(array('id'=>$att_id, 'url'=>$url));
  } else {
    delete_term_meta($term_id, 'dev_floor_plan_id');
    wp_send_json_success(array('id'=>0, 'url'=>''));
  }
});
