<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'add_meta_boxes', function(){
    add_meta_box('dev_apartment_meta', __('Detaily bytu','developer-apartments'), 'dev_apartment_meta_box_render', 'apartment', 'normal','high');
});
function dev_apartment_meta_box_render( $post ) {
    wp_nonce_field( 'save_apartment_meta', 'apartment_nonce' );
    $price = get_post_meta( $post->ID, 'apartment_price', true ); if ($price==='') $price = get_post_meta( $post->ID, 'apt_price', true );
    $area  = get_post_meta( $post->ID, 'apartment_area',  true ); if ($area==='')  $area  = get_post_meta( $post->ID, 'apt_area',  true );
    $rooms = get_post_meta( $post->ID, 'apartment_rooms', true ); if ($rooms==='') $rooms = get_post_meta( $post->ID, 'apt_rooms', true );
    $label = get_post_meta( $post->ID, 'apartment_label', true );
    ?>
    <style>.dev-apt-field{margin:8px 0;display:flex;gap:12px;align-items:center}.dev-apt-field input{max-width:220px}</style>
    <div class="dev-apt-field"><label style="width:180px;" for="apartment_label">Označenie bytu:</label>
        <input type="text" id="apartment_label" name="apartment_label" value="<?php echo esc_attr($label); ?>" />
    </div>
    <div class="dev-apt-field"><label style="width:180px;" for="apartment_price">Cena (€):</label>
        <input type="number" step="1" id="apartment_price" name="apartment_price" value="<?php echo esc_attr($price); ?>" />
    </div>
    <div class="dev-apt-field"><label style="width:180px;" for="apartment_area">Plocha (m²):</label>
        <input type="number" step="0.01" id="apartment_area" name="apartment_area" value="<?php echo esc_attr($area); ?>" />
    </div>
    <div class="dev-apt-field"><label style="width:180px;" for="apartment_rooms">Počet izieb:</label>
        <input type="number" step="1" id="apartment_rooms" name="apartment_rooms" value="<?php echo esc_attr($rooms); ?>" />
    </div>
    <?php
}
add_action( 'save_post_apartment', function( $post_id ){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( ! isset($_POST['apartment_nonce']) || ! wp_verify_nonce( $_POST['apartment_nonce'], 'save_apartment_meta' ) ) return;
    if ( ! current_user_can('edit_post', $post_id ) ) return;
    $map = [ 'apartment_price' => 'floatval', 'apartment_area' => 'floatval', 'apartment_rooms' => 'intval', 'apartment_label' => 'sanitize_text_field' ];
    foreach ($map as $key => $cast){
        if ( isset($_POST[$key]) ){
            $val = $_POST[$key];
            if ($cast==='floatval') $val = floatval($val);
            elseif ($cast==='intval') $val = intval($val);
            elseif ($cast==='sanitize_text_field') $val = sanitize_text_field($val);
            update_post_meta( $post_id, $key, $val );
        }
    }
});
