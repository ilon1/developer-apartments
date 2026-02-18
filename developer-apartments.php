<?php
/**
 * Plugin Name: Developer Apartments
 * Description: Komplexné riešenie pre predaj bytov (CPT, taxonómie, Divi moduly, Map Editor).
 * Version: 1.3.1
 * Author: Ján Lakanda
 * Text Domain: developer-apartments
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Constants
define( 'DEV_APT_PATH', plugin_dir_path( __FILE__ ) );
define( 'DEV_APT_URL',  plugin_dir_url(  __FILE__ ) );
define( 'DEV_APT_VERSION', '2.2.1' );

class Developer_Apartments_Core {

    public function __construct(){
        $this->load_core();
        add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_front' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
    }

    private function load_core(){
        // Data structure
        require_once DEV_APT_PATH . 'includes/cpt-apartment.php';
        require_once DEV_APT_PATH . 'includes/taxonomies.php';
        require_once DEV_APT_PATH . 'includes/meta/fields.php';

        if ( file_exists( DEV_APT_PATH . 'includes/admin/term-map-editor.php' ) ) {
            require_once DEV_APT_PATH . 'includes/admin/term-map-editor.php';
        }
        if ( file_exists( DEV_APT_PATH . 'includes/helpers.php' ) ) {
            require_once DEV_APT_PATH . 'includes/helpers.php';
        }
        if ( file_exists( DEV_APT_PATH . 'includes/shortcodes.php' ) ) {
            require_once DEV_APT_PATH . 'includes/shortcodes.php';
        }

        // Divi modules
        require_once DEV_APT_PATH . 'includes/divi-loader.php';
        if ( file_exists( DEV_APT_PATH . 'includes/divi-modules/enqueue.php' ) ) {
            require_once DEV_APT_PATH . 'includes/divi-modules/enqueue.php';
        }

        // Admin (conditionally) – JEDEN blok (bez duplicity)
        if ( is_admin() ) {
            if ( file_exists( DEV_APT_PATH . 'includes/admin/quick-status.php' ) ) {
                require_once DEV_APT_PATH . 'includes/admin/quick-status.php';
            }
            if ( file_exists( DEV_APT_PATH . 'includes/admin/list-table.php' ) ) {
                require_once DEV_APT_PATH . 'includes/admin/list-table.php';
            }
            if ( file_exists( DEV_APT_PATH . 'includes/admin/meta-box-single-tax.php' ) ) {
                require_once DEV_APT_PATH . 'includes/admin/meta-box-single-tax.php';
            }
        }
    }

    public function enqueue_front(){
        $use_min = ! ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG );
        $css = $use_min ? 'assets/css/map.min.css' : 'assets/css/map.css';
        $js  = $use_min ? 'assets/js/map.min.js'  : 'assets/js/map.js';

        wp_enqueue_style( 'dev-map-css', DEV_APT_URL . $css, [], DEV_APT_VERSION );
        wp_enqueue_script( 'dev-map-js', DEV_APT_URL . $js, [ 'jquery' ], DEV_APT_VERSION, true );
        wp_localize_script( 'dev-map-js', 'DevApt', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public function enqueue_admin( $hook ){
        // Map editor assets
        if ( isset($_GET['page']) && $_GET['page'] === 'dev-map-editor' ) {
            wp_enqueue_media();
            wp_enqueue_style( 'dev-term-map-css', DEV_APT_URL . 'includes/admin/term-map-editor.css', [], DEV_APT_VERSION );
            wp_enqueue_script( 'dev-term-map-js',  DEV_APT_URL . 'includes/admin/term-map-editor.js',  [ 'jquery' ], DEV_APT_VERSION, true );
            $term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
            wp_localize_script( 'dev-term-map-js', 'DevAptAdmin', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'dev_term_map' ),
                'termId'  => $term_id,
            ] );
        }
    }
}

new Developer_Apartments_Core();