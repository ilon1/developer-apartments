<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rola "Editor cien a statusov" a capability dev_apt_manage_pricing.
 * - Rola má prístup len k Ceny & Statusy (export/import), k zoznamu bytov a rýchlemu statusu, na karte bytu len k statusu a cenám.
 * - Administrator dostane tú istú capability, aby videl položku Nastavenia a mohol riešiť Ceny & Statusy.
 */

if ( ! defined( 'DEV_APT_CAP_PRICING' ) ) {
    define( 'DEV_APT_CAP_PRICING', 'dev_apt_manage_pricing' );
}

const DEV_APT_ROLE_PRICING_EDITOR = 'dev_apt_pricing_editor';

function dev_apt_install_roles_and_caps() {
    $cap = DEV_APT_CAP_PRICING;

    $admin = get_role( 'administrator' );
    if ( $admin && ! $admin->has_cap( $cap ) ) {
        $admin->add_cap( $cap );
    }

    $role = get_role( DEV_APT_ROLE_PRICING_EDITOR );
    if ( ! $role ) {
        add_role(
            DEV_APT_ROLE_PRICING_EDITOR,
            __( 'Editor cien a statusov', 'developer-apartments' ),
            [
                'read'                   => true,
                'edit_posts'             => true,
                'publish_posts'          => false,
                'delete_posts'           => false,
                'edit_others_posts'      => true,
                'delete_others_posts'    => false,
                'read_private_posts'     => true,
                'edit_private_posts'     => true,
                'delete_private_posts'   => false,
                $cap                     => true,
            ]
        );
    } else {
        if ( ! $role->has_cap( $cap ) ) {
            $role->add_cap( $cap );
        }
    }
}

add_action( 'init', function() {
    if ( get_option( 'dev_apt_roles_version' ) !== '1' ) {
        dev_apt_install_roles_and_caps();
        update_option( 'dev_apt_roles_version', '1' );
    }
}, 5 );
