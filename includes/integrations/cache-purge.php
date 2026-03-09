<?php
/**
 * Voliteľná integrácia s cache pluginmi: pri úprave bytu alebo súvisiacich termov zruší príslušnú cache.
 * Podporované: WP Rocket, WP-Optimize (WPO), WP Compress (ak majú API na purge).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function dev_apt_purge_cache_for_post( $post_id ) {
    if ( ! $post_id || get_post_type( $post_id ) !== 'apartment' ) return;
    if ( function_exists( 'rocket_clean_post' ) ) {
        rocket_clean_post( $post_id );
    }
    if ( class_exists( 'WPO_Page_Cache' ) && method_exists( 'WPO_Page_Cache', 'delete_single_post_cache' ) ) {
        WPO_Page_Cache::delete_single_post_cache( $post_id );
    }
    if ( class_exists( 'WPCompress\Cache' ) && method_exists( 'WPCompress\Cache', 'purge_url' ) ) {
        $url = get_permalink( $post_id );
        if ( $url ) {
            \WPCompress\Cache::purge_url( $url );
        }
    }
}

function dev_apt_purge_cache_for_term( $term_id, $taxonomy ) {
    if ( ! in_array( $taxonomy, [ 'project_structure', 'apartment_status', 'apartment_type' ], true ) ) return;
    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain();
    }
    if ( function_exists( 'wpo_cache_flush' ) ) {
        wpo_cache_flush();
    }
}

add_action( 'save_post_apartment', function( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    dev_apt_purge_cache_for_post( $post_id );
}, 999 );

add_action( 'edited_project_structure', function( $term_id ) { dev_apt_purge_cache_for_term( $term_id, 'project_structure' ); }, 999 );
add_action( 'edited_apartment_status', function( $term_id ) { dev_apt_purge_cache_for_term( $term_id, 'apartment_status' ); }, 999 );
add_action( 'edited_apartment_type', function( $term_id ) { dev_apt_purge_cache_for_term( $term_id, 'apartment_type' ); }, 999 );
