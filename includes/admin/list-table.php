<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin list columns for CPT "apartment"
 * Columns: Celková výmera | Cena | Štruktúra projektu (breadcrumb) | Typ bytu | Status | Rýchly status
 * - Farebné štítky pre Status
 * - Sortable: Celková výmera (apt_area_total), Cena (IFNULL(discount, list))
 * - Horný filter predvyplníme podľa kliknutého breadcrumbu + odkaz „Vymazať filter“
 */

// ----- HEAD STYLES (badges + breadcrumb)
add_action('admin_head', function(){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'apartment') return;
    echo '<style>
    .devapt-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;line-height:1;vertical-align:middle;color:#fff}
    .devapt-badge.status-volny{background:#2e7d32}
    .devapt-badge.status-rezervovany{background:#ef6c00}
    .devapt-badge.status-predany{background:#c62828}
    .devapt-badge.status-default{background:#607d8b}
    .column-area_total, .column-price{width:120px}
    .column-status{width:140px}
    .devapt-bc-sep{opacity:.5;margin:0 4px}
    .devapt-filters-wrap{display:inline-flex;align-items:center;gap:6px}
    .devapt-clear-filter{margin-left:6px}
    </style>';
});

// ----- Define columns
add_filter('manage_apartment_posts_columns', function($cols){
    return [
        'cb'               => '<input type="checkbox" />',
        'title'            => __('Názov', 'developer-apartments'),
        'area_total'       => __('Celková výmera', 'developer-apartments'),
        'price'            => __('Cena', 'developer-apartments'),
        'project_path'     => __('Štruktúra projektu', 'developer-apartments'),
        'type'             => __('Typ bytu', 'developer-apartments'),
        'status'           => __('Status', 'developer-apartments'),
        'dev_status_quick' => __('Rýchly status', 'developer-apartments'), // vyplní quick-status.php
        'date'             => __('Dátum', 'developer-apartments'),
    ];
}, 20);

// Helper: choose deepest term in hierarchy
function devapt_deepest_term($terms){
    if (empty($terms) || is_wp_error($terms)) return null;
    $maxDepth = -1; $deepest = null;
    foreach($terms as $t){
        $depth = count(get_ancestors($t->term_id, 'project_structure'));
        if ($depth > $maxDepth){ $maxDepth = $depth; $deepest = $t; }
    }
    return $deepest;
}

// Helper: breadcrumb for project_structure for a given deepest term
function devapt_project_breadcrumb($term){
    if (!$term) return '';
    $anc = array_reverse(get_ancestors($term->term_id, 'project_structure'));
    $parts = [];
    foreach($anc as $aid){
        $a = get_term($aid, 'project_structure');
        if ($a && !is_wp_error($a)){
            $url = admin_url('edit.php?post_type=apartment&project_structure=' . $a->slug);
            $parts[] = '<a href="'.esc_url($url).'">'.esc_html($a->name).'</a>';
        }
    }
    $selfUrl = admin_url('edit.php?post_type=apartment&project_structure=' . $term->slug);
    $parts[] = '<a href="'.esc_url($selfUrl).'">'.esc_html($term->name).'</a>';
    return implode(' <span class="devapt-bc-sep">/</span> ', $parts);
}

// ----- Render columns
add_action('manage_apartment_posts_custom_column', function($col, $post_id){
    switch($col){
        case 'area_total':
            $v = get_post_meta($post_id, 'apt_area_total', true);
            echo ($v !== '' && $v !== null) ? esc_html( number_format_i18n((float)$v, 2) . ' m²' ) : '—';
        break;

        case 'price':
            $d = get_post_meta($post_id, 'apt_price_discount', true);
            $l = get_post_meta($post_id, 'apt_price_list', true);
            if ($d !== '' && $d !== null){
                echo '<strong>' . esc_html( number_format_i18n((float)$d, 0) ) . ' €</strong>';
            } elseif ($l !== '' && $l !== null){
                echo esc_html( number_format_i18n((float)$l, 0) ) . ' €';
            } else {
                echo '—';
            }
        break;

        case 'project_path':
            $terms = get_the_terms($post_id, 'project_structure');
            if (!$terms || is_wp_error($terms)){ echo '—'; break; }
            $deep = devapt_deepest_term($terms);
            $html = devapt_project_breadcrumb($deep);
            echo $html ? $html : '—';
        break;

        case 'type':
            $ty = wp_get_post_terms($post_id, 'apartment_type', ['number'=>1]);
            echo ($ty && !is_wp_error($ty)) ? esc_html($ty[0]->name) : '—';
        break;

        case 'status':
            $st = wp_get_post_terms($post_id, 'apartment_status', ['number'=>1]);
            if ($st && !is_wp_error($st)){
                $slug = sanitize_title($st[0]->slug);
                $class = in_array($slug, ['volny','rezervovany','predany'], true) ? 'status-'.$slug : 'status-default';
                echo '<span class="devapt-badge '.$class.'">'.esc_html($st[0]->name).'</span>';
            } else {
                echo '—';
            }
        break;

        // 'dev_status_quick' – renderuje quick-status.php
    }
}, 10, 2);

// ----- Top filter (dropdown) + Clear link
add_action('restrict_manage_posts', function($post_type){
    if ($post_type !== 'apartment') return;
    $selected = isset($_GET['project_structure']) ? sanitize_title($_GET['project_structure']) : '';

    echo '<div class="devapt-filters-wrap">';

    // Hierarchický dropdown projektovej štruktúry
    wp_dropdown_categories([
        'taxonomy'         => 'project_structure',
        'name'             => 'project_structure',
        'orderby'          => 'name',
        'hide_empty'       => false,
        'hierarchical'     => true,
        'show_option_all'  => '— ' . __('všetko','developer-apartments') . ' —',
        'show_count'       => false,
        'value_field'      => 'slug',
        'selected'         => $selected,
    ]);

    if ($selected) {
        $clear = admin_url('edit.php?post_type=apartment');
        echo ' <a class="button button-link devapt-clear-filter" href="'.esc_url($clear).'">'.esc_html__('Vymazať filter','developer-apartments').'</a>';
    }

    echo '</div>';

    // Auto-submit pri zmene výberu
    echo '<script>document.addEventListener("change",function(e){ if(e.target && e.target.name==="project_structure"){ e.target.form.submit(); } });</script>';
});

// ----- Apply filter + Sortable columns
add_filter('manage_edit-apartment_sortable_columns', function($cols){
    $cols['area_total'] = 'area_total';
    $cols['price']      = 'price';
    return $cols;
});

add_action('pre_get_posts', function($q){
    if (!is_admin() || !$q->is_main_query()) return;
    if ($q->get('post_type') !== 'apartment') return;

    // 1) Filter by project_structure (slug)
    if (!empty($_GET['project_structure'])){
        $slug = sanitize_title($_GET['project_structure']);
        $taxq = (array) $q->get('tax_query');
        $taxq[] = [
            'taxonomy' => 'project_structure',
            'field'    => 'slug',
            'terms'    => [$slug],
            'include_children' => true,
        ];
        $q->set('tax_query', $taxq);
    }

    // 2) Sorting
    $orderby = $q->get('orderby');
    global $wpdb;
    if ($orderby === 'area_total'){
        $q->set('meta_key','apt_area_total');
        $q->set('orderby','meta_value_num');
    } elseif ($orderby === 'price'){
        $q->set('orderby','none');
        add_filter('posts_clauses', function($clauses) use ($q, $wpdb){
            $join  = " LEFT JOIN {$wpdb->postmeta} pm_d ON pm_d.post_id = {$wpdb->posts}.ID AND pm_d.meta_key='apt_price_discount' ";
            $join .= " LEFT JOIN {$wpdb->postmeta} pm_l ON pm_l.post_id = {$wpdb->posts}.ID AND pm_l.meta_key='apt_price_list' ";
            $clauses['join'] .= $join;
            $order = strtoupper($q->get('order')) === 'ASC' ? 'ASC' : 'DESC';
            $clauses['orderby'] = " IFNULL(pm_d.meta_value+0, pm_l.meta_value+0) " . $order;
            return $clauses;
        }, 20);
    }
});
