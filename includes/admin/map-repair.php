<?php
if (!defined('ABSPATH')) exit;

// Auto-repair tool: match building polygons to its child floors by name
add_action('admin_post_devapt_map_repair', function(){
    if(!current_user_can('manage_options')) wp_die('forbidden');
    check_admin_referer('devapt_map_repair');
    $term_id = isset($_POST['term_id'])? intval($_POST['term_id']) : 0; // building term id
    $apply   = !empty($_POST['apply']);
    if(!$term_id) wp_die('missing term_id');

    $children = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'parent'=>$term_id]);
    if(is_wp_error($children)) $children=[];
    $by_name = [];
    foreach($children as $c){ $by_name[mb_strtolower($c->name,'UTF-8')] = $c; }

    $raw = get_term_meta($term_id, 'dev_map_data', true); $arr = $raw? json_decode($raw,true):[]; if(!is_array($arr)) $arr=[];
    $fixed=0; $report=[];
    foreach($arr as &$s){
        // Neprepisovať polygóny priradené na byt (target_type=post)
        if(!empty($s['target_type']) && $s['target_type']==='post' && !empty($s['target_id'])){
            $report[] = ['from'=> intval($s['target_id']), 'to'=>'—', 'title'=> isset($s['custom_title'])? trim($s['custom_title']) : '', 'skip'=>true];
            continue;
        }
        $ok = (!empty($s['target_type']) && $s['target_type']==='term' && !empty($s['target_id']));
        if($ok){
            // check that target is a child of this building
            $t = get_term(intval($s['target_id']), 'project_structure');
            if($t && !is_wp_error($t) && intval($t->parent) === $term_id){ $report[] = ['from'=> intval($s['target_id']), 'to'=>intval($s['target_id']), 'title'=> isset($s['custom_title'])? trim($s['custom_title']) : '', 'skip'=>true]; continue; } // already good child
        }
        // try match by custom_title
        $title = isset($s['custom_title'])? trim($s['custom_title']) : '';
        $match = $title ? ( $by_name[mb_strtolower($title,'UTF-8')] ?? null ) : null;
        if(!$match){
            // try contains match: e.g. custom_title "1. poschodie - východ" contains "1. poschodie"
            foreach($children as $c){ if(stripos($title, $c->name) !== false){ $match=$c; break; } }
        }
        if($match){
            $report[] = ['from'=> isset($s['target_id'])? intval($s['target_id']) : 0, 'to'=>$match->term_id, 'title'=>$title, 'skip'=>false];
            if($apply){ $s['target_type']='term'; $s['target_id']=$match->term_id; $fixed++; }
        } else {
            $report[] = ['from'=> isset($s['target_id'])? intval($s['target_id']) : 0, 'to'=>0, 'title'=>$title, 'skip'=>false];
        }
    }
    unset($s); // break reference after foreach by reference
    if($apply){ update_term_meta($term_id, 'dev_map_data', wp_slash(json_encode($arr))); }

    echo '<div class="wrap"><h1>Auto-repair: '.esc_html(get_term_field('name',$term_id,'project_structure','raw')).'</h1>';
    echo '<p>Počet polygónov: '.count($arr).', opravených: '.$fixed.'</p>';
    echo '<table class="widefat striped"><thead><tr><th>Pôvodné target_id</th><th>Nové target_id</th><th>custom_title</th><th>Poznámka</th></tr></thead><tbody>';
    foreach($report as $r){
        $note = !empty($r['skip']) ? (isset($r['from']) && $r['from'] && $r['to']==='—' ? 'Byt – bez zmeny' : 'OK – bez zmeny') : '';
        $toCell = isset($r['to']) && $r['to']==='—' ? '—' : (int)(isset($r['to'])? $r['to'] : 0);
        echo '<tr><td>'.esc_html(is_numeric($r['from'])? $r['from'] : $r['from']).'</td><td>'.esc_html($toCell).'</td><td>'.esc_html($r['title']).'</td><td>'.esc_html($note).'</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p><a class="button" href="'.esc_url(admin_url('term.php?taxonomy=project_structure&tag_ID='.$term_id)).'">Späť na termín</a></p>';    
    echo '</div>';
    exit;
});

// Small UI under term edit to run repair – bez vlastného <form> (vnorené formuláre by pokazili hlavné tlačidlo Aktualizovať)
add_action('project_structure_edit_form', function($term){ if(!current_user_can('manage_options')) return;
  $action_url = esc_url(admin_url('admin-post.php'));
  $nonce = wp_create_nonce('devapt_map_repair');
  $term_id = (int) $term->term_id;
?>
    <h2>Mapy – auto-repair (poschodia tejto budovy)</h2>
    <p class="description">Skontroluje mapu tohto termínu (budovy) a polygónom s neplatným alebo chýbajúcim priradením <strong>navrhne priradenie na poschodie</strong> podľa <code>custom_title</code> (zhoda s názvom child termu). Polygóny priradené na byt sa nemenia. Odporúča sa najprv spustiť <strong>Náhľad</strong>.</p>
    <p>
        <button type="button" class="button" id="devapt_repair_dry">Náhľad</button>
        <button type="button" class="button button-primary" id="devapt_repair_apply">Upraviť priradenia</button>
    </p>
    <script>
    (function(){
        var actionUrl = <?php echo json_encode($action_url); ?>;
        var nonce = <?php echo json_encode($nonce); ?>;
        var termId = <?php echo (int) $term_id; ?>;
        function submitRepair(apply) {
            var f = document.createElement('form');
            f.method = 'post';
            f.action = actionUrl;
            function inp(name, val) { var i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = val; f.appendChild(i); }
            inp('action', 'devapt_map_repair');
            inp('_wpnonce', nonce);
            inp('term_id', String(termId));
            if (apply) inp('apply', '1'); else inp('dry', '1');
            document.body.appendChild(f);
            f.submit();
        }
        document.getElementById('devapt_repair_dry').onclick = function() { submitRepair(false); };
        document.getElementById('devapt_repair_apply').onclick = function() { submitRepair(true); };
    })();
    </script>
<?php
});
