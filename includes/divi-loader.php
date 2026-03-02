<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('et_builder_ready', function(){
  if ( ! class_exists('ET_Builder_Module') ) return;
  $base = plugin_dir_path(__FILE__) . 'divi-modules/';

  // Legacy MapModule a TableModule odstránené – používajte MapModuleV2 a TableModuleV2
  // ✅ Všetky moduly v priečinku „Byty“
  if ( file_exists($base.'MapModuleV2.php') )        require_once $base.'MapModuleV2.php';
  if ( file_exists($base.'TableModuleV2.php') )      require_once $base.'TableModuleV2.php';
  if ( file_exists($base.'StatModule.php') )         require_once $base.'StatModule.php';
  if ( file_exists($base.'FloorplanButtonModule.php') )    require_once $base.'FloorplanButtonModule.php';
  if ( file_exists($base.'ContactButtonModule.php') )      require_once $base.'ContactButtonModule.php';
  if ( file_exists($base.'BreadcrumbModule.php') )         require_once $base.'BreadcrumbModule.php';
  if ( file_exists($base.'FeaturedImageModule.php') )      require_once $base.'FeaturedImageModule.php';
  if ( file_exists($base.'SingleStatsModule.php') )        require_once $base.'SingleStatsModule.php';
  if ( file_exists($base.'SimilarApartmentsModule.php') )  require_once $base.'SimilarApartmentsModule.php';
  if ( file_exists($base.'GalleryModule.php') )            require_once $base.'GalleryModule.php';
});