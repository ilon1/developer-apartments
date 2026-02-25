
<?php
/**
 * Plugin Name: Developer Apartments
 * Description: Komplexné riešenie pre predaj bytov (CPT, taxonómie, Divi moduly, Map Editor, Export/Import).
 * Version: 2.2.1
 * Author: Ján Lakanda
 * Text Domain: developer-apartments
 * Domain Path: /languages
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined('DEV_APT_PATH') ) define( 'DEV_APT_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined('DEV_APT_URL') ) define( 'DEV_APT_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined('DEV_APT_VERSION') ) define( 'DEV_APT_VERSION', '2.2.1' );

class Developer_Apartments_Core {
  public function __construct(){
    $this->load_core();
    add_action('init', [ $this, 'load_textdomain' ]);
    add_action('wp_enqueue_scripts', [ $this, 'enqueue_front' ]);
    add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin' ]);
  }

  public function load_textdomain(){
    load_plugin_textdomain('developer-apartments', false, dirname(plugin_basename(__FILE__)) . '/languages');
  }

  private function load_core(){
    // Core entities
    require_once DEV_APT_PATH . 'includes/cpt-apartment.php';
    require_once DEV_APT_PATH . 'includes/taxonomies.php';
    require_once DEV_APT_PATH . 'includes/meta/fields.php';
    if ( file_exists( DEV_APT_PATH . 'includes/helpers.php' ) ) require_once DEV_APT_PATH . 'includes/helpers.php';
    if ( file_exists( DEV_APT_PATH . 'includes/shortcodes.php' ) ) require_once DEV_APT_PATH . 'includes/shortcodes.php';

    // Divi
    require_once DEV_APT_PATH . 'includes/divi-loader.php';
    if ( file_exists( DEV_APT_PATH . 'includes/divi-modules/enqueue.php' ) ) require_once DEV_APT_PATH . 'includes/divi-modules/enqueue.php';

    if ( is_admin() ){
      // Existing admin pages/boxes
      foreach(['settings.php','quick-status.php','list-table.php','meta-box-single-tax.php'] as $file){
        $p = DEV_APT_PATH.'includes/admin/'.$file;
        if ( file_exists($p) ) require_once $p;
      }
      
      // --- EDITOR MÁP: stránka (submenu + HTML layout) ---
      require_once DEV_APT_PATH . 'includes/admin/editor-page.php';

      // --- EDITOR MÁP (čistý loader, API, náhľad) ---
      require_once DEV_APT_PATH . 'includes/admin/editor-loader.php';
      require_once DEV_APT_PATH . 'includes/admin/targets-api.php';
      require_once DEV_APT_PATH . 'includes/admin/editor-preview.php';

      // --- KARTA BYTU: metabox na priradenie polygonu (UID) ---
      require_once DEV_APT_PATH . 'includes/admin/apartment-overlay-metabox.php';
      require_once DEV_APT_PATH . 'includes/tools/overlay-csv-import.php';
	  require_once DEV_APT_PATH . 'includes/admin/floor-plan-api.php';
    }
  }

  public function enqueue_front(){
    $use_min = !( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG );
    $css = $use_min ? 'assets/css/map.min.css' : 'assets/css/map.css';
    $js  = $use_min ? 'assets/js/map.min.js'  : 'assets/js/map.js';
    wp_enqueue_style( 'dev-map-css', DEV_APT_URL . $css, [], DEV_APT_VERSION );
    wp_enqueue_script( 'dev-map-js', DEV_APT_URL . $js, [ 'jquery' ], DEV_APT_VERSION, true );
    wp_localize_script( 'dev-map-js', 'DevApt', [ 'ajaxurl' => admin_url('admin-ajax.php') ] );
  }

  public function enqueue_admin($hook){
    if ( isset($_GET['page']) && $_GET['page']==='dev-apt-settings' ){
      // nothing extra now
    }
    // Poznámka: Stránku „Byty → Editor máp" obsluhuje includes/admin/editor-loader.php
  }
}

function dev_apt_activate(){
  require_once DEV_APT_PATH . 'includes/cpt-apartment.php';
  require_once DEV_APT_PATH . 'includes/taxonomies.php';
  flush_rewrite_rules();
}
function dev_apt_deactivate(){ flush_rewrite_rules(); }
register_activation_hook(__FILE__,'dev_apt_activate');
register_deactivation_hook(__FILE__,'dev_apt_deactivate');
new Developer_Apartments_Core();
