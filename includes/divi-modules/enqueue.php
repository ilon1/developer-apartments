<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('wp_enqueue_scripts', function(){
  wp_register_style('dev-apt-map-css', DEV_APT_URL.'assets/css/map.css', array(), DEV_APT_VERSION);
  wp_register_script('dev-apt-map', DEV_APT_URL.'assets/js/map.js', array(), DEV_APT_VERSION, true);
  wp_enqueue_style('dev-apt-table', DEV_APT_URL.'includes/divi-modules/assets/table.css', array(), DEV_APT_VERSION);
  wp_enqueue_style('dev-apt-sticky', DEV_APT_URL.'assets/css/dev-sticky-breadcrumbs.css', array('dev-apt-table'), DEV_APT_VERSION);
  wp_enqueue_style( 'dev-apt-bitform-divi', DEV_APT_URL . 'assets/css/bitform-divi.css', array(), DEV_APT_VERSION );
});
add_action('admin_enqueue_scripts', function(){
  wp_enqueue_style('dev-apt-table', DEV_APT_URL.'includes/divi-modules/assets/table.css', array(), DEV_APT_VERSION);
  wp_enqueue_style('dev-apt-sticky', DEV_APT_URL.'assets/css/dev-sticky-breadcrumbs.css', array('dev-apt-table'), DEV_APT_VERSION);
});
