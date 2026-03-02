<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Divi modul: Galéria bytu – miniatúry s Lightbox
 */
class DEV_Gallery_Module extends ET_Builder_Module {

    public $slug       = 'dev_apt_gallery';
    public $vb_support = 'on';

    public function init(){
        $this->name = 'Galéria bytu';
        $this->folder_name = 'dev_apt_byty';
        $this->settings_modal_toggles = array(
            'general' => array( 'toggles' => array( 'main' => 'Galéria', 'design' => 'Štýl' ) ),
        );
    }

    public function get_fields(){
        return array(
            'lightbox' => array(
                'label'       => 'Lightbox',
                'type'        => 'yes_no_button',
                'options'     => array( 'on' => 'Áno', 'off' => 'Nie' ),
                'default'     => 'on',
                'description' => 'Obrázky v galérii otvárať v lightboxe.',
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
            ),
            'columns' => array(
                'label'       => 'Počet stĺpcov (auto)',
                'type'        => 'text',
                'default'     => 'auto-fill',
                'description' => 'Napr. 4 pre pevné 4 stĺpce, alebo auto-fill pre automatické.',
                'tab_slug'    => 'general',
                'toggle_slug' => 'design',
            ),
            'min_width' => array(
                'label'     => 'Min. šírka miniatúry (px)',
                'type'      => 'text',
                'default'   => '100',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'gap' => array(
                'label'     => 'Medzera medzi obrázkami (px)',
                'type'      => 'range',
                'default'   => '8',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>4, 'max'=>24, 'step'=>2 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'border_radius' => array(
                'label'     => 'Zaoblenie rohov',
                'type'      => 'range',
                'default'   => '0',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>0, 'max'=>12, 'step'=>2 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
        );
    }

    private function resolve_pid(){
        return function_exists('dev_resolve_apartment_id_from_atts') ? dev_resolve_apartment_id_from_atts(array()) : 0;
    }

    public function render( $attrs, $content = null, $render_slug ){
        $lightbox = ($this->props['lightbox'] ?? 'on') === 'on';
        $cols = trim($this->props['columns'] ?? 'auto-fill');
        if($cols === '') $cols = 'auto-fill';
        $min_w = (int)($this->props['min_width'] ?? 100);
        if($min_w < 60) $min_w = 60;
        $gap = (int)($this->props['gap'] ?? 8);
        $radius = (int)($this->props['border_radius'] ?? 0);

        $pid = $this->resolve_pid();
        if ( dev_apt_is_builder() ) {
            return dev_apt_builder_placeholder( 'Galéria bytu', '◫' );
        }
        if(!$pid) return '';

        $ids = get_post_meta($pid, 'apt_gallery_ids', true);
        if(!$ids) return '';
        $arr = array_filter(array_map('intval', explode(',', $ids)));
        if(!$arr) return '';

        $uid = 'dev-gal-'.uniqid();
        $grid = (is_numeric($cols) && (int)$cols > 0) ? 'repeat('.intval($cols).', 1fr)' : 'repeat(auto-fill, minmax('.esc_attr($min_w).'px, 1fr))';
        $gal_class = 'dev-apt-gallery et_pb_module'.($lightbox ? ' et_post_gallery clearfix' : '');
        $html = '<div class="'.esc_attr($gal_class).'" id="'.esc_attr($uid).'" style="display:grid;grid-template-columns:'.esc_attr($grid).';gap:'.esc_attr($gap).'px">';
        foreach($arr as $aid){
            $full = wp_get_attachment_image_url($aid, 'large');
            $thumb = wp_get_attachment_image($aid, 'thumbnail', false, array('style'=>'width:100%;height:auto;display:block;border-radius:'.esc_attr($radius).'px;cursor:'.($lightbox?'pointer':'default')));
            if($thumb){
                if($lightbox && $full){
                    $html .= '<div class="dev-gal-item et_pb_gallery_image"><a href="'.esc_url($full).'">'.$thumb.'</a></div>';
                } else {
                    $html .= '<div class="dev-gal-item">'.$thumb.'</div>';
                }
            }
        }
        $html .= '</div>';
        return $html;
    }
}

new DEV_Gallery_Module();
