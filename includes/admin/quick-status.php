<?php
if (!defined('ABSPATH')) exit;

/**
 * Rýchly status – necháme existujúcu funkcionalitu,
 * ale do HEADERS pridáme stĺpec len vtedy, ak tam nie je.
 */

// Bezpečne doplniť stĺpec len ak chýba
add_filter('manage_apartment_posts_columns', function($cols){
    if (!array_key_exists('dev_status_quick', $cols)) {
        // vlož za 'status' ak existuje, inak na koniec
        $new = [];
        $inserted = false;
        foreach ($cols as $k => $v) {
            $new[$k] = $v;
            if ($k === 'status') {
                $new['dev_status_quick'] = __('Rýchly status', 'developer-apartments');
                $inserted = true;
            }
        }
        if (!$inserted) {
            $new['dev_status_quick'] = __('Rýchly status', 'developer-apartments');
        }
        return $new;
    }
    return $cols;
}, 30);

// Render selectu v stĺpci
add_action('manage_apartment_posts_custom_column', function($column,$post_id){
    if($column!=='dev_status_quick') return;

    $terms = get_terms([ 'taxonomy'=>'apartment_status', 'hide_empty'=>false ]);
    if ( is_wp_error($terms) ) { echo esc_html__('Chýbajú statusy','developer-apartments'); return; }

    $current = wp_get_post_terms($post_id,'apartment_status',[ 'fields'=>'ids' ]);
    $current_id = !empty($current)? $current[0] : 0;
    $nonce = wp_create_nonce('dev_status_nonce');

    echo '<select class="dev-quick-status" data-id="'.intval($post_id).'" data-nonce="'.esc_attr($nonce).'">';
    echo '<option value="">— ' . esc_html__('Vyberte','developer-apartments') . ' —</option>';
    foreach($terms as $t){
        printf('<option value="%d" %s>%s</option>',
            intval($t->term_id),
            selected($current_id, $t->term_id, false),
            esc_html($t->name)
        );
    }
    echo '</select> <span class="spinner" style="float:none;"></span>';
},10,2);

// AJAX handler
add_action('wp_ajax_dev_apt_quick_save', function(){
    check_ajax_referer('dev_status_nonce','nonce');

    if( ! current_user_can('edit_posts') ) wp_send_json_error('no-perms');

    $post_id = intval($_POST['post_id'] ?? 0);
    $term_id = intval($_POST['term_id'] ?? 0);
    if($post_id<=0) wp_send_json_error('no-post');

    if($term_id>0){
        $res = wp_set_post_terms($post_id, [ $term_id ], 'apartment_status', false);
    } else {
        $res = wp_set_post_terms($post_id, [], 'apartment_status', false);
    }
    if ( is_wp_error($res) ) wp_send_json_error($res->get_error_message());

    wp_send_json_success();
});

// Inline JS (ponechávam tvoju pôvodnú logiku – ak ju už máš inde, toto môžeš vynechať)
add_action('admin_footer', function(){
    $screen=get_current_screen();
    if( ! $screen || $screen->post_type!=='apartment') return;
    ?>
    <script>
    jQuery(function($){
        $(document).on('change','.dev-quick-status', function(){
            var $s=$(this), id=$s.data('id'), nonce=$s.data('nonce'), term=$s.val();
            var $sp=$s.next('.spinner'); $sp.addClass('is-active');
            $.post(ajaxurl, {
                action:'dev_apt_quick_save',
                nonce: nonce,
                post_id: id,
                term_id: term
            }).always(function(){ $sp.removeClass('is-active'); });
        });
    });
    </script>
    <?php
});