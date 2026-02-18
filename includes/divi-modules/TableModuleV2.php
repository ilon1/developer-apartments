<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DEV_Table_Module_V2 extends ET_Builder_Module {

    public $slug = 'dev_apartment_table_v2';
    public $vb_support = 'on';

    public function init(){
        $this->name = 'Tabuľka Bytov (v2)';
        $this->settings_modal_toggles = array(
            'general'  => array(
                'toggles' => array(
                    'main_content' => array('title' => 'Hlavný obsah'),
                    'columns'      => array('title' => 'Zobrazenie stĺpcov'),
                    'labels'       => array('title' => 'Texty hlavičiek'),
                    'colors'       => array('title' => 'Farby statusov'),
                    'behavior'     => array('title' => 'Triedenie, filtre & lazy‑load'),
                ),
            ),
            'advanced'   => array('toggles' => array()),
            'custom_css' => array('toggles' => array()),
        );
    }

    public function get_fields(){
        $terms = get_terms(array(
            'taxonomy'=>'project_structure',
            'hide_empty'=>false
        ));
        
        $opts = array('all'=>'Všetky byty');
        if(!is_wp_error($terms)){
            foreach($terms as $t){
                $opts[$t->term_id] = $t->name;
            }
        }

        return array(

            // ========== MAIN SOURCE ==========
            'source_mode'   => array(
                'label'=>'Režim zdroja',
                'type'=>'select',
                'options'=>array(
                    'manual'=>'Manuálny výber',
                    'context'=>'Z kontextu stránky (Theme Builder)'
                ),
                'default'=>'context',
                'tab_slug'=>'general',
                'toggle_slug'=>'main_content'
            ),

            'source_id'     => array(
                'label'=>'Zdroj bytov',
                'type'=>'select',
                'options'=>$opts,
                'description'=>'Použije sa pri Režime zdroja = Manuálny výber.',
                'tab_slug'=>'general',
                'toggle_slug'=>'main_content'
            ),

            // ========== COLUMN VISIBILITY ==========
            'show_col_ext'    => array(
                'label'=>'Zobraziť stĺpec: Exteriér',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'columns'
            ),

            'show_col_cellar' => array(
                'label'=>'Zobraziť stĺpec: Pivnica',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'columns'
            ),

            'show_col_total'  => array(
                'label'=>'Zobraziť stĺpec: Celkom',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'columns'
            ),

            'show_col_price'  => array(
                'label'=>'Zobraziť stĺpec: Cena',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'columns'
            ),

            'show_col_status' => array(
                'label'=>'Zobraziť stĺpec: Status',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'columns'
            ),

            // ========== LABELS ==========
            'label_col_flat'   => array(
                'label'=>'Názov stĺpca: Byt',
                'type'=>'text',
                'default'=>'Byt',
                'tab_slug'=>'general',
                'toggle_slug'=>'labels'
            ),

            'label_col_int'    => array(
                'label'=>'Názov stĺpca: Interiér',
                'type'=>'text',
                'default'=>'Interiér',
                'tab_slug'=>'general',
                'toggle_slug'=>'labels'
            ),

            'label_col_ext'    => array(
                'label'=>'Názov stĺpca: Exteriér',
                'type'=>'text',
                'default'=>'Exteriér',
                'tab_slug'=>'general',
                'toggle_slug'=>'labels'
            ),

            'label_col_cellar' => array(
                'label'=>'Názov stĺpca: Pivnica',
                'type'=>'text',
                'default'=>'Pivnica',
                'tab_slug'=>'general',
                'toggle_slug'=>'labels'
            ),

            'label_col_total'  => array(
                'label'=>'Názov stĺpca: Celkom',
                'type'=>'text',
                'default'=>'Celkom',
                'tab_slug'=>'general',
                'toggle_slug'=>'labels'
            ),

            'label_col_price'  => array(
                'label'=>'Názov stĺpca: Cena',
                'type'=>'text',
                'default'=>'Cena',
                'tab_slug'=>'general',
                'toggle_slug'=>'labels'
            ),

            'label_col_status' => array(
                'label'=>'Názov stĺpca: Status',
                'type'=>'text',
                'default'=>'Status',
                'tab_slug'=>'general',
                'toggle_slug'=>'labels'
            ),

            // ========== COLORS ==========
            'color_free'      => array(
                'label'=>'Farba statusu: Voľný',
                'type'=>'color-alpha',
                'default'=>'#3aa655',
                'tab_slug'=>'general',
                'toggle_slug'=>'colors'
            ),

            'color_reserved'  => array(
                'label'=>'Farba statusu: Rezervovaný',
                'type'=>'color-alpha',
                'default'=>'#f0ad4e',
                'tab_slug'=>'general',
                'toggle_slug'=>'colors'
            ),

            'color_sold'      => array(
                'label'=>'Farba statusu: Predaný',
                'type'=>'color-alpha',
                'default'=>'#d9534f',
                'tab_slug'=>'general',
                'toggle_slug'=>'colors'
            ),

            // ========== BEHAVIOR ==========
            'enable_sort'       => array(
                'label'=>'Povoliť triedenie',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),

            'items_per_page'    => array(
                'label'=>'Položiek na stránku (0 = všetko)',
                'type'=>'text',
                'default'=>'0',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),

            'filters_enable'    => array(
                'label'=>'Zobraziť panel filtrov',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),

            'filter_status'     => array(
                'label'=>'Filter: Status',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),

            'filter_rooms'      => array(
                'label'=>'Filter: Izby',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'off',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),

            'filter_area'       => array(
                'label'=>'Filter: Plocha (min/max)',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'off',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),

            'filter_price'      => array(
                'label'=>'Filter: Cena (min/max)',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'off',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),

            'filter_search'     => array(
                'label'=>'Filter: Hľadať názov',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'on',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),

            'export_csv'        => array(
                'label'=>'Tlačidlo Export CSV',
                'type'=>'yes_no_button',
                'options'=>array('on'=>'Áno','off'=>'Nie'),
                'default'=>'off',
                'tab_slug'=>'general',
                'toggle_slug'=>'behavior'
            ),
        );
    }

    private function is_builder(){
        return function_exists('et_fb_is_builder') && et_fb_is_builder();
    }

    private function resolve_source_term(){
        $mode   = $this->props['source_mode'] ?? 'context';
        $manual = isset($this->props['source_id']) ? trim((string)$this->props['source_id']) : '';

        // Manual mode takes priority
        if($mode === 'manual') return $manual;

        // Context: Term archive
        $qo = get_queried_object();
        if($qo && isset($qo->taxonomy) && $qo->taxonomy === 'project_structure'){
            return (string) $qo->term_id;
        }

        // Context: Single apartment → find its structure term
        if(is_singular('apartment')){
            $terms = wp_get_post_terms(get_the_ID(),'project_structure',array('number'=>1));
            if($terms && !is_wp_error($terms)){
                return (string)$terms[0]->term_id;
            }
        }

        return $manual;
    }

    private function builder_preview(){
        $v_ext  = ($this->props['show_col_ext']     === 'on');
        $v_cell = ($this->props['show_col_cellar']  === 'on');
        $v_tot  = ($this->props['show_col_total']   === 'on');
        $v_pr   = ($this->props['show_col_price']   === 'on');
        $v_st   = ($this->props['show_col_status']  === 'on');

        $L_flat = $this->props['label_col_flat'];
        $L_int  = $this->props['label_col_int'];
        $L_ext  = $this->props['label_col_ext'];
        $L_cell = $this->props['label_col_cellar'];
        $L_tot  = $this->props['label_col_total'];
        $L_pr   = $this->props['label_col_price'];
        $L_st   = $this->props['label_col_status'];

        ob_start(); ?>
        <div class="dev-apartment-table-wrapper dev-builder-preview">
            <table class="dev-table">
                <thead><tr>
                    <th><?php echo esc_html($L_flat); ?></th>
                    <th><?php echo esc_html($L_int); ?></th>
                    <?php if($v_ext):  ?><th><?php echo esc_html($L_ext);  ?></th><?php endif; ?>
                    <?php if($v_cell): ?><th><?php echo esc_html($L_cell); ?></th><?php endif; ?>
                    <?php if($v_tot):  ?><th><?php echo esc_html($L_tot);  ?></th><?php endif; ?>
                    <?php if($v_pr):   ?><th><?php echo esc_html($L_pr);   ?></th><?php endif; ?>
                    <?php if($v_st):   ?><th><?php echo esc_html($L_st);   ?></th><?php endif; ?>
                </tr></thead>
                <tbody>
                    <?php for($i=1;$i<=3;$i++): ?>
                    <tr>
                        <td data-label="<?php echo esc_attr($L_flat); ?>"><strong>Byt A<?php echo $i; ?></strong></td>
					<tr>
                        <td data-label="<?php echo esc_attr($L_flat); ?>"><strong>Byt A<?php echo $i; ?></strong></td>
                        <td data-label="<?php echo esc_attr($L_int); ?>">5<?php echo $i; ?> m²</td>
                        <?php if($v_ext):  ?><td data-label="<?php echo esc_attr($L_ext); ?>">8 m²</td><?php endif; ?>
                        <?php if($v_cell): ?><td data-label="<?php echo esc_attr($L_cell); ?>">Áno</td><?php endif; ?>
                        <?php if($v_tot):  ?><td data-label="<?php echo esc_attr($L_tot); ?>">6<?php echo $i; ?> m²</td><?php endif; ?>
                        <?php if($v_pr):   ?><td data-label="<?php echo esc_attr($L_pr); ?>"><strong>199 000 €</strong></td><?php endif; ?>
                        <?php if($v_st):   ?><td data-label="<?php echo esc_attr($L_st); ?>"><span class="dev-badge free">Voľný</span></td><?php endif; ?>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <div style="opacity:.6;margin-top:8px;font-size:12px;">Builder náhľad (statický).</div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function dev_format_price($post_id){
        $pres = get_post_meta($post_id,'apt_price_presale',true);
        $disc = get_post_meta($post_id,'apt_price_discount',true);
        $list = get_post_meta($post_id,'apt_price_list',true);

        if($pres !== ''){
            return '<strong>'.number_format((float)$pres,0,',',' ').' €</strong>';
        }
        if($disc !== ''){
            $right = ($list !== '' ? number_format((float)$list,0,',',' ').' €' : '');
            return '<strong>'.number_format((float)$disc,0,',',' ').' €</strong>
                    <span class="dev-price-old">'.$right.'</span>';
        }
        if($list !== ''){
            return number_format((float)$list,0,',',' ').' €';
        }
        return 'Na vyžiadanie';
    }

    public function render($attrs,$content=null,$render_slug){

        if ( ! is_admin() ) {
            wp_enqueue_script(
                'dev-apt-table',
                DEV_APT_URL.'includes/divi-modules/assets/table.js',
                array('jquery'),
                DEV_APT_VERSION,
                true
            );
        }

        if ( $this->is_builder() ) {
            return $this->builder_preview();
        }

        $free  = $this->props['color_free']     ?? '#3aa655';
        $res   = $this->props['color_reserved'] ?? '#f0ad4e';
        $sold  = $this->props['color_sold']     ?? '#d9534f';

        $src = $this->resolve_source_term();

        $args = array(
            'post_type'     => 'apartment',
            'post_status'   => 'publish',
            'posts_per_page'=> -1,
            'orderby'       => 'title',
            'order'         => 'ASC',
        );

        if($src !== '' && $src !== 'all'){
            $args['tax_query'] = array(array(
                'taxonomy'=>'project_structure',
                'field'=>'term_id',
                'terms'=>intval($src),
                'include_children'=>true
            ));
        }

        $q = new WP_Query($args);

        if(!$q->have_posts()){
            return '<p>Žiadne byty nenájdené.</p>';
        }

        // Labels
        $L_flat = $this->props['label_col_flat']; 
        $L_int  = $this->props['label_col_int'];
        $L_ext  = $this->props['label_col_ext'];   
        $L_cell = $this->props['label_col_cellar'];
        $L_tot  = $this->props['label_col_total']; 
        $L_pr   = $this->props['label_col_price'];
        $L_st   = $this->props['label_col_status'];

        // Column visibility
        $v_ext  = ($this->props['show_col_ext']==='on');
        $v_cell = ($this->props['show_col_cellar']==='on');
        $v_tot  = ($this->props['show_col_total']==='on');
        $v_pr   = ($this->props['show_col_price']==='on');
        $v_st   = ($this->props['show_col_status']==='on');

        // Behavior parameters
        $enable_sort    = ($this->props['enable_sort']==='on');
        $per_page       = intval($this->props['items_per_page'] ?? 0);
        $enable_filters = ($this->props['filters_enable']==='on');
        $F_status       = ($this->props['filter_status']==='on');
        $F_rooms        = ($this->props['filter_rooms']==='on');
        $F_area         = ($this->props['filter_area']==='on');
        $F_price        = ($this->props['filter_price']==='on');
        $F_search       = ($this->props['filter_search']==='on');
        $btn_csv        = ($this->props['export_csv']==='on');

        $uid = 'dev-table-'.uniqid();

        ob_start(); ?>
        
        <style>
            #<?php echo esc_js($uid); ?> .dev-badge.free{background:<?php echo esc_html($free); ?>}
            #<?php echo esc_js($uid); ?> .dev-badge.res{background:<?php echo esc_html($res); ?>}
            #<?php echo esc_js($uid); ?> .dev-badge.sold{background:<?php echo esc_html($sold); ?>}
            #<?php echo esc_js($uid); ?> thead th[data-sortable="1"]{
                position:relative; padding-right:18px
            }
            #<?php echo esc_js($uid); ?> thead th .sort-ico{
                position:absolute;right:6px;top:50%;
                transform:translateY(-50%);
                opacity:.45;font-size:11px
            }
            #<?php echo esc_js($uid); ?> thead th[data-dir="asc"]  .sort-ico:after{content:"↑";}
            #<?php echo esc_js($uid); ?> thead th[data-dir="desc"] .sort-ico:after{content:"↓";}
        </style>

        <div id="<?php echo esc_attr($uid); ?>"
            class="dev-apartment-table-wrapper"
            data-sort="<?php echo $enable_sort?'1':'0'; ?>"
            data-pagesize="<?php echo $per_page; ?>"
            data-filters="<?php echo $enable_filters?'1':'0'; ?>"
            data-f-status="<?php echo $F_status?'1':'0'; ?>"
            data-f-rooms="<?php echo $F_rooms?'1':'0'; ?>"
            data-f-area="<?php echo $F_area?'1':'0'; ?>"
            data-f-price="<?php echo $F_price?'1':'0'; ?>"
            data-f-search="<?php echo $F_search?'1':'0'; ?>"
            data-export-csv="<?php echo $btn_csv?'1':'0'; ?>"
        >

        <?php if($enable_filters): ?>
            <div class="dev-filters" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">

                <?php if($F_status): ?>
                    <select class="f-status">
                        <option value="">Status: všetko</option>
                        <option value="Voľný">Voľný</option>
                        <option value="Rezervovaný">Rezervovaný</option>
                        <option value="Predaný">Predaný</option>
                    </select>
                <?php endif; ?>

                <?php if($F_rooms): ?>
                    <select class="f-rooms">
                        <option value="">Izby: všetko</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5+</option>
                    </select>
                <?php endif; ?>

                <?php if($F_area): ?>
                    <input type="number" class="f-amin" placeholder="Plocha min" style="width:110px">
                    <input type="number" class="f-amax" placeholder="Plocha max" style="width:110px">
                <?php endif; ?>

                <?php if($F_price): ?>
                    <input type="number" class="f-pmin" placeholder="Cena min" style="width:110px">
                    <input type="number" class="f-pmax" placeholder="Cena max" style="width:110px">
                <?php endif; ?>

                <?php if($F_search): ?>
                    <input type="search" class="f-q" placeholder="Hľadať byt..." style="width:160px">
                <?php endif; ?>

                <?php if($btn_csv): ?>
                    <button type="button" class="f-csv">Export CSV</button>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <table class="dev-table">
            <thead>
                <tr>
                    <th data-key="flat"  <?php if($enable_sort) echo 'data-sortable="1"'; ?>>
                        <?php echo esc_html($L_flat); ?><span class="sort-ico"></span>
                    </th>

                    <th data-key="int"   <?php if($enable_sort) echo 'data-sortable="1"'; ?>>
                        <?php echo esc_html($L_int); ?><span class="sort-ico"></span>
                    </th>

                    <?php if($v_ext): ?>
                        <th data-key="ext" <?php if($enable_sort) echo 'data-sortable="1"'; ?>>
                            <?php echo esc_html($L_ext); ?><span class="sort-ico"></span>
                        </th>
                    <?php endif; ?>

                    <?php if($v_cell): ?>
                        <th data-key="cell" <?php if($enable_sort) echo 'data-sortable="1"'; ?>>
                            <?php echo esc_html($L_cell); ?><span class="sort-ico"></span>
                        </th>
                    <?php endif; ?>

                    <?php if($v_tot): ?>
                        <th data-key="tot" <?php if($enable_sort) echo 'data-sortable="1"'; ?>>
                            <?php echo esc_html($L_tot); ?><span class="sort-ico"></span>
                        </th>
                    <?php endif; ?>

                    <?php if($v_pr): ?>
                        <th data-key="price" <?php if($enable_sort) echo 'data-sortable="1"'; ?>>
                            <?php echo esc_html($L_pr); ?><span class="sort-ico"></span>
                        </th>
                    <?php endif; ?>

                    <?php if($v_st): ?>
                        <th data-key="status"><?php echo esc_html($L_st); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>

            <tbody>
            <?php while($q->have_posts()): $q->the_post();

                // ===== FETCH & SANITIZE ALL META VALUES =====
                $i_area_raw = get_post_meta(get_the_ID(),'apt_area_interior',true);
                $e_area_raw = get_post_meta(get_the_ID(),'apt_area_exterior',true);
                $cellar_raw = get_post_meta(get_the_ID(),'apt_cellar_area',true);
                $tot_area_raw = get_post_meta(get_the_ID(),'apt_area_total',true);

                // Safe numeric values
                $i_area = floatval($i_area_raw);
                $e_area = floatval($e_area_raw);
                $cellar = floatval($cellar_raw);
                $t_area = floatval($tot_area_raw);

                $cellar_yes = get_post_meta(get_the_ID(),'apt_cellar_yes',true);

                $price_html = $this->dev_format_price(get_the_ID());

                $status_terms = get_the_terms(get_the_ID(),'apartment_status');
                $status_name  = ($status_terms && !is_wp_error($status_terms)) ? $status_terms[0]->name : '';

                $badge_class='free';
                if($status_name==='Rezervovaný') $badge_class='res';
                if($status_name==='Predaný')     $badge_class='sold';

                $status_html = $status_name
                    ? '<span class="dev-badge '.$badge_class.'">'.esc_html($status_name).'</span>'
                    : '';

                $cellar_html = ($cellar_yes==='1')
                    ? __('Áno','developer-apartments')
                    : ($cellar ? esc_html($cellar).' m²' : '—');

                $permalink = get_permalink();

                $terms_ids = wp_get_post_terms(
                    get_the_ID(),
                    'project_structure',
                    array('fields'=>'ids')
                );

                $rooms = get_post_meta(get_the_ID(),'apartment_rooms',true);
            ?>
                <tr data-href="<?php echo esc_url($permalink); ?>"
                    data-flat="<?php echo esc_attr(get_the_title()); ?>"
                    data-int="<?php echo esc_attr($i_area); ?>"
                    <?php if($v_ext): ?>
                        data-ext="<?php echo esc_attr($e_area); ?>"
                    <?php endif; ?>
                    <?php if($v_cell): ?>
                        data-cell="<?php echo esc_attr($cellar); ?>"
                    <?php endif; ?>

                    <?php if($v_tot): ?>
                        data-tot="<?php echo esc_attr($t_area); ?>"
                    <?php endif; ?>

                    <?php if($v_pr): ?>
                        data-price="<?php echo esc_attr(preg_replace('/\D+/','', strip_tags($price_html)) ?: 0); ?>"
                    <?php endif; ?>

                    data-status="<?php echo esc_attr($status_name); ?>"
                    data-rooms="<?php echo esc_attr($rooms ?: ''); ?>"
                    data-terms="<?php echo esc_attr(implode(',', array_map('intval',$terms_ids ?: array()))); ?>"
                >

                    <td data-label="<?php echo esc_attr($L_flat); ?>">
                        <strong><?php the_title(); ?></strong>
                    </td>

                    <td data-label="<?php echo esc_attr($L_int); ?>">
                        <?php echo $i_area ? esc_html($i_area.' m²') : '—'; ?>
                    </td>

                    <?php if($v_ext): ?>
                        <td data-label="<?php echo esc_attr($L_ext); ?>">
                            <?php echo $e_area ? esc_html($e_area.' m²') : '—'; ?>
                        </td>
                    <?php endif; ?>

                    <?php if($v_cell): ?>
                        <td data-label="<?php echo esc_attr($L_cell); ?>">
                            <?php echo $cellar_yes==='1' ? 'Áno' : ($cellar ? esc_html($cellar.' m²') : '—'); ?>
                        </td>
                    <?php endif; ?>

                    <?php if($v_tot): ?>
                        <td data-label="<?php echo esc_attr($L_tot); ?>">
                            <?php echo $t_area ? esc_html($t_area.' m²') : '—'; ?>
                        </td>
                    <?php endif; ?>

                    <?php if($v_pr): ?>
                        <td data-label="<?php echo esc_attr($L_pr); ?>">
                            <?php echo $price_html; ?>
                        </td>
                    <?php endif; ?>

                    <?php if($v_st): ?>
                        <td data-label="<?php echo esc_attr($L_st); ?>">
                            <?php echo $status_html; ?>
                        </td>
                    <?php endif; ?>

                </tr>
            <?php endwhile; wp_reset_postdata(); ?>
            </tbody>
        </table>

        <div class="dev-pagination" style="margin-top:10px;display:none;gap:6px"></div>

        </div>
        <?php
        return ob_get_clean();
    }
}

new DEV_Table_Module_V2();	