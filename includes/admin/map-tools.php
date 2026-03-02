<?php
if (!defined('ABSPATH')) exit;

// Simple diagnostic page (Tools -> Mapy) to scan mismatches and optionally repair term targets by custom_title
add_action('admin_menu', function(){
    add_submenu_page('edit.php?post_type=apartment', 'Mapy – Diagnostika', 'Mapy – Diagnostika', 'manage_options', 'dev-apt-map-tools', function(){
        if(!current_user_can('manage_options')) wp_die('forbidden');
        echo '<div class="wrap"><h1>Mapy – Diagnostika</h1>';
        if(isset($_POST['scan'])){
            check_admin_referer('devapt_map_scan');
            $terms = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false]);
            echo '<table class="widefat striped"><thead><tr><th>Term</th><th>Shapes</th><th>Assigned posts</th><th>Assigned terms</th><th>Null targets</th></tr></thead><tbody>';
            foreach($terms as $t){
                $raw=get_term_meta($t->term_id,'dev_map_data',true); $arr=$raw? json_decode($raw,true):[]; if(!is_array($arr)) $arr=[];
                $tot=count($arr); $p=0;$tm=0;$nul=0; foreach($arr as $s){ if(!empty($s['target_type']) && $s['target_type']==='post' && !empty($s['target_id'])) $p++; elseif(!empty($s['target_type']) && $s['target_type']==='term' && !empty($s['target_id'])) $tm++; else $nul++; }
                echo '<tr><td>'.esc_html($t->name).'</td><td>'.$tot.'</td><td>'.$p.'</td><td>'.$tm.'</td><td>'.$nul.'</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<form method="post">'; wp_nonce_field('devapt_map_scan'); echo '<p><button class="button button-primary" name="scan" value="1">Prejsť mapy</button></p></form>';
        echo '</div>';
    });
});
