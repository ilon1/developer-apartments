<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Divi modul: Údaje bytu (jedna položka)
 * Umožňuje umiestniť jednotlivé údaje (Cena, Počet izieb, Stav, …) samostatne.
 */
class DEV_Stat_Module extends ET_Builder_Module {

    public $slug       = 'dev_apartment_stat';
    public $vb_support = 'partial';

    public function init(){
        $this->name = 'Údaje bytu';
        $this->folder_name = 'dev_apt_byty';
        $this->settings_modal_toggles = array(
            'general' => array(
                'toggles' => array(
                    'main'   => array( 'title' => 'Výber údaja' ),
                    'label'  => array( 'title' => 'Názov poľa' ),
                    'design' => array( 'title' => 'Štýl textu' ),
                ),
            ),
        );
    }

    public function get_fields(){
        return array(
            'apartment_id' => array(
                'label'       => 'ID bytu (voliteľné)',
                'type'        => 'text',
                'description' => 'Prázdne = aktuálny byt zo stránky (Theme Builder)',
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
            ),
            'stat_type' => array(
                'label'       => 'Typ údaja',
                'type'        => 'select',
                'options'     => array(
                    'price'     => 'Cena',
                    'rooms'     => 'Počet izieb',
                    'area_total'=> 'Užitková plocha',
                    'status'    => 'Stav',
                    'floor'     => 'Podlažie',
                    'interior'  => 'Plocha interiér',
                    'exterior'  => 'Plocha exteriér',
                    'cellar'    => 'Pivnica',
                ),
                'default'     => 'price',
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
            ),
            'label_custom' => array(
                'label'       => 'Vlastný názov poľa',
                'type'        => 'text',
                'description' => 'Prázdne = použiť predvolený názov podľa typu údaja',
                'tab_slug'    => 'general',
                'toggle_slug' => 'label',
            ),
            'label_font_size' => array(
                'label'     => 'Názov poľa: veľkosť písma',
                'type'      => 'range',
                'default'   => '14',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>10, 'max'=>24, 'step'=>1 ),
                'tab_slug'    => 'general',
                'toggle_slug' => 'design',
            ),
            'label_color' => array(
                'label'     => 'Názov poľa: farba',
                'type'      => 'color-alpha',
                'default'   => '',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'label_font_weight' => array(
                'label'     => 'Názov poľa: hrúbka',
                'type'      => 'select',
                'options'   => array( ''=>'Predvolená', '400'=>'Normálna', '600'=>'Polotučná', '700'=>'Tučná' ),
                'default'   => '',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'value_font_size' => array(
                'label'     => 'Dáta: veľkosť písma',
                'type'      => 'range',
                'default'   => '16',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>10, 'max'=>32, 'step'=>1 ),
                'tab_slug'    => 'general',
                'toggle_slug' => 'design',
            ),
            'value_color' => array(
                'label'     => 'Dáta: farba',
                'type'      => 'color-alpha',
                'default'   => '',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'value_font_weight' => array(
                'label'     => 'Dáta: hrúbka',
                'type'      => 'select',
                'options'   => array( ''=>'Predvolená', '400'=>'Normálna', '600'=>'Polotučná', '700'=>'Tučná' ),
                'default'   => '',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
        );
    }

    private function resolve_pid(){
        $aid = isset($this->props['apartment_id']) ? intval($this->props['apartment_id']) : 0;
        if ( $aid > 0 ) return $aid;
        return function_exists('dev_resolve_apartment_id_from_atts') ? dev_resolve_apartment_id_from_atts(array()) : 0;
    }

    private function get_stat_value( $type, $pid ){
        if ( ! $pid ) return '—';
        switch ( $type ) {
            case 'price':
                return do_shortcode('[dev_apt_price id="'.$pid.'"]');
            case 'rooms':
                $t = wp_get_post_terms($pid, 'apartment_type', array('number'=>1));
                return ($t && !is_wp_error($t)) ? esc_html($t[0]->name) : '—';
            case 'area_total':
                $v = get_post_meta($pid, 'apt_area_total', true);
                return ($v !== '' && is_numeric($v)) ? number_format((float)$v, 2, ',', ' ').' m²' : '—';
            case 'status':
                $t = wp_get_post_terms($pid, 'apartment_status', array('number'=>1));
                return ($t && !is_wp_error($t)) ? esc_html($t[0]->name) : '—';
            case 'floor':
                return do_shortcode('[dev_apt_floor id="'.$pid.'"]');
            case 'interior':
                $v = get_post_meta($pid, 'apt_area_interior', true);
                return ($v !== '' && is_numeric($v)) ? number_format((float)$v, 2, ',', ' ').' m²' : '—';
            case 'exterior':
                $v = get_post_meta($pid, 'apt_area_exterior', true);
                return ($v !== '' && is_numeric($v)) ? number_format((float)$v, 2, ',', ' ').' m²' : '—';
            case 'cellar':
                return do_shortcode('[dev_apt_cellar id="'.$pid.'"]');
            default:
                return '—';
        }
    }

    private function get_stat_label( $type ){
        $labels = array(
            'price'      => 'Cena',
            'rooms'      => 'Počet izieb',
            'area_total' => 'Užitková plocha',
            'status'     => 'Stav',
            'floor'      => 'Podlažie',
            'interior'   => 'Plocha interiér',
            'exterior'   => 'Plocha exteriér',
            'cellar'     => 'Pivnica',
        );
        return isset($labels[$type]) ? $labels[$type] : '';
    }

    public function render( $attrs, $content = null, $render_slug ){
        $type = $this->props['stat_type'] ?? 'price';
        $label = trim( (string) ( $this->props['label_custom'] ?? '' ) );
        if ( $label === '' ) $label = $this->get_stat_label( $type );

        if ( dev_apt_is_builder() ) {
            return dev_apt_builder_placeholder( 'Údaje bytu: ' . $label, '▤' );
        }

        $pid = $this->resolve_pid();
        $value = $this->get_stat_value( $type, $pid );

        $label_fs  = $this->props['label_font_size'] ?? '14';
        $label_clr = $this->props['label_color'] ?? '';
        $label_fw  = $this->props['label_font_weight'] ?? '';
        $value_fs  = $this->props['value_font_size'] ?? '16';
        $value_clr = $this->props['value_color'] ?? '';
        $value_fw  = $this->props['value_font_weight'] ?? '';

        $label_style = 'font-size:'.esc_attr($label_fs).'px;';
        if ( $label_clr !== '' ) $label_style .= 'color:'.esc_attr($label_clr).';';
        if ( $label_fw !== '' ) $label_style .= 'font-weight:'.esc_attr($label_fw).';';
        $value_style = 'font-size:'.esc_attr($value_fs).'px;';
        if ( $value_clr !== '' ) $value_style .= 'color:'.esc_attr($value_clr).';';
        if ( $value_fw !== '' ) $value_style .= 'font-weight:'.esc_attr($value_fw).';';

        return '<div class="dev-apt-stat-module et_pb_module"><div class="dev-apt-stat-label" style="'.esc_attr($label_style).'">'.esc_html($label).'</div><div class="dev-apt-stat-value" style="'.esc_attr($value_style).'">'.$value.'</div></div>';
    }
}

new DEV_Stat_Module();
