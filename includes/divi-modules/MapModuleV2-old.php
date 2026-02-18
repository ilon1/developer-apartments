<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DEV_Map_Module_V2 extends ET_Builder_Module{
    public $slug = 'dev_apartment_map_v2';
    public $vb_support = 'on';

    public function init(){
        $this->name = 'Mapa Bytov (v2)';
        $this->settings_modal_toggles = array(
            'general' => array(
                'toggles' => array(
                    'source' => array('title'=>'Zdroj'),
                    'style'  => array('title'=>'Štýl'),
                    'tooltip'=> array('title'=>'Tooltip'),
                    'badge'  => array('title'=>'Počty voľných bytov'),
                    'behavior' => array('title'=>'Správanie'),
                )
            ),
            'advanced'   => array('toggles'=>array()),
            'custom_css' => array('toggles'=>array()),
        );
    }

    public function get_fields(){
        $terms = get_terms(array('taxonomy'=>'project_structure','hide_empty'=>false));
        $opts  = array('' => '— Vyberte —');
        if(!is_wp_error($terms)){
            foreach($terms as $t){ $opts[$t->term_id] = $t->name; }
        }
        return array(
            'source_mode'   => array('label'=>'Režim zdroja','type'=>'select','options'=>array('manual'=>'Manuálny výber','context'=>'Z kontextu stránky (Theme Builder)'),'default'=>'context','tab_slug'=>'general','toggle_slug'=>'source'),
            'source_id'     => array('label'=>'Zdroj (projekt/budova/podlažie)','type'=>'select','options'=>$opts,'description'=>'Použije sa pri Režime zdroja = Manuálny výber.','tab_slug'=>'general','toggle_slug'=>'source'),
            'height'        => array('label'=>'Výška mapy (px)','type'=>'text','default'=>'420','tab_slug'=>'general','toggle_slug'=>'style'),
            'show_legend'   => array('label'=>'Zobraziť legendu','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'on','tab_slug'=>'general','toggle_slug'=>'style'),
            'overlay_color_mode' => array('label'=>'Farba overlay','type'=>'select','options'=>array('shape'=>'Podľa shape','override'=>'Vynútiť farbu'),'default'=>'shape','tab_slug'=>'general','toggle_slug'=>'style'),
            'overlay_color' => array('label'=>'Vynútená farba overlay','type'=>'color-alpha','default'=>'#1e88e5','tab_slug'=>'general','toggle_slug'=>'style'),
            'tooltip_bg'    => array('label'=>'Tooltip: pozadie','type'=>'color-alpha','default'=>'#222','tab_slug'=>'general','toggle_slug'=>'tooltip'),
            'tooltip_color' => array('label'=>'Tooltip: farba písma','type'=>'color-alpha','default'=>'#fff','tab_slug'=>'general','toggle_slug'=>'tooltip'),
            'tooltip_padding'=>array('label'=>'Tooltip: padding (px)','type'=>'text','default'=>'8','tab_slug'=>'general','toggle_slug'=>'tooltip'),
            'tooltip_size'  => array('label'=>'Tooltip: veľkosť písma (px)','type'=>'text','default'=>'14','tab_slug'=>'general','toggle_slug'=>'tooltip'),
            'badge_bg'      => array('label'=>'Badge: pozadie','type'=>'color-alpha','default'=>'#ffffff','tab_slug'=>'general','toggle_slug'=>'badge'),
            'badge_color'   => array('label'=>'Badge: farba písma','type'=>'color-alpha','default'=>'#333','tab_slug'=>'general','toggle_slug'=>'badge'),
            'badge_size'    => array('label'=>'Badge: veľkosť písma (px)','type'=>'text','default'=>'12','tab_slug'=>'general','toggle_slug'=>'badge'),
            'overlay_click_action' => array('label'=>'Klik na overlay','type'=>'select','options'=>array('navigate'=>'Prejsť na kategóriu','emit_event'=>'Poslať event do stránky'),'default'=>'navigate','tab_slug'=>'general','toggle_slug'=>'behavior'),
        );
    }

    private function is_builder(){
        return function_exists('et_fb_is_builder') && et_fb_is_builder();
    }

    private function resolve_source_term(){
        $mode   = $this->props['source_mode'] ?? 'context';
        $manual = isset($this->props['source_id']) ? intval($this->props['source_id']) : 0;

        // HOTFIX: preferuj manuál ak je k dispozícii (aj keby mode neprišiel)
        if ($manual > 0) return $manual;
        if ($mode === 'manual') return 0; // manual bez výberu → 0

        $qo = get_queried_object();
        if ($qo && isset($qo->taxonomy) && $qo->taxonomy === 'project_structure') {
            return intval($qo->term_id);
        }
        if (is_singular('apartment')) {
            $terms = wp_get_post_terms(get_the_ID(), 'project_structure', array('number' => 1));
            if ($terms && ! is_wp_error($terms)) return intval($terms[0]->term_id);
        }
        return 0;
    }

    private function builder_preview($h, $src){
        $h = max(120, intval(preg_replace('/[^0-9]/','', (string)$h) ?: 420));
        ob_start(); ?>
        <div class="dev-map-preview"
             style="height:<?php echo esc_attr($h); ?>px;display:flex;align-items:center;justify-content:center;background:#f6f7f9;border:1px dashed #cdd3db;border-radius:8px;">
            <div style="text-align:center;color:#6b7480;line-height:1.5">
                <strong>Mapa Bytov (náhľad)</strong><br>
                Zdroj: <?php echo $src ? 'term ID '.$src : 'nevybraný'; ?><br>
                Reálna mapa sa načíta na publiku.
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public function render($attrs,$content=null,$render_slug){
        $term_id = $this->resolve_source_term();

        // HOTFIX: bezpečná normalizácia výšky (už nikdy 0px)
        $h_raw = (string) ($this->props['height'] ?? '420');
        $h     = preg_replace('/[^0-9]/', '', $h_raw);
        if ($h === '') $h = '420';

        if ($this->is_builder()){
            return $this->builder_preview($h, $term_id);
        }
        
        $img_id = $term_id ? get_term_meta($term_id,'dev_floor_plan_id',true) : 0;
        $img_url = $img_id ? wp_get_attachment_url($img_id) : '';

        $json = $term_id ? get_term_meta($term_id, 'dev_map_data', true) : '';
        $shapes = array();
        if ($json) {
            $tmp = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $shapes = $tmp;
        }

        $enriched = array();
        foreach ($shapes as $s) {
            $row = $s;
            if (!empty($s['target_type']) && $s['target_type']==='term' && !empty($s['target_id'])) {
                $tid = intval($s['target_id']);
                $row['link'] = get_term_link($tid, 'project_structure');
                $q = new WP_Query(array(
                    'post_type'=>'apartment','posts_per_page'=>1,'fields'=>'ids','no_found_rows'=>false,
                    'tax_query'=>array(
                        'relation'=>'AND',
                        array('taxonomy'=>'project_structure','field'=>'term_id','terms'=>$tid),
                        array('taxonomy'=>'apartment_status','field'=>'name','terms'=>array('Voľný')),
                    ),
                ));
                $row['free_count'] = intval($q->found_posts);
                wp_reset_postdata();
            }
            $enriched[] = $row;
        }

        $css_vars = sprintf(
            '--dev-tooltip-bg:%1$s;--dev-tooltip-color:%2$s;--dev-tooltip-pad:%3$dpx;--dev-tooltip-size:%4$dpx;--dev-badge-bg:%5$s;--dev-badge-color:%6$s;--dev-badge-size:%7$dpx;',
            esc_attr($this->props['tooltip_bg'] ?? '#222'),
            esc_attr($this->props['tooltip_color'] ?? '#fff'),
            intval($this->props['tooltip_padding'] ?? 8),
            intval($this->props['tooltip_size'] ?? 14),
            esc_attr($this->props['badge_bg'] ?? '#fff'),
            esc_attr($this->props['badge_color'] ?? '#333'),
            intval($this->props['badge_size'] ?? 12)
        );

        $legend   = ($this->props['show_legend']==='on') ? '1' : '0';
        $mode     = $this->props['overlay_color_mode'] ?? 'shape';
        $ovr_col  = $this->props['overlay_color'] ?? '#1e88e5';
        $action   = $this->props['overlay_click_action'] ?? 'navigate';

        $data = array(
            'termId'   => $term_id,
            'legend'   => $legend === '1',
            'shapes'   => $enriched,
            'colorMode'=> $mode,
            'color'    => $ovr_col,
            'action'   => $action
        );

        $uid = 'dev-map-'.uniqid();
        ob_start(); ?>
        <div id="<?php echo esc_attr($uid); ?>" class="dev-apt-map"
             style="height:<?php echo esc_attr(intval($h)); ?>px;<?php echo $css_vars; ?>"
             data-img="<?php echo esc_attr($img_url); ?>"
             data-payload='<?php echo wp_json_encode($data); ?>'>
        </div>
        <?php
        return ob_get_clean();
    }
}
new DEV_Map_Module_V2();
