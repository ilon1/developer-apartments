<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('et_builder_ready', 'dev_register_divi_modules');
function dev_register_divi_modules(){
    if ( ! class_exists('ET_Builder_Module') ) return;
    require_once plugin_dir_path(__FILE__) . 'divi-modules/MapModule.php';
    require_once plugin_dir_path(__FILE__) . 'divi-modules/TableModule.php';
}
