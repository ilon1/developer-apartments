<?php
if ( ! defined('ABSPATH') ) exit;
// Admin page is already registered; we only update renderer markup to add path import help
if ( ! function_exists('dev_render_map_editor_page') ){
    function dev_render_map_editor_page(){
        $term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
        echo '<div class="wrap dev-term-map-wrap">';
        echo '<h1>'.esc_html__('Editor máp (project_structure)','developer-apartments').'</h1>';

        if($term_id<=0){
            echo '<p>'.esc_html__('Vyberte kategóriu na úpravu:','developer-apartments').'</p>';
            echo '<table class="widefat striped" style="max-width:900px">';
            echo '<thead><tr><th>'.esc_html__('Názov','developer-apartments').'</th><th>'.esc_html__('Akcia','developer-apartments').'</th></tr></thead><tbody>';
            $terms = get_terms([ 'taxonomy'=>'project_structure', 'hide_empty'=>false ]);
            if( ! is_wp_error($terms) ){
                foreach($terms as $t){
                    $url = admin_url('admin.php?page=dev-map-editor&term_id='.$t->term_id);
                    echo '<tr><td><strong>'.esc_html($t->name).'</strong></td><td><a class="button" href="'.esc_url($url).'">'.esc_html__('Editovať','developer-apartments').'</a></td></tr>';
                }
            }
            echo '</tbody></table></div>';
            return;
        }

        $crumb = get_term_parents_list($term_id, 'project_structure', [ 'separator' => ' › ', 'inclusive'=>true ]);
        echo '<p><strong>'.esc_html__('Aktuálna štruktúra:','developer-apartments').'</strong> '.wp_kses_post($crumb ?: '—').'</p>';

        $image_id = get_term_meta($term_id,'dev_floor_plan_id',true);
        $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

        echo '<form method="post" class="dev-term-image-form" style="margin:8px 0 16px">';
        wp_nonce_field('dev_term_image','dev_term_image_nonce');
        echo '<input type="hidden" name="dev_term_id" value="'.esc_attr($term_id).'" />';
        echo '<label>'.esc_html__('ID obrázka:','developer-apartments').'</label> ';
        echo '<input type="number" name="dev_floor_plan_id" value="'.esc_attr($image_id).'" placeholder="attachment ID" style="width:160px"/> ';
        submit_button(__('Uložiť obrázok','developer-apartments'),'secondary','save_term_image',false);
        echo '</form>';

        if(!$image_url){
            echo '<div class="notice notice-warning"><p>'.esc_html__('Najprv uložte ID obrázka (pôdorys/pozadie).','developer-apartments').'</p></div>';
            echo '</div>';
            return;
        }

        $map_data  = get_term_meta($term_id,'dev_map_data',true);

        echo '<div class="dev-editor-grid" style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start">';
        echo '<div class="dev-canvas-col">';
        echo '<div class="dev-canvas-wrap" style="position:relative;display:inline-block;line-height:0;border:1px solid #e5e5e5">';
        echo '<img id="dev_floor_img" src="'.esc_url($image_url).'" style="max-width:100%;height:auto;display:block"/>';
        echo '<svg id="dev_svg" style="position:absolute;left:0;top:0;width:100%;height:100%" preserveAspectRatio="none"></svg>';
        echo '</div>';
        echo '<p class="description">'.esc_html__('TIP: klikmi pridávajte body. Potiahnutím koliesok posúvate vrcholy. Import SVG podporuje <polygon points> aj <path d>.','developer-apartments').'</p>';
        echo '</div>';

        echo '<div class="dev-side-col" style="border:1px solid #e5e5e5;padding:12px;background:#fff">';
        echo '<h2 style="margin-top:0">'.esc_html__('Vrstvy (Overlays)','developer-apartments').'</h2>';
        echo '<div style="margin-bottom:8px">';
        echo '<button class="button button-primary" id="dev_new_poly">'.esc_html__('Nový polygon','developer-apartments').'</button> ';
        echo '<button class="button" id="dev_finish_poly">'.esc_html__('Ukončiť kreslenie','developer-apartments').'</button> ';
        echo '<button class="button" id="dev_delete_poly">'.esc_html__('Vymazať vybraný','developer-apartments').'</button>';
        echo '</div>';
        echo '<div class="dev-field"><label>'.esc_html__('Vlastný nadpis (tooltip)','developer-apartments').'</label><input type="text" id="dev_title" class="widefat"/></div>';
        echo '<div class="dev-field"><label>'.esc_html__('Farba oblasti','developer-apartments').'</label><input type="color" id="dev_color" value="#e91e63"/></div>';
        echo '<div class="dev-field"><label>'.esc_html__('Priradiť k:','developer-apartments').'</label><select id="dev_target" class="widefat"></select></div>';
        echo '<p><button class="button" id="dev_reload_targets">'.esc_html__('Znova načítať možnosti','developer-apartments').'</button></p>';
        echo '<details><summary>'.esc_html__('Import SVG polygon','developer-apartments').'</summary>';
        echo '<textarea id="dev_svg_import" class="widefat" rows="3" placeholder="<polygon points="x,y x,y ..." />"></textarea>';
        echo '<button class="button" id="dev_import_btn">'.esc_html__('Importovať do aktuálneho polygonu','developer-apartments').'</button>';
        echo '</details>';
        echo '<details style="margin-top:8px"><summary>'.esc_html__('Import SVG path','developer-apartments').'</summary>';
        echo '<textarea id="dev_path_import" class="widefat" rows="3" placeholder="<path d="M10 10 L50 50 Z" /> alebo iba d=..."></textarea>';
        echo '<button class="button" id="dev_import_path_btn">'.esc_html__('Konvertovať PATH → polygon','developer-apartments').'</button>';
        echo '</details>';
        echo '<hr/>';
        echo '<button class="button button-primary" id="dev_save_map" data-term="'.esc_attr($term_id).'">'.esc_html__('Uložiť mapu','developer-apartments').'</button> ';
        echo '<span id="dev_save_result"></span>';
        echo '<input type="hidden" id="dev_map_json" value="'.esc_attr($map_data).'" />';
        echo '</div></div></div>';
    }
}
