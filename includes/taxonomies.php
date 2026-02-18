<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function dev_register_taxonomies() {
    // project_structure (nezmenené)
    $labels_struct = [
        'name'          => __( 'Štruktúra projektu', 'developer-apartments' ),
        'singular_name' => __( 'Štruktúra', 'developer-apartments' ),
        'menu_name'     => __( 'Štruktúra projektu', 'developer-apartments' ),
    ];
    register_taxonomy( 'project_structure', [ 'apartment' ], [
        'hierarchical'      => true,
        'labels'            => $labels_struct,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'ponuka' ],
        'show_in_rest'      => true,
    ] );

    // apartment_status → HIERARCHICAL + vlastný meta box (drop-down) + schovať Gutenberg panel
    $labels_status = [
        'name'          => __( 'Statusy', 'developer-apartments' ),
        'singular_name' => __( 'Status', 'developer-apartments' ),
        'menu_name'     => __( 'Statusy', 'developer-apartments' ),
    ];
    register_taxonomy( 'apartment_status', [ 'apartment' ], [
        'hierarchical'      => true,
        'labels'            => $labels_status,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'status' ],
        'show_in_rest'      => false, // skryje panel v block editore
        'public'            => false,
        'meta_box_cb'       => false, // skryje default meta box v classic editore
    ] );

    // apartment_type → HIERARCHICAL + vlastný meta box (drop-down) + schovať Gutenberg panel
    $labels_type = [
        'name'          => __( 'Typy bytov', 'developer-apartments' ),
        'singular_name' => __( 'Typ bytu', 'developer-apartments' ),
        'menu_name'     => __( 'Typy bytov', 'developer-apartments' ),
    ];
    register_taxonomy( 'apartment_type', [ 'apartment' ], [
        'hierarchical'      => true,
        'labels'            => $labels_type,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'typ-bytu' ],
        'show_in_rest'      => false, // skryje panel v block editore
        'meta_box_cb'       => false, // skryje default meta box v classic editore
    ] );
}
add_action( 'init', 'dev_register_taxonomies' );
