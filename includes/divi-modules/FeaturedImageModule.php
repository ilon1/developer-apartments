<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DEV_Featured_Image_Module extends ET_Builder_Module {
    public $slug       = 'dev_apt_featured_image';
    public $vb_support = 'on';

    public function init(){
        $this->name = 'Náhľadový obrázok bytu';
        $this->folder_name = 'dev_apt_byty';
        $this->settings_modal_toggles = array(
            'general' => array( 'toggles' => array( 'main' => 'Obrázok', 'design' => 'Štýl' ) ),
        );
    }

    public function get_fields(){
        return array(
            'image_size' => array('label'=>'Veľkosť obrázka','type'=>'select','options'=>array('thumbnail'=>'Thumbnail','medium'=>'Medium','large'=>'Large','full'=>'Full'),'default'=>'large','tab_slug'=>'general','toggle_slug'=>'main'),
            'fallback_gallery' => array('label'=>'Fallback na prvý z galérie','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'on','tab_slug'=>'general','toggle_slug'=>'main'),
            'border_radius' => array('label'=>'Zaoblenie rohov','type'=>'range','default'=>'0','unit'=>'px','range_settings'=>array('min'=>0,'max'=>24,'step'=>2),'tab_slug'=>'general','toggle_slug'=>'design'),
            'max_width' => array('label'=>'Max. šírka (%)','type'=>'range','default'=>'100','unit'=>'%','range_settings'=>array('min'=>50,'max'=>100,'step'=>5),'tab_slug'=>'general','toggle_slug'=>'design'),
        );
    }

    private function resolve_pid(){
        return function_exists('dev_resolve_apartment_id_from_atts') ? dev_resolve_apartment_id_from_atts(array()) : 0;
    }

    public function render($attrs,$content=null,$render_slug){
        $size = $this->props['image_size'] ?? 'large';
        $fallback = ($this->props['fallback_gallery'] ?? 'on') === 'on';
        $radius = $this->props['border_radius'] ?? '0';
        $max_w = $this->props['max_width'] ?? '100';

        $pid = $this->resolve_pid();
        if( dev_apt_is_builder() ){
            return dev_apt_builder_placeholder( 'Náhľadový obrázok bytu', '🖼' );
        }
        if(!$pid) return '';

        $thumb_id = get_post_thumbnail_id($pid);
        if(!$thumb_id && $fallback){
            $ids = get_post_meta($pid,'apt_gallery_ids',true);
            if($ids){
                $arr = array_filter(array_map('intval',explode(',',$ids)));
                if(!empty($arr)) $thumb_id = $arr[0];
            }
        }
        if(!$thumb_id) return '';

        $img = wp_get_attachment_image($thumb_id,$size,false,array('class'=>'dev-apt-featured-img','style'=>'width:100%;height:auto;display:block;border-radius:'.esc_attr($radius).'px'));
        if(!$img) return '';
        return '<div class="dev-apt-featured-image et_pb_module" style="max-width:'.esc_attr($max_w).'%">'.$img.'</div>';
    }
}
new DEV_Featured_Image_Module();
