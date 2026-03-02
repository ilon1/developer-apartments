<?php
if (!defined('ABSPATH')) exit;

add_action('project_structure_edit_form_fields', function($term){ if(!current_user_can('manage_options')) return; $tid=(int)$term->term_id; $raw=get_term_meta($tid,'dev_map_data',true); $img_id=get_term_meta($tid,'dev_floor_plan_id',true); $img_url=$img_id? wp_get_attachment_url($img_id):''; $coming = (int) get_term_meta($tid, 'dev_coming_soon', true); ?>
  <tr class="form-field">
    <th scope="row"><label for="dev_coming_soon"><?php esc_html_e('Neaktívna vrstva (Pripravujeme)','developer-apartments'); ?></label></th>
    <td>
      <label><input type="checkbox" name="dev_coming_soon" id="dev_coming_soon" value="1" data-term-id="<?php echo (int)$tid; ?>" <?php checked($coming, 1); ?> /> <?php esc_html_e('Označiť ako neaktívnu – na mape sa zobrazí „Pripravujeme“, klik bude vypnutý','developer-apartments'); ?></label>
      <span id="dev_coming_soon_status" style="margin-left:8px;color:#00a32a;font-size:12px;"></span>
      <p class="description"><?php esc_html_e('Uloží sa automaticky pri zaškrtnutí/odškrtnutí. Vetva zostane v Štruktúre projektu; na frontende bude deaktivovaná.','developer-apartments'); ?></p>
    </td>
  </tr>
  <tr class="form-field">
    <th scope="row"><label><?php esc_html_e('Náhľad mapy','developer-apartments'); ?></label></th>
    <td>
      <div id="dev-apt-preview" data-json="<?php echo esc_attr(is_string($raw)?$raw:''); ?>" data-img="<?php echo esc_url($img_url); ?>" style="border:1px solid #e3e3e3; padding:8px; max-width:100%;"></div>
      <p class="description">Read‑only náhľad z uložených dát (<code>dev_map_data</code>) s podkladom.</p>
    </td>
  </tr>
<?php }, 20, 1);

add_action('project_structure_add_form_fields', function(){ if(!current_user_can('manage_options')) return; ?>
  <div class="form-field">
    <label for="dev_coming_soon_new"><input type="checkbox" name="dev_coming_soon" id="dev_coming_soon_new" value="1" /> <?php esc_html_e('Neaktívna vrstva (Pripravujeme) – na mape „Pripravujeme“, bez presmerovania','developer-apartments'); ?></label>
  </div>
<?php }, 10, 0);

function dev_apt_save_coming_soon_meta( $term_id ) {
  if ( ! current_user_can( 'manage_options' ) ) return;
  $term_id = (int) $term_id;
  if ( $term_id <= 0 ) return;
  $val = ( isset( $_POST['dev_coming_soon'] ) && $_POST['dev_coming_soon'] ) ? 1 : 0;
  update_term_meta( $term_id, 'dev_coming_soon', $val );
}

add_action('created_project_structure', 'dev_apt_save_coming_soon_meta', 10, 1);
add_action('edited_project_structure', 'dev_apt_save_coming_soon_meta', 10, 1);

add_action('wp_ajax_dev_apt_save_coming_soon', function() {
  if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'forbidden' ] );
  check_ajax_referer( 'dev_apt_coming_soon', 'nonce' );
  $term_id = isset( $_POST['term_id'] ) ? (int) $_POST['term_id'] : 0;
  if ( $term_id <= 0 ) wp_send_json_error( [ 'message' => 'missing term_id' ] );
  $term = get_term( $term_id, 'project_structure' );
  if ( ! $term || is_wp_error( $term ) ) wp_send_json_error( [ 'message' => 'invalid term' ] );
  $val = ( isset( $_POST['value'] ) && $_POST['value'] ) ? 1 : 0;
  update_term_meta( $term_id, 'dev_coming_soon', $val );
  wp_send_json_success( [ 'saved' => $val ] );
});

// Fallback: pri odoslaní formulára úpravy termínu (POST) uložiť meta skoro, keďže edited_* niektoré WP inštalácie spracujú inak
add_action('admin_init', function() {
  if ( ! current_user_can( 'manage_options' ) ) return;
  if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || empty( $_POST ) ) return;
  $term_id = isset( $_POST['tag_ID'] ) ? (int) $_POST['tag_ID'] : 0;
  if ( $term_id <= 0 ) return;
  $tax = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
  if ( $tax !== 'project_structure' ) return;
  // Len pri odoslaní formulára úpravy termínu (má štandardné polia)
  if ( ! isset( $_POST['name'] ) ) return;
  $val = ( isset( $_POST['dev_coming_soon'] ) && $_POST['dev_coming_soon'] ) ? 1 : 0;
  update_term_meta( $term_id, 'dev_coming_soon', $val );
}, 5);

add_action('admin_enqueue_scripts', function($hook){ if($hook!=='term.php') return; $tax= isset($_GET['taxonomy'])? sanitize_key($_GET['taxonomy']) : ''; if($tax!=='project_structure') return; wp_enqueue_script('dev-apt-term-preview', plugins_url('includes/admin/term-map-preview.js', dirname(__FILE__,2)), [], '1.0.0', true); wp_localize_script('dev-apt-term-preview', 'devAptTermPreview', ['ajaxUrl'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('dev_apt_coming_soon'), 'i18n'=>['saved'=>'Uložené','error'=>'Chyba ukladania']]); });
