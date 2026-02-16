
<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// Add quick status column after title
add_filter('manage_apartment_posts_columns', function($cols){
    $new=[]; foreach($cols as $k=>$v){ $new[$k]=$v; if($k==='title'){ $new['dev_status_quick']='⚡ Rýchly Status'; } } return $new;
});
// Render select
add_action('manage_apartment_posts_custom_column', function($column,$post_id){
    if($column!=='dev_status_quick') return;
    $terms = get_terms([ 'taxonomy'=>'apartment_status', 'hide_empty'=>false ]);
    if ( is_wp_error($terms) ) { echo 'Chýbajú statusy'; return; }
    $current = wp_get_post_terms($post_id,'apartment_status',[ 'fields'=>'ids' ]);
    $current_id = !empty($current)? $current[0] : 0;
    $nonce = wp_create_nonce('dev_status_nonce');
    echo '<select class="dev-qs" data-post="'.esc_attr($post_id).'" data-nonce="'.esc_attr($nonce).'">';
    echo '<option value="0">— Vyberte —</option>';
    foreach($terms as $t){
        $sel = selected($current_id, $t->term_id, false);
        echo '<option value="'.esc_attr($t->term_id).'" '.$sel.'>'.esc_html($t->name).'</option>';
    }
    echo '</select> <span class="dev-qs-spinner" style="display:none;">⏳</span>';
},10,2);
// AJAX handler
add_action('wp_ajax_dev_apt_quick_save', function(){
    check_ajax_referer('dev_status_nonce','nonce');
    if( ! current_user_can('edit_posts') ) wp_send_json_error('Nemáte oprávnenie');
    $post_id = intval($_POST['post_id'] ?? 0);
    $term_id = intval($_POST['term_id'] ?? 0);
    if($post_id<=0) wp_send_json_error('Chýba post_id');
    $res = ($term_id>0) ? wp_set_post_terms($post_id, [ $term_id ], 'apartment_status', false) : wp_set_post_terms($post_id, [], 'apartment_status', false);
    if ( is_wp_error($res) ) wp_send_json_error($res->get_error_message());
    wp_send_json_success();
});
// Inline JS for quick-status
add_action('admin_footer', function(){ $screen=get_current_screen(); if( ! $screen || $screen->post_type!=='apartment') return; ?>
<script>jQuery(function($){ $(document).on('change','.dev-qs',function(){var $s=$(this),pid=$s.data('post'),nonce=$s.data('nonce');$('.dev-qs-spinner').show();$.post(ajaxurl,{action:'dev_apt_quick_save',post_id:pid,term_id:$s.val(),nonce:nonce},function(){ $('.dev-qs-spinner').hide(); });});});</script>
<?php });
