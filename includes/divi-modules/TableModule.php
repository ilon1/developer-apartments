<?php
class DEV_Table_Module extends ET_Builder_Module {
    public $slug       = 'dev_apartment_table';
    public $vb_support = 'on';
    public function init(){ $this->name = 'Tabuľka Bytov'; }
    public function get_fields(){
        $terms = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false]);
        $options = ['all'=>'Všetky byty'];
        if ( ! is_wp_error($terms) ) foreach($terms as $t) $options[$t->term_id] = $t->name;
        return [
            'source_id' => [ 'label'=>'Zdroj bytov','type'=>'select','options'=>$options,'description'=>'Vyberte projekt, budovu alebo poschodie.','toggle_slug'=>'main_content' ],
            'show_status'=> ['label'=>'Zobraziť status','type'=>'yes_no_button','options'=>['on'=>'Áno','off'=>'Nie'],'default'=>'on'],
        ];
    }
    public function render( $attrs, $content = null, $render_slug ){
        $source_id = $this->props['source_id'];
        $args = [ 'post_type'=>'apartment', 'posts_per_page'=>-1 ];
        if ( $source_id !== 'all' ){
            $args['tax_query'] = [[ 'taxonomy'=>'project_structure', 'field'=>'term_id', 'terms'=>$source_id, 'include_children'=>true ]];
        }
        $q = new WP_Query($args);
        if ( ! $q->have_posts() ) return '<p>Žiadne byty nenájdené.</p>';
        ob_start(); ?>
        <div class="dev-apartment-table-wrapper">
            <table class="dev-table" style="width:100%; border-collapse:collapse;">
                <thead><tr style="background:#f1f1f1;text-align:left;">
                    <th style="padding:10px;">Názov</th>
                    <th style="padding:10px;">Izby</th>
                    <th style="padding:10px;">Plocha</th>
                    <th style="padding:10px;">Cena</th>
                    <?php if($this->props['show_status']==='on'): ?><th style="padding:10px;">Status</th><?php endif; ?>
                    <th style="padding:10px;"></th>
                </tr></thead>
                <tbody>
                <?php while( $q->have_posts() ): $q->the_post();
                    $price = get_post_meta(get_the_ID(),'apartment_price',true);
                    $area  = get_post_meta(get_the_ID(),'apartment_area',true);
                    $rooms = get_post_meta(get_the_ID(),'apartment_rooms',true);
                    $status_terms = get_the_terms(get_the_ID(),'apartment_status');
                    $status_name  = $status_terms ? $status_terms[0]->name : 'Neznámy';
                    $status_color = '#666';
                    if($status_name==='Voľný') $status_color='green';
                    if($status_name==='Predaný') $status_color='red';
                    if($status_name==='Rezervovaný') $status_color='orange';
                ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px;"><strong><?php the_title(); ?></strong></td>
                        <td style="padding:10px;"><?php echo esc_html($rooms); ?></td>
                        <td style="padding:10px;"><?php echo esc_html($area); ?> m²</td>
                        <td style="padding:10px;"><?php echo $price ? number_format($price,0,',',' ') . ' €' : 'Na vyžiadanie'; ?></td>
                        <?php if($this->props['show_status']==='on'): ?><td style="padding:10px;"><span style="color:<?php echo $status_color; ?>;font-weight:bold;"><?php echo esc_html($status_name); ?></span></td><?php endif; ?>
                        <td style="padding:10px;"><a href="<?php the_permalink(); ?>" class="button">Detail</a></td>
                    </tr>
                <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
        </div>
        <?php return ob_get_clean();
    }
}
new DEV_Table_Module();
