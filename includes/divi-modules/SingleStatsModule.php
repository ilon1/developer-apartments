<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DEV_Single_Stats_Module extends ET_Builder_Module {
    public $slug       = 'dev_apt_single_stats';
    public $vb_support = 'on';

    /** Predvolené kľúče a názvy polí (pre zapínanie a vlastné názvy) */
    private static function get_default_items(){
        return array(
            'price'       => array( 'key' => 'price',       'default_label' => 'Cena',           'prominent' => true ),
            'rooms'       => array( 'key' => 'rooms',       'default_label' => 'Počet izieb',    'prominent' => false ),
            'area_total'  => array( 'key' => 'area_total',  'default_label' => 'Užitková plocha','prominent' => false ),
            'status'      => array( 'key' => 'status',      'default_label' => 'Stav',           'prominent' => false ),
            'floor'       => array( 'key' => 'floor',       'default_label' => 'Podlažie',       'prominent' => false ),
            'interior'   => array( 'key' => 'interior',   'default_label' => 'Plocha interiér','prominent' => false ),
            'exterior'   => array( 'key' => 'exterior',   'default_label' => 'Plocha exteriér','prominent' => false ),
            'cellar'     => array( 'key' => 'cellar',     'default_label' => 'Pivnica',        'prominent' => false ),
        );
    }

    public function init(){
        $this->name = 'Údaje bytu (mriežka)';
        $this->folder_name = 'dev_apt_byty';
        $this->settings_modal_toggles = array(
            'general' => array(
                'toggles' => array(
                    'main'    => 'Rozloženie',
                    'fields'  => 'Viditeľnosť a názvy polí',
                    'design'  => 'Štýl blokov',
                    'text'    => 'Štýl textu',
                ),
            ),
        );
    }

    public function get_fields(){
        $defaults = self::get_default_items();
        $fields = array(
            'layout' => array('label'=>'Rozloženie','type'=>'select','options'=>array('blocks'=>'Mriežka blokov','inline'=>'V jednom riadku'),'default'=>'blocks','tab_slug'=>'general','toggle_slug'=>'main'),
            'price_position' => array(
                'label'       => 'Umiestnenie ceny',
                'type'        => 'select',
                'options'     => array(
                    'left'  => 'Naľavo od ostatných',
                    'above' => 'Nad ostatnými',
                ),
                'default'     => 'left',
                'tab_slug'    => 'general',
                'toggle_slug' => 'main',
                'show_if'     => array( 'layout' => 'blocks' ),
            ),
            'block_bg' => array('label'=>'Pozadie blokov','type'=>'color-alpha','default'=>'#ffffff','tab_slug'=>'general','toggle_slug'=>'design'),
            'block_padding' => array('label'=>'Padding blokov','type'=>'text','default'=>'12px 16px','tab_slug'=>'general','toggle_slug'=>'design'),
            'border_radius' => array('label'=>'Zaoblenie','type'=>'range','default'=>'6','unit'=>'px','range_settings'=>array('min'=>0,'max'=>16,'step'=>2),'tab_slug'=>'general','toggle_slug'=>'design'),
            // Štýl textu – názvy polí
            'label_font_size' => array('label'=>'Názvy polí: veľkosť písma','type'=>'range','default'=>'14','unit'=>'px','range_settings'=>array('min'=>10,'max'=>24,'step'=>1),'tab_slug'=>'general','toggle_slug'=>'text'),
            'label_color'      => array('label'=>'Názvy polí: farba','type'=>'color-alpha','default'=>'','tab_slug'=>'general','toggle_slug'=>'text'),
            'label_font_weight'=> array('label'=>'Názvy polí: hrúbka','type'=>'select','options'=>array(''=>'Predvolená','400'=>'Normálna','600'=>'Polotučná','700'=>'Tučná'),'default'=>'','tab_slug'=>'general','toggle_slug'=>'text'),
            // Štýl textu – dáta
            'value_font_size'  => array('label'=>'Dáta: veľkosť písma','type'=>'range','default'=>'16','unit'=>'px','range_settings'=>array('min'=>10,'max'=>32,'step'=>1),'tab_slug'=>'general','toggle_slug'=>'text'),
            'value_color'      => array('label'=>'Dáta: farba','type'=>'color-alpha','default'=>'','tab_slug'=>'general','toggle_slug'=>'text'),
            'value_font_weight'=> array('label'=>'Dáta: hrúbka','type'=>'select','options'=>array(''=>'Predvolená','400'=>'Normálna','600'=>'Polotučná','700'=>'Tučná'),'default'=>'','tab_slug'=>'general','toggle_slug'=>'text'),
        );
        foreach ( $defaults as $key => $item ) {
            $fields[ 'show_' . $key ] = array(
                'label'       => 'Zobraziť: ' . $item['default_label'],
                'type'        => 'yes_no_button',
                'options'     => array( 'on' => 'Áno', 'off' => 'Nie' ),
                'default'     => 'on',
                'tab_slug'    => 'general',
                'toggle_slug' => 'fields',
            );
            $fields[ 'label_' . $key ] = array(
                'label'       => 'Názov poľa: ' . $item['default_label'],
                'type'        => 'text',
                'default'     => $item['default_label'],
                'tab_slug'    => 'general',
                'toggle_slug' => 'fields',
            );
        }
        return $fields;
    }

    private function resolve_pid(){
        return function_exists('dev_resolve_apartment_id_from_atts') ? dev_resolve_apartment_id_from_atts(array()) : 0;
    }

    private function get_item_value( $key, $pid ){
        if ( ! $pid ) return '—';
        switch ( $key ) {
            case 'price':
                return do_shortcode('[dev_apt_price id="'.$pid.'"]');
            case 'rooms':
                $t = wp_get_post_terms($pid,'apartment_type',array('number'=>1));
                return ($t && !is_wp_error($t)) ? esc_html($t[0]->name) : '—';
            case 'area_total':
                $v = get_post_meta($pid,'apt_area_total',true);
                return ($v !== '' && is_numeric($v)) ? number_format((float)$v,2,',',' ').' m²' : '—';
            case 'status':
                $t = wp_get_post_terms($pid,'apartment_status',array('number'=>1));
                return ($t && !is_wp_error($t)) ? esc_html($t[0]->name) : '—';
            case 'floor':
                return do_shortcode('[dev_apt_floor id="'.$pid.'"]');
            case 'interior':
                $v = get_post_meta($pid,'apt_area_interior',true);
                return ($v !== '' && is_numeric($v)) ? number_format((float)$v,2,',',' ').' m²' : '—';
            case 'exterior':
                $v = get_post_meta($pid,'apt_area_exterior',true);
                return ($v !== '' && is_numeric($v)) ? number_format((float)$v,2,',',' ').' m²' : '—';
            case 'cellar':
                return do_shortcode('[dev_apt_cellar id="'.$pid.'"]');
            default:
                return '—';
        }
    }

    public function render($attrs,$content=null,$render_slug){
        $layout = $this->props['layout'] ?? 'blocks';
        $block_bg = $this->props['block_bg'] ?? '#ffffff';
        $block_pad = $this->props['block_padding'] ?? '12px 16px';
        $radius = $this->props['border_radius'] ?? '6';
        $price_position = $this->props['price_position'] ?? 'left';

        $label_fs   = $this->props['label_font_size'] ?? '14';
        $label_clr  = $this->props['label_color'] ?? '';
        $label_fw   = $this->props['label_font_weight'] ?? '';
        $value_fs   = $this->props['value_font_size'] ?? '16';
        $value_clr  = $this->props['value_color'] ?? '';
        $value_fw   = $this->props['value_font_weight'] ?? '';

        $pid = $this->resolve_pid();
        if ( dev_apt_is_builder() ){
            return dev_apt_builder_placeholder( 'Údaje bytu (mriežka)', '▦' );
        }
        if ( ! $pid ) return '';

        $defaults = self::get_default_items();
        $items = array();
        foreach ( $defaults as $key => $item ) {
            $show = $this->props[ 'show_' . $key ] ?? 'on';
            if ( $show !== 'on' ) continue;
            $label = trim( (string) ( $this->props[ 'label_' . $key ] ?? $item['default_label'] ) );
            if ( $label === '' ) $label = $item['default_label'];
            $items[] = array(
                'key'       => $key,
                'label'     => $label,
                'value'     => $this->get_item_value( $key, $pid ),
                'prominent' => $item['prominent'],
            );
        }

        $label_style = 'font-size:'.esc_attr($label_fs).'px;';
        if ( $label_clr !== '' ) $label_style .= 'color:'.esc_attr($label_clr).';';
        if ( $label_fw !== '' ) $label_style .= 'font-weight:'.esc_attr($label_fw).';';
        $value_style = 'font-size:'.esc_attr($value_fs).'px;';
        if ( $value_clr !== '' ) $value_style .= 'color:'.esc_attr($value_clr).';';
        if ( $value_fw !== '' ) $value_style .= 'font-weight:'.esc_attr($value_fw).';';

        $block_style = 'padding:'.esc_attr($block_pad).';background:'.esc_attr($block_bg).';border-radius:'.esc_attr($radius).'px;';

        if ( $layout === 'inline' ){
            $out = '';
            foreach ( $items as $i ) {
                $out .= '<span class="dev-stat-item"><span class="dev-apt-stat-label" style="'.esc_attr($label_style).'">'.esc_html($i['label']).':</span> <span class="dev-apt-stat-value" style="'.esc_attr($value_style).'">'.$i['value'].'</span></span>';
                if ( $i !== end($items) ) $out .= ' &nbsp;|&nbsp; ';
            }
            return '<div class="dev-apt-single-stats dev-apt-stats-inline et_pb_module">'.$out.'</div>';
        }

        $prominent = array_filter( $items, function( $x ) { return ! empty( $x['prominent'] ); } );
        $rest      = array_filter( $items, function( $x ) { return empty( $x['prominent'] ); } );

        $out = '<div class="dev-apt-single-stats dev-apt-stats-blocks et_pb_module">';

        if ( $price_position === 'above' && ! empty( $prominent ) ) {
            foreach ( $prominent as $i ) {
                $out .= '<div class="dev-apt-stat-block dev-apt-stat-prominent dev-apt-stat-price-row" style="'.esc_attr($block_style).'width:100%;flex:1 1 100%;min-width:200px"><div class="dev-apt-stat-label" style="'.esc_attr($label_style).'margin-bottom:4px">'.esc_html($i['label']).'</div><div class="dev-apt-stat-value" style="'.esc_attr($value_style).'font-size:1.25em;font-weight:700">'.$i['value'].'</div></div>';
            }
            $out .= '<div class="dev-apt-stat-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;flex:1 1 100%;min-width:0;width:100%">';
            foreach ( $rest as $i ) {
                $out .= '<div class="dev-apt-stat-block" style="'.esc_attr($block_style).'min-width:120px"><div class="dev-apt-stat-label" style="'.esc_attr($label_style).'margin-bottom:4px">'.esc_html($i['label']).'</div><div class="dev-apt-stat-value" style="'.esc_attr($value_style).'">'.$i['value'].'</div></div>';
            }
            $out .= '</div>';
        } else {
            foreach ( $prominent as $i ) {
                $out .= '<div class="dev-apt-stat-block dev-apt-stat-prominent" style="'.esc_attr($block_style).'flex:1;min-width:200px"><div class="dev-apt-stat-label" style="'.esc_attr($label_style).'margin-bottom:4px">'.esc_html($i['label']).'</div><div class="dev-apt-stat-value" style="'.esc_attr($value_style).'font-size:1.25em;font-weight:700">'.$i['value'].'</div></div>';
            }
            $out .= '<div class="dev-apt-stat-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;flex:1;min-width:0">';
            foreach ( $rest as $i ) {
                $out .= '<div class="dev-apt-stat-block" style="'.esc_attr($block_style).'min-width:120px"><div class="dev-apt-stat-label" style="'.esc_attr($label_style).'margin-bottom:4px">'.esc_html($i['label']).'</div><div class="dev-apt-stat-value" style="'.esc_attr($value_style).'">'.$i['value'].'</div></div>';
            }
            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= function_exists('dev_apt_stats_blocks_style_once') ? dev_apt_stats_blocks_style_once() : '';
        return $out;
    }
}
new DEV_Single_Stats_Module();
