<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Divi modul: Tlačidlo pôdorys – odkaz na stiahnutie pôdorysu
 * Plná editovateľnosť štýlu cez Divi design options.
 */
class DEV_Floorplan_Button_Module extends ET_Builder_Module {

    public $slug       = 'dev_floorplan_button';
    public $vb_support = 'partial';

    public function init(){
        $this->name = 'Tlačidlo pôdorys';
        $this->folder_name = 'dev_apt_byty';
        $this->settings_modal_toggles = array(
            'general' => array( 'toggles' => array( 'main' => 'Text', 'design' => 'Štýl tlačidla', 'align' => 'Zarovnanie' ) ),
        );
    }

    public function get_fields(){
        return array(
            'label' => array(
                'label'       => 'Text tlačidla',
                'type'        => 'text',
                'default'     => 'Stiahnuť pôdorys',
                'description' => 'Prázdne = použitie z meta poľa bytu',
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
            ),
            'button_bg' => array(
                'label'     => 'Pozadie',
                'type'      => 'color-alpha',
                'default'   => '#9C9B7F',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_color' => array(
                'label'     => 'Farba textu',
                'type'      => 'color-alpha',
                'default'   => '#ffffff',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_padding' => array(
                'label'     => 'Padding',
                'type'      => 'custom_padding',
                'default'   => '12px|24px|12px|24px',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_border_radius' => array(
                'label'     => 'Zaoblenie',
                'type'      => 'range',
                'default'   => '4',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>0, 'max'=>50, 'step'=>1 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_font_size' => array(
                'label'     => 'Veľkosť písma',
                'type'      => 'range',
                'default'   => '16',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>12, 'max'=>24, 'step'=>1 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_border_width' => array(
                'label'     => 'Šírka okraja',
                'type'      => 'range',
                'default'   => '0',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>0, 'max'=>10, 'step'=>1 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_border_color' => array(
                'label'     => 'Farba okraja',
                'type'      => 'color-alpha',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'button_align' => array(
                'label'       => 'Zarovnanie (PC)',
                'type'        => 'select',
                'options'     => array( 'left' => 'Vľavo', 'center' => 'Na stred', 'right' => 'Vpravo' ),
                'default'     => 'left',
                'tab_slug'    => 'general',
                'toggle_slug' => 'align',
            ),
            'button_align_tablet' => array(
                'label'       => 'Zarovnanie (Tablet)',
                'type'        => 'select',
                'options'     => array( 'left' => 'Vľavo', 'center' => 'Na stred', 'right' => 'Vpravo' ),
                'default'     => 'left',
                'tab_slug'    => 'general',
                'toggle_slug' => 'align',
            ),
            'button_align_phone' => array(
                'label'       => 'Zarovnanie (Mobil)',
                'type'        => 'select',
                'options'     => array( 'left' => 'Vľavo', 'center' => 'Na stred', 'right' => 'Vpravo' ),
                'default'     => 'left',
                'tab_slug'    => 'general',
                'toggle_slug' => 'align',
            ),
        );
    }

    private function resolve_pid(){
        return function_exists('dev_resolve_apartment_id_from_atts') ? dev_resolve_apartment_id_from_atts(array()) : 0;
    }

    public function render( $attrs, $content = null, $render_slug ){
        $pid = $this->resolve_pid();

        $label = trim($this->props['label'] ?? '');
        if($label === '' && $pid) $label = get_post_meta($pid, 'apt_floorplan_label', true);
        if($label === '') $label = __('Stiahnuť pôdorys','developer-apartments');

        $bg    = $this->props['button_bg'] ?? '#9C9B7F';
        $color = $this->props['button_color'] ?? '#ffffff';
        $pad   = $this->props['button_padding'] ?? '12px|24px|12px|24px';
        $radius= $this->props['button_border_radius'] ?? '4';
        $fs    = $this->props['button_font_size'] ?? '16';
        $bw    = $this->props['button_border_width'] ?? '0';
        $bc    = $this->props['button_border_color'] ?? '';
        $pad_arr = array_map('trim', explode('|', $pad));
        $pad_css = isset($pad_arr[0]) ? $pad_arr[0].' '.(isset($pad_arr[1])?$pad_arr[1]:$pad_arr[0]).' '.(isset($pad_arr[2])?$pad_arr[2]:$pad_arr[0]).' '.(isset($pad_arr[3])?$pad_arr[3]:$pad_arr[1]) : '12px 24px';
        $style = 'display:inline-block;box-sizing:border-box;line-height:1.4;vertical-align:middle;font-family:inherit;font-weight:inherit;background:'.esc_attr($bg).';color:'.esc_attr($color).';padding:'.esc_attr($pad_css).';border-radius:'.esc_attr($radius).'px;font-size:'.esc_attr($fs).'px;text-decoration:none;border:'.esc_attr($bw).'px solid '.esc_attr($bc ?: 'transparent').';cursor:pointer;';

        $align = in_array( $this->props['button_align'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? $this->props['button_align'] : 'left';
        $align_t = in_array( $this->props['button_align_tablet'] ?? '', array( 'left', 'center', 'right' ), true ) ? $this->props['button_align_tablet'] : $align;
        $align_m = in_array( $this->props['button_align_phone'] ?? '', array( 'left', 'center', 'right' ), true ) ? $this->props['button_align_phone'] : $align_t;
        $uid = 'dev-floorplan-btn-'.uniqid();

        if ( dev_apt_is_builder() ) {
            return dev_apt_builder_placeholder( 'Tlačidlo pôdorys', '⬇' );
        }
        if(!$pid) return '<div class="et_pb_module dev-floorplan-btn-empty">'.__('Byt nenájdený','developer-apartments').'</div>';

        $file_id = (int) get_post_meta($pid, 'apt_floorplan_file_id', true);
        if(!$file_id){
            return '<div class="et_pb_module dev-floorplan-btn-empty">'.__('Pôdorys nie je nastavený','developer-apartments').'</div>';
        }
        $url = wp_get_attachment_url($file_id);
        if(!$url) return '';

        $wrap_style = 'text-align:'.esc_attr($align).';margin-right:12px;';
        $resp_css = '';
        if ( $align_t !== $align ) {
            $resp_css .= '@media(max-width:980px){#'.esc_attr($uid).'{text-align:'.esc_attr($align_t).'}}';
        }
        if ( $align_m !== $align_t ) {
            $resp_css .= '@media(max-width:767px){#'.esc_attr($uid).'{text-align:'.esc_attr($align_m).'}}';
        }
        $out = '<div id="'.esc_attr($uid).'" class="dev-apt-btn-wrap et_pb_module" style="'.esc_attr($wrap_style).'">';
        $out .= '<a href="'.esc_url($url).'" target="_blank" rel="noopener" class="dev-btn-floorplan" style="'.esc_attr($style).'">'.esc_html($label).'</a>';
        $out .= '</div>';
        if ( $resp_css !== '' ) $out .= '<style>'.$resp_css.'</style>';
        return $out;
    }
}

new DEV_Floorplan_Button_Module();
