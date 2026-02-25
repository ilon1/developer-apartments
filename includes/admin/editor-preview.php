<?php
if (!defined('ABSPATH')) exit;
add_action('project_structure_edit_form_fields', function($term){ if(!current_user_can('manage_options')) return; $tid=(int)$term->term_id; $raw=get_term_meta($tid,'dev_map_data',true); $img_id=get_term_meta($tid,'dev_floor_plan_id',true); $img_url=$img_id? wp_get_attachment_url($img_id):''; ?>
  <tr class="form-field">
    <th scope="row"><label><?php esc_html_e('Náhľad mapy','developer-apartments'); ?></label></th>
    <td>
      <div id="dev-apt-preview" data-json="<?php echo esc_attr(is_string($raw)?$raw:''); ?>" data-img="<?php echo esc_url($img_url); ?>" style="border:1px solid #e3e3e3; padding:8px; max-width:100%;"></div>
      <p class="description">Read‑only náhľad z uložených dát (<code>dev_map_data</code>) s podkladom.</p>
    </td>
  </tr>
<?php }, 20, 1);
add_action('admin_enqueue_scripts', function($hook){ if($hook!=='term.php') return; $tax= isset($_GET['taxonomy'])? sanitize_key($_GET['taxonomy']) : ''; if($tax!=='project_structure') return; wp_enqueue_script('dev-apt-term-preview', plugins_url('includes/admin/term-map-preview.js', dirname(__FILE__,2)), [], '1.0.0', true); });
