<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DEV_Breadcrumb_Module extends ET_Builder_Module {
    public $slug       = 'dev_apt_breadcrumb';
    public $vb_support = 'partial';

    public function init(){
        $this->name = 'Breadcrumb štruktúry';
        $this->folder_name = 'dev_apt_byty';
        $this->settings_modal_toggles = array(
            'general' => array( 'toggles' => array( 'main' => 'Nastavenia', 'design' => 'Štýl' ) ),
        );
    }

    public function get_fields(){
        return array(
            'separator' => array('label'=>'Oddeľovač (symbol, medzery sa pridajú automaticky)','type'=>'text','default'=>'|','tab_slug'=>'general','toggle_slug'=>'main'),
            'link' => array('label'=>'Odkazy na kategórie','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'on','tab_slug'=>'general','toggle_slug'=>'main'),
            'font_size' => array('label'=>'Veľkosť písma','type'=>'range','default'=>'14','unit'=>'px','range_settings'=>array('min'=>12,'max'=>24,'step'=>1),'tab_slug'=>'general','toggle_slug'=>'design'),
            'color' => array('label'=>'Farba textu','type'=>'color-alpha','tab_slug'=>'general','toggle_slug'=>'design'),
            'link_color' => array('label'=>'Farba odkazov','type'=>'color-alpha','default'=>'#9C9B7F','tab_slug'=>'general','toggle_slug'=>'design'),
        );
    }

    public function render($attrs,$content=null,$render_slug){
        $sep = trim($this->props['separator'] ?? '|') ?: '|';
        $do_link = ($this->props['link'] ?? 'on') === 'on';
        $fs = $this->props['font_size'] ?? '14';
        $color = $this->props['color'] ?? '';
        $link_color = $this->props['link_color'] ?? '#9C9B7F';

        if( dev_apt_is_builder() ){
            return dev_apt_builder_placeholder( 'Breadcrumb štruktúry', '»' );
        }

        $term = null;
        $qo = get_queried_object();
        if($qo && isset($qo->taxonomy) && $qo->taxonomy === 'project_structure'){
            $term = $qo;
        } elseif(is_singular('apartment')){
            $terms = wp_get_post_terms(get_the_ID(),'project_structure',array('number'=>1));
            if($terms && !is_wp_error($terms)){
                $deep = $terms[0];
                $depth = count(get_ancestors($deep->term_id,'project_structure'));
                foreach($terms as $t){
                    $d = count(get_ancestors($t->term_id,'project_structure'));
                    if($d > $depth){ $depth = $d; $deep = $t; }
                }
                $term = $deep;
            }
        }
        if(!$term || is_wp_error($term)) return '';

        $ancestors = array_reverse(get_ancestors($term->term_id,'project_structure'));
        $parts = array();
        foreach($ancestors as $aid){
            $t = get_term($aid,'project_structure');
            if(!$t || is_wp_error($t)) continue;
            $url = get_term_link($t,'project_structure');
            if($do_link && $url && !is_wp_error($url)){
                $parts[] = '<a href="'.esc_url($url).'" style="color:'.esc_attr($link_color).'">'.esc_html($t->name).'</a>';
            } else {
                $parts[] = esc_html($t->name);
            }
        }
        $url = get_term_link($term,'project_structure');
        if($do_link && $url && !is_wp_error($url)){
            $parts[] = '<a href="'.esc_url($url).'" style="color:'.esc_attr($link_color).'">'.esc_html($term->name).'</a>';
        } else {
            $parts[] = esc_html($term->name);
        }

        $style = 'font-size:'.esc_attr($fs).'px;';
        if($color) $style .= 'color:'.esc_attr($color).';';
        return '<div class="dev-breadcrumbs et_pb_module" style="'.esc_attr($style).'">'.implode(' '.esc_html($sep).' ',$parts).'</div>';
    }
}
new DEV_Breadcrumb_Module();
