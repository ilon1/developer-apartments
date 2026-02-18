<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('et_builder_ready', function(){
  if ( ! class_exists('ET_Builder_Module') ) return;
  $base = plugin_dir_path(__FILE__) . 'divi-modules/';

  // Legacy moduly zapni len dočasne (aby si otvoril staré inštancie a preniesol nastavenia)
  if ( file_exists($base.'MapModule.php') )   require_once $base.'MapModule.php';
  if ( file_exists($base.'TableModule.php') ) require_once $base.'TableModule.php';

  // ✅ Nové V2 moduly (builder-safe, s kompletnými nastaveniami)
  if ( file_exists($base.'MapModuleV2.php') )   require_once $base.'MapModuleV2.php';
  if ( file_exists($base.'TableModuleV2.php') ) require_once $base.'TableModuleV2.php';
});