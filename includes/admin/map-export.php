<?php
if (!defined('ABSPATH')) exit;

/**
 * Export mapy (JSON / SVG) pre daný termín project_structure
 * + sťahovanie šablón (template) pre JSON/SVG.
 */

// Export mapy pre termín ako JSON alebo SVG
add_action('admin_post_devapt_map_export', function () {
    if (!current_user_can('manage_options')) wp_die('forbidden');

    $tid = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
    if (!$tid) wp_die('missing term');

    $fmt = isset($_GET['fmt']) ? strtolower(sanitize_text_field($_GET['fmt'])) : 'json';

    // načítaj uložený JSON
    $raw = get_term_meta($tid, 'dev_map_data', true);
    if (!$raw) $raw = '[]';

    // názov termínu -> bezpečný názov súboru
    $term_name = get_term_field('name', $tid, 'project_structure', 'raw');
    if (is_wp_error($term_name) || !$term_name) $term_name = 'mapa';
    $name = sanitize_title($term_name);

    if ($fmt === 'json') {
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '-map.json"');
        echo $raw;
        exit;
    }

    if ($fmt === 'svg') {
        $arr = json_decode($raw, true);
        if (!is_array($arr)) $arr = [];

        // jednoduché SVG bez podkladu (viewBox prispôsob, ak chceš)
        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2400 2400">';

        foreach ($arr as $s) {
            if (empty($s['points']) || !is_array($s['points'])) continue;

            // body polygonu
            $pts = [];
            foreach ($s['points'] as $p) {
                $x = isset($p[0]) ? intval($p[0]) : 0;
                $y = isset($p[1]) ? intval($p[1]) : 0;
                $pts[] = $x . ',' . $y;
            }

            $fill = !empty($s['color']) ? $s['color'] : '#9d9c7e';
            $uid  = !empty($s['uid']) ? $s['uid'] : '';

            $svg .= '<polygon data-uid="' . esc_attr($uid) . '" points="' . esc_attr(implode(' ', $pts)) . '" fill="' . esc_attr($fill) . '" fill-opacity="0.4" stroke="#333" stroke-width="1" />';
        }

        $svg .= '</svg>';

        nocache_headers();
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '-map.svg"');
        echo $svg;
        exit;
    }

    wp_die('unknown fmt');
});

// Stiahnutie šablón (template) pre JSON/SVG
add_action('admin_post_devapt_map_template', function () {
    if (!current_user_can('manage_options')) wp_die('forbidden');

    $type = isset($_GET['type']) ? strtolower(sanitize_text_field($_GET['type'])) : 'json';

    if ($type === 'json') {
        $tpl = [
            [
                "uid"          => "p123456789",
                "points"       => [[100, 100], [200, 120], [220, 240]],
                "color"        => "#9d9c7e",
                "hover"        => "#26a69a",
                "no_stroke"    => false,
                "target_id"    => null,
                "target_type"  => null,
                "custom_title" => "Byt X",
                "tooltip"      => ""
            ]
        ];
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="map-template.json"');
        echo wp_json_encode($tpl);
        exit;
    }

    if ($type === 'svg') {
        $svg = <<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2400 2400">
  <polygon data-uid="p123456789" points="100,100 200,120 220,240" fill="#9d9c7e" fill-opacity="0.4" stroke="#333" stroke-width="1"/>
</svg>
SVG;
        nocache_headers();
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="map-template.svg"');
        echo $svg;
        exit;
    }

    wp_die('unknown type');
});