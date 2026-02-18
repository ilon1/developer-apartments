<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style('dev-apt-table', DEV_APT_URL.'includes/divi-modules/assets/table.css', [], DEV_APT_VERSION);
});
add_action('admin_enqueue_scripts', function(){
    wp_enqueue_style('dev-apt-table', DEV_APT_URL.'includes/divi-modules/assets/table.css', [], DEV_APT_VERSION);
});
