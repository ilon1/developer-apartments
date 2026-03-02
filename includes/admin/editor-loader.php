<?php
if (!defined('ABSPATH')) exit;

// AJAX: uloženie mapy (JSON) pre termín – musí byť dostupný pred načítaním editora
add_action('wp_ajax_dev_save_term_map', function(){
  if (!current_user_can('manage_options')) wp_send_json_error('no perms');
  check_ajax_referer('dev_term_map', 'nonce');
  $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
  $json = isset($_POST['json']) ? wp_unslash($_POST['json']) : '';
  if ($term_id <= 0) wp_send_json_error('missing term_id');
  if ($json === '') wp_send_json_error('missing json');
  $decoded = json_decode($json);
  if (json_last_error() !== JSON_ERROR_NONE) wp_send_json_error('invalid json');
  update_term_meta($term_id, 'dev_map_data', $json);
  wp_send_json_success(array('message' => __('Mapa uložená.', 'developer-apartments')));
});

// Editor loader – ensure media modal is always available on the map editor page
add_action('admin_enqueue_scripts', function(){
  if ( ! function_exists('get_current_screen') ) return;
  $scr = get_current_screen();
  $is_custom = isset($_GET['post_type'], $_GET['page']) && $_GET['post_type']==='apartment' && $_GET['page']==='dev-map-editor';
  $is_edit = $is_custom && isset($_GET['term_id']) && (int) $_GET['term_id'] > 0;

  if ( ($scr && $scr->id==='apartment_page_dev-map-editor') || $is_custom ){
    // 1) Guarantee WordPress media modal (na edit view potrebné pre podklad mapy)
    if ( function_exists('wp_enqueue_media') ) wp_enqueue_media();
    wp_enqueue_style('thickbox');
    wp_enqueue_script('thickbox');
    wp_enqueue_script('media-upload');
    wp_enqueue_script('media-editor');
    wp_enqueue_script('media-views');

    // 2–4) Editor a panel cieľov len v režime editovania (s term_id) – na úvodnom zozname by sa panel vkladal do stránky a kazil layout
    if ( $is_edit ) {
      wp_enqueue_script('dev-term-map-editor', plugins_url('includes/admin/term-map-editor.js', dirname(__FILE__,2)), array('jquery'), '3.1.2', true);
      wp_enqueue_style('dev-term-map-targets', plugins_url('includes/admin/term-map-targets.css', dirname(__FILE__,2)), array(), '3.1.1');
      wp_enqueue_script('dev-term-map-targets', plugins_url('includes/admin/term-map-editor.targets.js', dirname(__FILE__,2)), array('jquery','dev-term-map-editor'), '3.1.1', true);
      wp_localize_script('dev-term-map-targets','DevApt', array(
        'ajax' => admin_url('admin-ajax.php'),
        'root' => (int) $_GET['term_id'],
        'save_nonce' => wp_create_nonce('dev_term_map'),
      ));
    }
  }
});
