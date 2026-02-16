<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function dev_register_taxonomies() {
    // project_structure (hierarchical)
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

    // apartment_status (flat)
    $labels_status = [
        'name'          => __( 'Statusy', 'developer-apartments' ),
        'singular_name' => __( 'Status', 'developer-apartments' ),
        'menu_name'     => __( 'Statusy', 'developer-apartments' ),
    ];
    register_taxonomy( 'apartment_status', [ 'apartment' ], [
        'hierarchical'      => false,
        'labels'            => $labels_status,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'status' ],
        'show_in_rest'      => true,
        'public'            => false,
    ] );

    // apartment_type (flat)
    $labels_type = [
        'name'          => __( 'Typy bytov', 'developer-apartments' ),
        'singular_name' => __( 'Typ bytu', 'developer-apartments' ),
        'menu_name'     => __( 'Typy bytov', 'developer-apartments' ),
    ];
    register_taxonomy( 'apartment_type', [ 'apartment' ], [
        'hierarchical'      => false,
        'labels'            => $labels_type,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'typ-bytu' ],
        'show_in_rest'      => true,
    ] );
}
add_action( 'init', 'dev_register_taxonomies' );
