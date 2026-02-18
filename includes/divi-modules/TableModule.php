<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class DEV_Table_Module extends ET_Builder_Module{
  public $slug='dev_apartment_table'; public $vb_support='on';
  public function init(){ $this->name='Tabuľka Bytov'; }
  public function get_fields(){
    $terms=get_terms(array('taxonomy'=>'project_structure','hide_empty'=>false));
    $opts=array('all'=>'Všetky byty'); if(!is_wp_error($terms)){ foreach($terms as $t){ $opts[$t->term_id]=$t->name; } }
    return array(
      'source_id'=>array('label'=>'Zdroj bytov','type'=>'select','options'=>$opts,'description'=>'Vyberte projekt, budovu alebo poschodie.','toggle_slug'=>'main_content'),
      // Columns toggles
      'show_col_ext'   => array('label'=>'Zobraziť stĺpec: Exteriér','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'on','tab_slug'=>'advanced','toggle_slug'=>'columns'),
      'show_col_cellar'=> array('label'=>'Zobraziť stĺpec: Pivnica','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'on','tab_slug'=>'advanced','toggle_slug'=>'columns'),
      'show_col_total' => array('label'=>'Zobraziť stĺpec: Celkom','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'on','tab_slug'=>'advanced','toggle_slug'=>'columns'),
      'show_col_price' => array('label'=>'Zobraziť stĺpec: Cena','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'on','tab_slug'=>'advanced','toggle_slug'=>'columns'),
      'show_col_status'=> array('label'=>'Zobraziť stĺpec: Status','type'=>'yes_no_button','options'=>array('on'=>'Áno','off'=>'Nie'),'default'=>'on','tab_slug'=>'advanced','toggle_slug'=>'columns'),
      // Labels
      'label_col_flat'   => array('label'=>'Názov stĺpca: Byt','type'=>'text','default'=>'Byt','tab_slug'=>'advanced','toggle_slug'=>'labels'),
      'label_col_int'    => array('label'=>'Názov stĺpca: Interiér','type'=>'text','default'=>'Interiér','tab_slug'=>'advanced','toggle_slug'=>'labels'),
      'label_col_ext'    => array('label'=>'Názov stĺpca: Exteriér','type'=>'text','default'=>'Exteriér','tab_slug'=>'advanced','toggle_slug'=>'labels'),
      'label_col_cellar' => array('label'=>'Názov stĺpca: Pivnica','type'=>'text','default'=>'Pivnica','tab_slug'=>'advanced','toggle_slug'=>'labels'),
      'label_col_total'  => array('label'=>'Názov stĺpca: Celkom','type'=>'text','default'=>'Celkom','tab_slug'=>'advanced','toggle_slug'=>'labels'),
      'label_col_price'  => array('label'=>'Názov stĺpca: Cena','type'=>'text','default'=>'Cena','tab_slug'=>'advanced','toggle_slug'=>'labels'),
      'label_col_status' => array('label'=>'Názov stĺpca: Status','type'=>'text','default'=>'Status','tab_slug'=>'advanced','toggle_slug'=>'labels'),
      // Colors
      'color_free'       => array('label'=>'Farba statusu: Voľný','type'=>'color-alpha','default'=>'#3aa655','tab_slug'=>'advanced','toggle_slug'=>'colors'),
      'color_reserved'   => array('label'=>'Farba statusu: Rezervovaný','type'=>'color-alpha','default'=>'#f0ad4e','tab_slug'=>'advanced','toggle_slug'=>'colors'),
      'color_sold'       => array('label'=>'Farba statusu: Predaný','type'=>'color-alpha','default'=>'#d9534f','tab_slug'=>'advanced','toggle_slug'=>'colors'),
    );
  }
  public function get_advanced_fields_config(){
    return array('toggles'=>array(
      'columns'=>array('title'=>'Zobrazenie stĺpcov'),
      'labels'=>array('title'=>'Texty hlavičiek'),
      'colors'=>array('title'=>'Farby statusov'),
    ));
  }
  private function dev_format_price($post_id){
    $pres=get_post_meta($post_id,'apt_price_presale',true);
    $disc=get_post_meta($post_id,'apt_price_discount',true);
    $list=get_post_meta($post_id,'apt_price_list',true);
    if($pres!=='') return '<strong>'.number_format((float)$pres,0,',',' ').' €</strong>';
    if($disc!==''){
      $right=$list!==''? number_format((float)$list,0,',',' ').' €' : '';
      return '<strong>'.number_format((float)$disc,0,',',' ').' €</strong> <span class="dev-price-old">'.$right.'</span>';
    }
    if($list!=='') return number_format((float)$list,0,',',' ').' €';
    return 'Na vyžiadanie';
  }
  public function render($attrs,$content=null,$render_slug){
    $src=isset($this->props['source_id'])? trim((string)$this->props['source_id']) : '';
    $args=array('post_type'=>'apartment','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC');
    if($src!=='' && $src!=='all'){ $args['tax_query']=array(array('taxonomy'=>'project_structure','field'=>'term_id','terms'=>intval($src),'include_children'=>true)); }
    $q=new WP_Query($args); if(!$q->have_posts()) return '<p>Žiadne byty nenájdené.</p>';

    $L_flat=$this->props['label_col_flat']; $L_int=$this->props['label_col_int']; $L_ext=$this->props['label_col_ext'];
    $L_cell=$this->props['label_col_cellar']; $L_tot=$this->props['label_col_total']; $L_pr=$this->props['label_col_price']; $L_st=$this->props['label_col_status'];
    $v_ext=($this->props['show_col_ext']==='on'); $v_cell=($this->props['show_col_cellar']==='on'); $v_tot=($this->props['show_col_total']==='on'); $v_price=($this->props['show_col_price']==='on'); $v_stat=($this->props['show_col_status']==='on');

    ob_start(); ?>
<div class="dev-apartment-table-wrapper">
<table class="dev-table">
  <thead>
    <tr>
      <th><?php echo esc_html($L_flat); ?></th>
      <th><?php echo esc_html($L_int); ?></th>
      <?php if($v_ext): ?><th><?php echo esc_html($L_ext); ?></th><?php endif; ?>
      <?php if($v_cell): ?><th><?php echo esc_html($L_cell); ?></th><?php endif; ?>
      <?php if($v_tot): ?><th><?php echo esc_html($L_tot); ?></th><?php endif; ?>
      <?php if($v_price): ?><th><?php echo esc_html($L_pr); ?></th><?php endif; ?>
      <?php if($v_stat): ?><th><?php echo esc_html($L_st); ?></th><?php endif; ?>
    </tr>
  </thead>
  <tbody>
  <?php while($q->have_posts()): $q->the_post();
    $i_area=get_post_meta(get_the_ID(),'apt_area_interior',true);
    $e_area=get_post_meta(get_the_ID(),'apt_area_exterior',true);
    $cellar=get_post_meta(get_the_ID(),'apt_cellar_area',true);
    $cellar_yes=get_post_meta(get_the_ID(),'apt_cellar_yes',true);
    $t_area=get_post_meta(get_the_ID(),'apt_area_total',true);
    $price_html=$this->dev_format_price(get_the_ID());
    $status_terms=get_the_terms(get_the_ID(),'apartment_status');
    $status_name=($status_terms && !is_wp_error($status_terms))? $status_terms[0]->name : '';
    $badge_class='free'; if($status_name==='Rezervovaný') $badge_class='res'; if($status_name==='Predaný') $badge_class='sold';
    $status_html = $status_name ? '<span class="dev-badge '.$badge_class.'">'.esc_html($status_name).'</span>' : '';
    $cellar_html=($cellar_yes==='1')? __('Áno','developer-apartments') : ($cellar!==''? esc_html($cellar).' m²' : '—');
  ?>
    <tr>
      <td data-label="<?php echo esc_attr($L_flat); ?>"><a class="dev-rowlink" href="<?php echo esc_url(get_permalink()); ?>"><strong><?php the_title(); ?></strong></a></td>
      <td data-label="<?php echo esc_attr($L_int); ?>"><?php echo $i_area!==''? esc_html($i_area).' m²':'—'; ?></td>
      <?php if($v_ext): ?><td data-label="<?php echo esc_attr($L_ext); ?>"><?php echo $e_area!==''? esc_html($e_area).' m²':'—'; ?></td><?php endif; ?>
      <?php if($v_cell): ?><td data-label="<?php echo esc_attr($L_cell); ?>"><?php echo $cellar_html; ?></td><?php endif; ?>
      <?php if($v_tot): ?><td data-label="<?php echo esc_attr($L_tot); ?>"><?php echo $t_area!==''? esc_html($t_area).' m²':'—'; ?></td><?php endif; ?>
      <?php if($v_price): ?><td data-label="<?php echo esc_attr($L_pr); ?>"><?php echo $price_html; ?></td><?php endif; ?>
      <?php if($v_stat): ?><td data-label="<?php echo esc_attr($L_st); ?>"><?php echo $status_html; ?></td><?php endif; ?>
    </tr>
  <?php endwhile; wp_reset_postdata(); ?>
  </tbody>
</table>
</div>
<?php return ob_get_clean(); }
}
new DEV_Table_Module();
