<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Divi modul: Podobné byty – 4/3/2 (desktop/tablet/mobile)
 */
class DEV_Similar_Apartments_Module extends ET_Builder_Module {

    public $slug       = 'dev_apt_similar';
    public $vb_support = 'partial';

    public function init(){
        $this->name = 'Podobné byty';
        $this->folder_name = 'dev_apt_byty';
        $this->settings_modal_toggles = array(
            'general' => array( 'toggles' => array( 'main' => 'Počet kariet', 'design' => 'Štýl' ) ),
        );
    }

    public function get_fields(){
        return array(
            'limit_desktop' => array(
                'label'       => 'Počet na desktop',
                'type'        => 'range',
                'default'     => '4',
                'unit'        => '',
                'range_settings' => array( 'min'=>2, 'max'=>6, 'step'=>1 ),
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
            ),
            'limit_tablet' => array(
                'label'       => 'Počet na tablete',
                'type'        => 'range',
                'default'     => '3',
                'unit'        => '',
                'range_settings' => array( 'min'=>2, 'max'=>4, 'step'=>1 ),
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
            ),
            'limit_mobile' => array(
                'label'       => 'Počet na mobile',
                'type'        => 'range',
                'default'     => '2',
                'unit'        => '',
                'range_settings' => array( 'min'=>1, 'max'=>3, 'step'=>1 ),
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
            ),
            'card_border_color' => array(
                'label'     => 'Farba okraja karty',
                'type'      => 'color-alpha',
                'default'   => '#ddd',
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
            'card_border_radius' => array(
                'label'     => 'Zaoblenie karty',
                'type'      => 'range',
                'default'   => '8',
                'unit'      => 'px',
                'range_settings' => array( 'min'=>0, 'max'=>20, 'step'=>2 ),
                'tab_slug'  => 'general',
                'toggle_slug'=> 'design',
            ),
        );
    }

    private function resolve_pid(){
        return function_exists('dev_resolve_apartment_id_from_atts') ? dev_resolve_apartment_id_from_atts(array()) : 0;
    }

    private function get_thumb_url($post_id){
        $thumb_id = get_post_thumbnail_id($post_id);
        if(!$thumb_id){
            $ids = get_post_meta($post_id, 'apt_gallery_ids', true);
            if($ids){
                $arr = array_filter(array_map('intval', explode(',', $ids)));
                if(!empty($arr)) $thumb_id = $arr[0];
            }
        }
        return $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
    }

    public function render( $attrs, $content = null, $render_slug ){
        $lim_d = (int)($this->props['limit_desktop'] ?? 4);
        $lim_t = (int)($this->props['limit_tablet'] ?? 3);
        $lim_m = (int)($this->props['limit_mobile'] ?? 2);
        $border_c = $this->props['card_border_color'] ?? '#ddd';
        $radius = $this->props['card_border_radius'] ?? '8';

        $pid = $this->resolve_pid();
        if ( dev_apt_is_builder() ) {
            return dev_apt_builder_placeholder( 'Podobné byty', '⊞' );
        }
        if(!$pid) return '';

        $opts = function_exists('dev_apt_get_options') ? dev_apt_get_options() : array();
        $free_slug = !empty($opts['free_status_slug']) ? sanitize_title($opts['free_status_slug']) : 'volny';
        $type_terms = wp_get_post_terms($pid, 'apartment_type', array('fields'=>'ids'));
        $type_ids = $type_terms && !is_wp_error($type_terms) ? array_map('intval', $type_terms) : array();
        $args = array(
            'post_type'=>'apartment',
            'post_status'=>'publish',
            'posts_per_page'=>max(4, $lim_d),
            'post__not_in'=>array($pid),
            'orderby'=>'title',
            'order'=>'ASC',
        );
        $tax_query = array(
            array('taxonomy'=>'apartment_status','field'=>'slug','terms'=>array($free_slug)),
        );
        if(!empty($type_ids)){
            $tax_query[] = array('taxonomy'=>'apartment_type','field'=>'term_id','terms'=>$type_ids);
        }
        $args['tax_query'] = $tax_query;
        $q = new WP_Query($args);
        if(!$q->have_posts()) return '';

        $fmt_price = function($p){
            $pr = get_post_meta($p->ID,'apt_price_presale',true);
            $di = get_post_meta($p->ID,'apt_price_discount',true);
            $li = get_post_meta($p->ID,'apt_price_list',true);
            if($pr !== '' && is_numeric($pr)) return number_format((float)$pr, 0, ',', ' ').' €';
            if($li !== '' && $di !== '' && is_numeric($di)) return number_format((float)$di, 0, ',', ' ').' €';
            if($li !== '' && is_numeric($li)) return number_format((float)$li, 0, ',', ' ').' €';
            return __('Na vyžiadanie','developer-apartments');
        };

        $card_style = 'display:block;border:1px solid '.esc_attr($border_c).';border-radius:'.esc_attr($radius).'px;overflow:hidden;text-decoration:none;color:inherit';

        ob_start();
        echo '<div class="dev-apt-similar et_pb_module" data-limit-desktop="'.esc_attr($lim_d).'" data-limit-tablet="'.esc_attr($lim_t).'" data-limit-mobile="'.esc_attr($lim_m).'">';
        echo '<div class="dev-apt-similar-grid" style="display:grid;grid-template-columns:repeat('.esc_attr($lim_d).',1fr);gap:16px">';
        while($q->have_posts()){ $q->the_post();
            $img_url = $this->get_thumb_url(get_the_ID());
            echo '<a href="'.esc_url(get_permalink()).'" class="dev-apt-similar-card" style="'.esc_attr($card_style).'">';
            if($img_url){
                echo '<div style="aspect-ratio:4/3;overflow:hidden"><img src="'.esc_url($img_url).'" alt="" style="width:100%;height:100%;object-fit:cover"></div>';
            } else {
                echo '<div style="aspect-ratio:4/3;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;font-size:14px">—</div>';
            }
            echo '<div style="padding:12px"><strong>'.esc_html(get_the_title()).'</strong><br><span class="dev-apt-similar-price">'.$fmt_price($q->post).'</span></div></a>';
        }
        wp_reset_postdata();
        echo '</div></div>';
        echo '<style>@media(max-width:980px){.dev-apt-similar-grid{grid-template-columns:repeat('.esc_attr($lim_t).',1fr)!important}}@media(max-width:767px){.dev-apt-similar-grid{grid-template-columns:repeat('.esc_attr($lim_m).',1fr)!important;gap:12px!important}}</style>';
        return ob_get_clean();
    }
}

new DEV_Similar_Apartments_Module();
