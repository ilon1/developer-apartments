<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Meta boxy (AJAX vyhľadávanie a výber jedného termu)
add_action( 'add_meta_boxes', function() {
    add_meta_box('dev_mb_status', __('Status bytu (jedna voľba)', 'developer-apartments'), function($post){
        dev_render_ajax_single_tax($post, 'apartment_status', 'dev_mb_status_nonce');
    }, 'apartment', 'side', 'high');

    add_meta_box('dev_mb_type', __('Typ bytu (jedna voľba)', 'developer-apartments'), function($post){
        dev_render_ajax_single_tax($post, 'apartment_type', 'dev_mb_type_nonce');
    }, 'apartment', 'side', 'default');
});

function dev_render_ajax_single_tax( $post, $taxonomy, $nonce_field ){
    wp_nonce_field( 'dev_mb_single_term_'.$taxonomy, $nonce_field );
    $current = wp_get_post_terms( $post->ID, $taxonomy, ['fields'=>'ids'] );
    $current_id = !empty($current) ? (int) $current[0] : 0;
    $current_name = '';
    if ( $current_id ){
        $t = get_term($current_id, $taxonomy); if($t && !is_wp_error($t)) $current_name = $t->name;
    }
    echo '<div class="dev-ajax-tax-box" data-tax="'.esc_attr($taxonomy).'">';
    echo '<input type="hidden" name="dev_single_term['.esc_attr($taxonomy).']" class="dev-ajax-tax-id" value="'.esc_attr($current_id).'" />';
    echo '<input type="search" class="dev-ajax-tax-search" placeholder="'.esc_attr__('Hľadať term...', 'developer-apartments').'" value="'.esc_attr($current_name).'" autocomplete="off" />';
    echo '<div class="dev-ajax-tax-results" style="display:none"></div>';
    echo '<p class="description">'.esc_html__('Začni písať aspoň 2 znaky a vyber z výsledkov.', 'developer-apartments').'</p>';
    echo '</div>';
}

// Uloženie – nahradí všetky termy jediným vybraným
add_action( 'save_post_apartment', function( $post_id ){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $map = [ 'apartment_status', 'apartment_type' ];
    foreach($map as $tax){
        $nonce_field = 'dev_mb_single_term_'.$tax;
        if ( ! isset($_POST['dev_mb_'.($tax==='apartment_status'?'status':'type').'_nonce']) ) continue;
        if ( ! wp_verify_nonce( $_POST['dev_mb_'.($tax==='apartment_status'?'status':'type').'_nonce'], $nonce_field ) ) continue;
        $new = isset($_POST['dev_single_term'][$tax]) ? intval($_POST['dev_single_term'][$tax]) : 0;
        if ( $new > 0 ) wp_set_post_terms( $post_id, [ $new ], $tax, false ); else wp_set_post_terms( $post_id, [], $tax, false );
    }
});

// AJAX: vyhľadávanie termov podľa názvu
add_action('wp_ajax_dev_tax_search', function(){
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('no perms');
    $taxonomy = sanitize_key( $_GET['taxonomy'] ?? '' );
    $q = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $taxonomy || strlen($q) < 2 ) wp_send_json_success([]);
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'name__like' => $q,
        'number' => 20,
    ]);
    if ( is_wp_error($terms) ) wp_send_json_success([]);
    $out = [];
    foreach($terms as $t){ $out[] = [ 'id'=>$t->term_id, 'text'=>$t->name ]; }
    wp_send_json_success($out);
});

// Enqueue JS/CSS iba na editáciu bytu
add_action('admin_enqueue_scripts', function($hook){
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'apartment' ) return;
    wp_enqueue_style( 'dev-ajax-tax', DEV_APT_URL.'includes/admin/single-tax.css', [], DEV_APT_VERSION );
    wp_enqueue_script( 'dev-ajax-tax', DEV_APT_URL.'includes/admin/single-tax.js', ['jquery'], DEV_APT_VERSION, true );
    wp_localize_script( 'dev-ajax-tax', 'DevAptTax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('dev_tax_search'),
    ]);
});
