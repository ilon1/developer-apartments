<?php
if (!defined('ABSPATH')) exit;

// Editor loader – ensure media modal is always available on the map editor page
add_action('admin_enqueue_scripts', function(){
  if ( ! function_exists('get_current_screen') ) return;
  $scr = get_current_screen();
  $is_custom = isset($_GET['post_type'], $_GET['page']) && $_GET['post_type']==='apartment' && $_GET['page']==='dev-map-editor';
  if ( ($scr && $scr->id==='apartment_page_dev-map-editor') || $is_custom ){
    // 1) Guarantee WordPress media modal is fully loaded
    if ( function_exists('wp_enqueue_media') ) wp_enqueue_media();
    // Defensive: explicitly enqueue media dependencies (some stacks deregister them)
    wp_enqueue_style('thickbox');
    wp_enqueue_script('thickbox');
    wp_enqueue_script('media-upload');
    wp_enqueue_script('media-editor');
    wp_enqueue_script('media-views');

    // 2) Core editor (drawing & state)
    wp_enqueue_script('dev-term-map-editor', plugins_url('includes/admin/term-map-editor.js', dirname(__FILE__,2)), array('jquery'), '3.0.2', true);

    // 3) Targets UI (Structure / Apartment DB)
    wp_enqueue_style('dev-term-map-targets', plugins_url('includes/admin/term-map-targets.css', dirname(__FILE__,2)), array(), '3.0.2');
    wp_enqueue_script('dev-term-map-targets', plugins_url('includes/admin/term-map-editor.targets.js', dirname(__FILE__,2)), array('jquery','dev-term-map-editor'), '3.0.2', true);

    // 4) Localize for AJAX & context
    wp_localize_script('dev-term-map-targets','DevApt', array(
      'ajax' => admin_url('admin-ajax.php'),
      'root' => isset($_GET['term_id']) ? intval($_GET['term_id']) : 0,
    ));
  }
});
