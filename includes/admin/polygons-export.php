<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Export polygonov (dev_map_data) z term meta project_structure.
 * Integrované do Nastavení – export všetkých termínov alebo jednotlivo podľa výberu.
 * Nič nemení v databáze.
 */

/* ========================= HELPERS ========================= */
function dev_apt_poly_collect( $term_id = 0 ) {
    $args = array(
        'taxonomy'   => 'project_structure',
        'hide_empty' => false,
    );
    if ( $term_id > 0 ) {
        $args['include'] = array( $term_id );
    }
    $terms = get_terms( $args );
    if ( is_wp_error( $terms ) ) return array();

    $out = array();
    foreach ( $terms as $t ) {
        $tid = (int) $t->term_id;
        $raw = get_term_meta( $tid, 'dev_map_data', true );
        $img_id = get_term_meta( $tid, 'dev_floor_plan_id', true );
        $img_url = $img_id ? wp_get_attachment_url( $img_id ) : '';
        $shapes = null;
        $count = 0;
        $valid_json = false;
        if ( is_string( $raw ) && $raw !== '' ) {
            $tmp = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $tmp ) ) {
                $valid_json = true;
                $shapes = $tmp;
                $count = count( $tmp );
            }
        }
        $out[] = array(
            'term_id'      => $tid,
            'name'         => $t->name,
            'slug'         => $t->slug,
            'parent'       => (int) $t->parent,
            'image_id'     => $img_id ? (int) $img_id : 0,
            'image_url'    => (string) $img_url,
            'data_length'  => is_string( $raw ) ? strlen( $raw ) : 0,
            'shapes_count' => (int) $count,
            'json_valid'   => $valid_json,
            'dev_map_data' => (string) $raw,
        );
    }
    return $out;
}

/* ========================= HANDLERY ========================= */
add_action( 'admin_post_dev_apt_poly_export_json', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'forbidden' );
    check_admin_referer( 'dev_apt_poly_export_json' );
    $rows = dev_apt_poly_collect();
    $payload = array(
        'generated_at'  => current_time( 'mysql' ),
        'site'          => home_url( '/' ),
        'taxonomy'      => 'project_structure',
        'count_terms'   => count( $rows ),
        'data'          => $rows,
    );
    $name = 'dev-apt-polygons-backup-' . date( 'Ymd-His' ) . '.json';
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $name . '"' );
    echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    exit;
} );

add_action( 'admin_post_dev_apt_poly_export_csv', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'forbidden' );
    check_admin_referer( 'dev_apt_poly_export_csv' );
    $rows = dev_apt_poly_collect();
    $name = 'dev-apt-polygons-summary-' . date( 'Ymd-His' ) . '.csv';
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $name . '"' );
    echo "\xEF\xBB\xBF";
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'term_id', 'name', 'slug', 'parent', 'shapes_count', 'data_length', 'image_id', 'image_url' ), ';' );
    foreach ( $rows as $r ) {
        fputcsv( $out, array( $r['term_id'], $r['name'], $r['slug'], $r['parent'], $r['shapes_count'], $r['data_length'], $r['image_id'], $r['image_url'] ), ';' );
    }
    fclose( $out );
    exit;
} );

/** Export jedného termínu – JSON (dev_map_data + metadáta). */
add_action( 'admin_post_dev_apt_poly_export_single', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'forbidden' );
    check_admin_referer( 'dev_apt_poly_export_single' );
    $term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
    if ( ! $term_id ) wp_die( __( 'Vyberte termín (poschodie / mapu).', 'developer-apartments' ) );

    $rows = dev_apt_poly_collect( $term_id );
    if ( empty( $rows ) ) {
        wp_die( __( 'Zvolený termín neexistuje alebo nemá dáta.', 'developer-apartments' ) );
    }
    $row = $rows[0];
    $payload = array(
        'generated_at'  => current_time( 'mysql' ),
        'site'          => home_url( '/' ),
        'taxonomy'      => 'project_structure',
        'term_id'       => $row['term_id'],
        'name'          => $row['name'],
        'slug'          => $row['slug'],
        'parent'        => $row['parent'],
        'image_id'      => $row['image_id'],
        'image_url'     => $row['image_url'],
        'shapes_count'  => $row['shapes_count'],
        'dev_map_data'  => $row['dev_map_data'],
    );
    $safe_name = sanitize_file_name( $row['slug'] ?: 'term-' . $term_id );
    $name = 'dev-apt-polygons-' . $safe_name . '-' . date( 'Ymd-His' ) . '.json';
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $name . '"' );
    echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    exit;
} );

/* ========================= UI ========================= */
function dev_apt_render_polygons_export_section() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $rows = dev_apt_poly_collect();
    $total_terms = count( $rows );
    $total_with_data = 0;
    $total_shapes = 0;
    foreach ( $rows as $r ) {
        if ( $r['data_length'] > 0 ) {
            $total_with_data++;
            $total_shapes += (int) $r['shapes_count'];
        }
    }

    $ps_terms = get_terms( array( 'taxonomy' => 'project_structure', 'hide_empty' => false ) );
    if ( is_wp_error( $ps_terms ) ) $ps_terms = array();
    ?>
    <hr/>
    <h2><?php _e( 'Export polygonov (mapy)', 'developer-apartments' ); ?></h2>
    <p class="description"><?php _e( 'Záloha polí dev_map_data a dev_floor_plan_id pre taxonómiu project_structure. Nič sa v databáze nemení.', 'developer-apartments' ); ?></p>
    <ul style="margin: 8px 0;">
        <li><?php printf( __( 'Počet termínov: %s', 'developer-apartments' ), '<strong>' . (int) $total_terms . '</strong>' ); ?></li>
        <li><?php printf( __( 'Termíny s dátami: %s', 'developer-apartments' ), '<strong>' . (int) $total_with_data . '</strong>' ); ?></li>
        <li><?php printf( __( 'Súhrnný počet polygonov: %s', 'developer-apartments' ), '<strong>' . (int) $total_shapes . '</strong>' ); ?></li>
    </ul>

    <h3><?php _e( 'Export všetkých termínov', 'developer-apartments' ); ?></h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 12px 0;">
        <?php wp_nonce_field( 'dev_apt_poly_export_json' ); ?>
        <input type="hidden" name="action" value="dev_apt_poly_export_json" />
        <?php submit_button( __( 'Stiahnuť JSON zálohu (všetky)', 'developer-apartments' ), 'primary', 'submit', false ); ?>
        <span class="description" style="margin-left:8px;"><?php _e( 'Raw dev_map_data pre každý termín + metadáta.', 'developer-apartments' ); ?></span>
    </form>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 12px 0;">
        <?php wp_nonce_field( 'dev_apt_poly_export_csv' ); ?>
        <input type="hidden" name="action" value="dev_apt_poly_export_csv" />
        <?php submit_button( __( 'Stiahnuť CSV sumár (všetky)', 'developer-apartments' ), 'secondary', 'submit', false ); ?>
        <span class="description" style="margin-left:8px;"><?php _e( 'Prehľad: term_id, názov, slug, počet polygonov, image_id, image_url.', 'developer-apartments' ); ?></span>
    </form>

    <h3><?php _e( 'Export jedného termínu', 'developer-apartments' ); ?></h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 12px 0;">
        <?php wp_nonce_field( 'dev_apt_poly_export_single' ); ?>
        <input type="hidden" name="action" value="dev_apt_poly_export_single" />
        <label for="dev_apt_poly_term_id"><?php _e( 'Poschodie / mapa:', 'developer-apartments' ); ?></label>
        <select name="term_id" id="dev_apt_poly_term_id" required style="margin: 0 8px;">
            <option value="">— <?php _e( 'vyberte', 'developer-apartments' ); ?> —</option>
            <?php
            foreach ( $ps_terms as $t ) {
                $shapes = 0;
                foreach ( $rows as $r ) {
                    if ( (int) $r['term_id'] === (int) $t->term_id ) {
                        $shapes = (int) $r['shapes_count'];
                        break;
                    }
                }
                $label = $t->name . ' (' . $shapes . ' ' . _n( 'polygón', 'polygónov', $shapes, 'developer-apartments' ) . ')';
                echo '<option value="' . esc_attr( $t->term_id ) . '">' . esc_html( $label ) . '</option>';
            }
            ?>
        </select>
        <?php submit_button( __( 'Stiahnuť JSON (vybraný termín)', 'developer-apartments' ), 'secondary', 'submit', false ); ?>
    </form>

    <h3><?php _e( 'Náhľad', 'developer-apartments' ); ?></h3>
    <table class="widefat striped" style="max-width: 960px;">
        <thead>
            <tr>
                <th>term_id</th>
                <th><?php _e( 'Názov', 'developer-apartments' ); ?></th>
                <th>Slug</th>
                <th><?php _e( 'Polygóny', 'developer-apartments' ); ?></th>
                <th><?php _e( 'Dátová dĺžka', 'developer-apartments' ); ?></th>
                <th>Image ID</th>
                <th>JSON</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $rows as $r ) : ?>
            <tr>
                <td><?php echo (int) $r['term_id']; ?></td>
                <td><?php echo esc_html( $r['name'] ); ?></td>
                <td><?php echo esc_html( $r['slug'] ); ?></td>
                <td><?php echo (int) $r['shapes_count']; ?></td>
                <td><?php echo (int) $r['data_length']; ?></td>
                <td><?php echo (int) $r['image_id']; ?></td>
                <td><?php echo $r['json_valid'] ? '✓' : ( $r['data_length'] > 0 ? '✗' : '—' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
