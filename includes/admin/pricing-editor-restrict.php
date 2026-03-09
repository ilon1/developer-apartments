<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pre rolu Editor cien a statusov: na karte bytu zobraziť len Status a Ceny, ostatné meta boxy skryť.
 */

function dev_apt_is_pricing_only_editor() {
    return defined( 'DEV_APT_CAP_PRICING' ) && current_user_can( DEV_APT_CAP_PRICING ) && ! current_user_can( 'manage_options' );
}

add_action( 'add_meta_boxes', function() {
    if ( ! dev_apt_is_pricing_only_editor() ) return;
    remove_meta_box( 'dev_apartment_meta_v2', 'apartment', 'normal' );
    remove_meta_box( 'dev_mb_type', 'apartment', 'side' );
    remove_meta_box( 'dev_apt_overlay', 'apartment', 'side' );
    add_meta_box( 'dev_apt_pricing_only', __( 'Ceny (€)', 'developer-apartments' ), 'dev_apt_pricing_only_render', 'apartment', 'normal', 'high' );
}, 999 );

add_action( 'admin_menu', function() {
    if ( ! dev_apt_is_pricing_only_editor() ) return;
    remove_menu_page( 'edit.php' );
}, 999 );

function dev_apt_pricing_only_render( $post ) {
    wp_nonce_field( 'dev_apt_pricing_only_save', 'dev_apt_pricing_only_nonce' );
    $p_list = get_post_meta( $post->ID, 'apt_price_list', true );
    $p_disc = get_post_meta( $post->ID, 'apt_price_discount', true );
    $p_pres = get_post_meta( $post->ID, 'apt_price_presale', true );
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="apt_price_list"><?php _e( 'Cenníková cena (€)', 'developer-apartments' ); ?></label></th>
            <td><input type="number" step="1" id="apt_price_list" name="apt_price_list" value="<?php echo esc_attr( $p_list ); ?>" class="regular-text" style="max-width:220px;" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="apt_price_discount"><?php _e( 'Zvýhodnená cena (€)', 'developer-apartments' ); ?></label></th>
            <td><input type="number" step="1" id="apt_price_discount" name="apt_price_discount" value="<?php echo esc_attr( $p_disc ); ?>" class="regular-text" style="max-width:220px;" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="apt_price_presale"><?php _e( 'Cena predpredaj (€)', 'developer-apartments' ); ?></label></th>
            <td><input type="number" step="1" id="apt_price_presale" name="apt_price_presale" value="<?php echo esc_attr( $p_pres ); ?>" class="regular-text" style="max-width:220px;" /></td>
        </tr>
    </table>
    <?php
}

add_action( 'save_post_apartment', function( $post_id ) {
    if ( ! dev_apt_is_pricing_only_editor() ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( ! isset( $_POST['dev_apt_pricing_only_nonce'] ) || ! wp_verify_nonce( $_POST['dev_apt_pricing_only_nonce'], 'dev_apt_pricing_only_save' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    foreach ( [ 'apt_price_list', 'apt_price_discount', 'apt_price_presale' ] as $name ) {
        $v = isset( $_POST[ $name ] ) && $_POST[ $name ] !== '' ? (float) $_POST[ $name ] : '';
        if ( $v === '' ) delete_post_meta( $post_id, $name ); else update_post_meta( $post_id, $name, $v );
    }
}, 20 );
