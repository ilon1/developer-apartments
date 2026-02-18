<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function dev_apt_register_cpt() {
    $labels = [
        'name'          => 'Byty',
        'singular_name' => 'Byt',
        'add_new'       => 'Pridať byt',
        'add_new_item'  => 'Pridať nový byt',
        'edit_item'     => 'Upraviť byt',
        'new_item'      => 'Nový byt',
        'view_item'     => 'Zobraziť byt',
        'menu_name'     => 'Byty',
    ];
    register_post_type( 'apartment', [
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'rewrite'            => [ 'slug' => 'byty' ],
        'menu_icon'          => 'dashicons-building',
        'supports'           => [ 'title', 'editor', 'thumbnail' ],
        'show_in_rest'       => true,
        'exclude_from_search'=> true,
        'map_meta_cap'       => true,
        'show_in_menu'       => true,
        'menu_position'      => 20,
    ] );
}
add_action( 'init', 'dev_apt_register_cpt' );
