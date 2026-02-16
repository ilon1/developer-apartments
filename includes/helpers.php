
<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function dev_get_field( $post_id, $key ) {
    if ( function_exists('get_field') ) {
        $value = get_field( $key, $post_id );
        return $value !== false ? $value : null;
    }
    $value = get_post_meta( $post_id, $key, true );
    return $value !== '' ? $value : null;
}
function dev_get_status( $post_id ) {
    $terms = wp_get_post_terms( $post_id, 'apartment_status', [ 'number' => 1 ] );
    if ( is_wp_error($terms) || empty($terms) ) return null;
    return $terms[0];
}
