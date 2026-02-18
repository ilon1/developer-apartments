<?php
class DEV_Map_Module extends ET_Builder_Module {
    public $slug       = 'dev_interactive_map';
    public $vb_support = 'on';

    public function init() {
        $this->name = 'Interaktívna Mapa + Tooltipy';
        $this->main_css_element = '%%order_class%%';
    }

    public function get_fields() {
        $terms   = get_terms([ 'taxonomy' => 'project_structure', 'hide_empty' => false ]);
        $options = [];
        if ( ! is_wp_error($terms) ) {
            foreach ($terms as $term) $options[$term->term_id] = $term->name;
        }
        return [
            'project_id' => [
                'label'       => 'Vyberte Projekt / Budovu',
                'type'        => 'select',
                'options'     => $options,
                'toggle_slug' => 'main_content',
                'description' => 'Primárny zdroj mapy. Ak nie je zadaný, použije sa fallback (term_id/term_slug z URL alebo prvý term s obrázkom).',
            ],
            'tt_position' => [
                'label'       => 'Pozícia Tooltipu',
                'type'        => 'select',
                'options'     => [ 'top' => 'Hore nad myšou', 'bottom' => 'Dole pod myšou', 'left' => 'Vľavo od myši', 'right' => 'Vpravo od myši' ],
                'default'     => 'top',
                'tab_slug'    => 'advanced',
                'toggle_slug' => 'tooltip_style',
            ],
            'tt_bg_color' => [ 'label' => 'Pozadie Tooltipu', 'type' => 'color-alpha', 'default' => '#333333', 'tab_slug' => 'advanced', 'toggle_slug' => 'tooltip_style' ],
            'tt_text_color' => [ 'label' => 'Farba Textu (Nadpis)', 'type' => 'color-alpha', 'default' => '#ffffff', 'tab_slug' => 'advanced', 'toggle_slug' => 'tooltip_style' ],
            'tt_padding' => [ 'label' => 'Padding Tooltipu', 'type' => 'range', 'default' => '15', 'range_settings' => [ 'min'=>0,'max'=>50,'step'=>1 ], 'tab_slug' => 'advanced', 'toggle_slug' => 'tooltip_style' ],
            'tt_radius'  => [ 'label' => 'Zaoblenie rohov (Radius)', 'type' => 'range', 'default' => '5', 'range_settings' => [ 'min'=>0,'max'=>50,'step'=>1 ], 'tab_slug' => 'advanced', 'toggle_slug' => 'tooltip_style' ],
            'count_show' => [ 'label' => 'Zobraziť počítadlo voľných bytov?', 'type' => 'yes_no_button', 'options' => [ 'on'=>'Áno','off'=>'Nie' ], 'default' => 'on', 'tab_slug' => 'advanced', 'toggle_slug' => 'count_style' ],
            'count_bg_color' => [ 'label' => 'Pozadie počítadla', 'type' => 'color-alpha', 'default' => '#28a745', 'tab_slug' => 'advanced', 'toggle_slug' => 'count_style' ],
            'count_text_color' => [ 'label' => 'Farba textu počítadla', 'type' => 'color-alpha', 'default' => '#ffffff', 'tab_slug' => 'advanced', 'toggle_slug' => 'count_style' ],
            'count_font_size' => [ 'label' => 'Veľkosť písma počítadla (px)', 'type' => 'range', 'default' => '12', 'tab_slug' => 'advanced', 'toggle_slug' => 'count_style' ],
        ];
    }
    public function get_advanced_fields_config(){
        return [ 'toggles' => [ 'advanced' => [ 'tooltip_style' => 'Štýl Tooltipu', 'count_style' => 'Štýl Počítadla' ] ], 'text' => false, 'fonts' => false ];
    }

    /**
     * Smart fallback na ID termu so zohľadnením:
     * - URL ?term_id=123 (ak existuje)
     * - URL ?term_slug=slug (ak existuje)
     * - ak je zadaný $prefer_id a nemá obrázok → prvé dieťa s obrázkom
     * - globálne: najprv top-level term s obrázkom, potom akýkoľvek s obrázkom, potom akýkoľvek term
     */
    private function dev_find_fallback_term_id( $prefer_id = 0 ){
        // 0) ?term_id=
        if ( isset($_GET['term_id']) ){
            $tid = intval($_GET['term_id']);
            if ( $tid > 0 ){
                $t = get_term( $tid, 'project_structure' );
                if ( $t && ! is_wp_error($t) ) return $tid;
            }
        }
        // 1) ?term_slug=
        if ( isset($_GET['term_slug']) && $_GET['term_slug'] !== '' ){
            $by_slug = get_term_by('slug', sanitize_title($_GET['term_slug']), 'project_structure');
            if ( $by_slug && ! is_wp_error($by_slug) ) return intval($by_slug->term_id);
        }
        // 2) preferovaný parent → ak nemá obrázok, hľadaj dieťa s obrázkom
        if ( $prefer_id ){
            $img = get_term_meta( $prefer_id, 'dev_floor_plan_id', true );
            if ( ! $img ){
                $child_with_img = get_terms([
                    'taxonomy'   => 'project_structure',
                    'hide_empty' => false,
                    'parent'     => $prefer_id,
                    'number'     => 1,
                    'meta_query' => [ [ 'key' => 'dev_floor_plan_id', 'compare' => 'EXISTS' ] ],
                ]);
                if ( ! is_wp_error($child_with_img) && ! empty($child_with_img) ) return intval($child_with_img[0]->term_id);
            }
            return $prefer_id; // nechaj pôvodný, ak sme nenašli lepší
        }
        // 3) globálne fallbacky
        // 3a) top-level s obrázkom
        $top_with_img = get_terms([
            'taxonomy'   => 'project_structure',
            'hide_empty' => false,
            'parent'     => 0,
            'number'     => 1,
            'meta_query' => [ [ 'key' => 'dev_floor_plan_id', 'compare' => 'EXISTS' ] ],
        ]);
        if ( ! is_wp_error($top_with_img) && ! empty($top_with_img) ) return intval($top_with_img[0]->term_id);
        // 3b) hociktorý s obrázkom
        $with_img = get_terms([
            'taxonomy'   => 'project_structure',
            'hide_empty' => false,
            'number'     => 1,
            'meta_query' => [ [ 'key' => 'dev_floor_plan_id', 'compare' => 'EXISTS' ] ],
        ]);
        if ( ! is_wp_error($with_img) && ! empty($with_img) ) return intval($with_img[0]->term_id);
        // 3c) hociktorý term
        $any = get_terms([ 'taxonomy'=>'project_structure', 'hide_empty'=>false, 'number'=>1 ]);
        if ( ! is_wp_error($any) && ! empty($any) ) return intval($any[0]->term_id);
        return 0;
    }

    public function render( $attrs, $content = null, $render_slug ){
        // Preferencie ID: URL ?map_id > modulové pole > fallback
        $project_id_attr = isset($this->props['project_id']) ? intval($this->props['project_id']) : 0;
        $current_id = isset($_GET['map_id']) ? intval($_GET['map_id']) : $project_id_attr;

        // Aplikuj smart fallback (zohľadní term_id/term_slug a deti s obrázkom)
        $current_id = $this->dev_find_fallback_term_id( $current_id );

        if ( ! $current_id ) {
            return $this->_render_module_wrapper('<div class="et_pb_module_inner">Vyberte projekt v nastaveniach modulu.</div>', $render_slug);
        }

        $term = get_term( $current_id );
        if ( ! $term || is_wp_error( $term ) ) return $this->_render_module_wrapper('<div class="et_pb_module_inner">Zvolená kategória/mapa neexistuje.</div>', $render_slug);

        $image_id = get_term_meta( $current_id, 'dev_floor_plan_id', true );
        // ak náhodou stále chýba obrázok, ešte posledný pokus: dieťa s obrázkom
        if ( ! $image_id ){
            $current_id = $this->dev_find_fallback_term_id( $current_id );
            $image_id   = get_term_meta( $current_id, 'dev_floor_plan_id', true );
        }
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';

        $map_data_raw = get_term_meta( $current_id, 'dev_map_data', true );
        $shapes = $map_data_raw ? json_decode( $map_data_raw ) : [];
        if ( ! $image_url ) return $this->_render_module_wrapper('<div class="et_pb_module_inner">Táto kategória nemá nastavený obrázok mapy.</div>', $render_slug);

        $free_status_slug = 'volny';
        if ( is_array($shapes) ){
            foreach ( $shapes as &$shape ){
                $label = '';
                if ( ! empty($shape->custom_title) ) {
                    $label = $shape->custom_title;
                } else {
                    if ( $shape->target_type === 'term' ){
                        $t = get_term( $shape->target_id );
                        $label = $t ? $t->name : 'Neznáme';
                    } elseif ( $shape->target_type === 'post' ){
                        $p = get_post( $shape->target_id );
                        $label = $p ? $p->post_title : 'Neznámy byt';
                    }
                }
                $shape->display_label = $label;
                $count = 0;
                if ( $shape->target_type === 'term' ){
                    $q = new WP_Query([
                        'post_type' => 'apartment',
                        'posts_per_page' => -1,
                        'fields' => 'ids',
                        'tax_query' => [
                            [ 'taxonomy'=>'project_structure','field'=>'term_id','terms'=>$shape->target_id,'include_children'=>true ],
                            [ 'taxonomy'=>'apartment_status','field'=>'slug','terms'=>$free_status_slug ],
                        ]
                    ]);
                    $count = $q->found_posts;
                } elseif ( $shape->target_type === 'post' ){
                    $is_free = has_term( $free_status_slug, 'apartment_status', $shape->target_id );
                    $count   = $is_free ? 1 : 0;
                }
                $shape->free_count = $count;
            }
        }
        $map_data_json    = wp_json_encode( $shapes );
        $map_data_escaped = esc_attr( $map_data_json );
        $style_data = sprintf(
            'data-tt-pos="%s" data-tt-bg="%s" data-tt-color="%s" data-tt-pad="%s" data-tt-rad="%s" data-cnt-show="%s" data-cnt-bg="%s" data-cnt-color="%s" data-cnt-size="%s"',
            esc_attr($this->props['tt_position']),
            esc_attr($this->props['tt_bg_color']),
            esc_attr($this->props['tt_text_color']),
            esc_attr($this->props['tt_padding']),
            esc_attr($this->props['tt_radius']),
            esc_attr($this->props['count_show']),
            esc_attr($this->props['count_bg_color']),
            esc_attr($this->props['count_text_color']),
            esc_attr($this->props['count_font_size'])
        );
        $output_html = sprintf(
            '<div class="dev-map-wrapper" id="dev-map-%d" style="position:relative;width:100%%;" %s>
                <div class="dev-map-container" style="position:relative;line-height:0;">
                    <img src="%s" class="dev-map-image" style="display:block;width:100%%;height:auto;" />
                    <svg class="dev-frontend-svg" preserveAspectRatio="none" style="position:absolute;top:0;left:0;width:100%%;height:100%%;z-index:10;" data-shapes="%s"></svg>
                </div>
                <div class="dev-map-tooltip" style="display:none;position:fixed;pointer-events:none;z-index:9999;"></div>
            </div>',
            $current_id,
            $style_data,
            esc_url($image_url),
            $map_data_escaped
        );
        return $this->_render_module_wrapper( $output_html, $render_slug );
    }
}
new DEV_Map_Module();
