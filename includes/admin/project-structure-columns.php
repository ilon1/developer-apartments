<?php
if (!defined('ABSPATH')) exit;

// Add columns to project_structure term list
add_filter('manage_edit-project_structure_columns', function($cols){
    $cols['dev_coming_soon'] = __('Stav vrstvy','developer-apartments');
    $cols['dev_children'] = __('Poschodia / vetvy','developer-apartments');
    $cols['dev_apts']     = __('Byty v mapách','developer-apartments');
    return $cols;
});

add_filter('manage_project_structure_custom_column', function($out,$col,$term_id){
    if($col==='dev_coming_soon'){
        $coming = get_term_meta($term_id, 'dev_coming_soon', true);
        if($coming) return '<span style="color:#856404;">'.esc_html__('Neaktívna (Pripravujeme)','developer-apartments').'</span>';
        return '—';
    }
    if($col==='dev_children'){
        $children = get_terms(['taxonomy'=>'project_structure','hide_empty'=>false,'parent'=>$term_id]);
        if(is_wp_error($children) || empty($children)) return '—';
        $names = array_map(function($t){ return esc_html($t->name); }, $children);
        return implode(', ', $names);
    }
    if($col==='dev_apts'){
        $raw = get_term_meta($term_id, 'dev_map_data', true); if(!$raw) return '—';
        $arr = json_decode($raw, true); if(!is_array($arr)) return '—';
        $cnt = 0; foreach($arr as $s){ if(!empty($s['target_type']) && $s['target_type']==='post' && !empty($s['target_id'])) $cnt++; }
        return $cnt ? sprintf(__('%d priradených bytov','developer-apartments'), $cnt) : '—';
    }
    return $out;
},10,3);
