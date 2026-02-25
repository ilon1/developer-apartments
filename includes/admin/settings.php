<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Developer Apartments – Settings & Import/Export (FULL)
 * - Submenu under CPT 'apartment' (Byty)
 * - CSV import: autodetect ; , \t
 * - XLSX import: inlineStr + sharedStrings
 * - Export filters: status / project_structure / rooms range
 * - Import logging (TXT + CSV)
 * - Cache bump + configurable TTL (including 0 = disabled)
 * - Pricing & Status round‑trip export/import
 */

// =========== MENU ==========
add_action('admin_menu', function(){
  add_submenu_page(
    'edit.php?post_type=apartment',
    __('Developer Apartments','developer-apartments'),
    __('Nastavenia','developer-apartments'),
    'manage_options',
    'dev-apt-settings',
    'dev_apt_render_settings_page',
    30
  );
});

// =========== SETTINGS BASE ==========
add_action('admin_init', function(){ register_setting('dev_apt_options','dev_apt_options','dev_apt_sanitize_options'); });
function dev_apt_default_options(){
  return [
    'csv_delimiter'              => ';',
    'free_status_slug'           => 'volny',
    'uninstall_delete_posts'     => 0,
    'uninstall_delete_terms'     => 0,
    'uninstall_delete_term_meta' => 0,
    'uninstall_delete_options'   => 0,
    'cache_bump'                 => 1,
    'cache_ttl'                  => 3600,
  ];
}
function dev_apt_get_options(){ return wp_parse_args( get_option('dev_apt_options',[]), dev_apt_default_options() ); }

function dev_apt_available_delimiters(){
  $list = [
    ';'  => __('Bodkočiarka ; (default)','developer-apartments'),
    ','  => __('Čiarka ,','developer-apartments'),
    '\\t' => __('Tabulátor \\t','developer-apartments'),
  ];
  $list = apply_filters('dev_apt_csv_delimiters', $list);
  $clean = [];
  foreach($list as $k=>$label){ $ks=(string)$k; if($ks==='') continue; $clean[$ks]=$label; }
  if (!isset($clean[';'])){ $clean = array_merge([';'=>__('Bodkočiarka ; (default)','developer-apartments')], $clean); }
  return $clean;
}

function dev_apt_sanitize_options($in){
  $o = dev_apt_get_options();
  $allowed = array_keys( dev_apt_available_delimiters() );
  $new_delim = isset($in['csv_delimiter']) ? (string)$in['csv_delimiter'] : ';';
  if ( ! in_array($new_delim, $allowed, true) ) { $new_delim = ';'; }
  $o['csv_delimiter']    = $new_delim;
  $o['free_status_slug'] = sanitize_title($in['free_status_slug'] ?? 'volny');
  // TTL accepts 0 as disabled + 10m/30m/1h/6h/12h
  $ttl = isset($in['cache_ttl']) ? intval($in['cache_ttl']) : 3600;
  $allowed_ttl = array(0,600,1800,3600,21600,43200);
  if ( !in_array($ttl,$allowed_ttl,true) ) $ttl = 3600;
  $o['cache_ttl'] = $ttl;
  foreach(['uninstall_delete_posts','uninstall_delete_terms','uninstall_delete_term_meta','uninstall_delete_options'] as $k){ $o[$k] = !empty($in[$k]) ? 1 : 0; }
  return $o;
}

// =========== HELPERS (EXPORT DATA) ==========
function dev_apt_build_project_path($tid){
  $parts = [];
  $anc = array_reverse(get_ancestors($tid,'project_structure'));
  foreach($anc as $a){ $t=get_term($a,'project_structure'); if($t && !is_wp_error($t)) $parts[]=$t->name; }
  $self = get_term($tid,'project_structure'); if($self && !is_wp_error($self)) $parts[]=$self->name;
  return implode(' / ',$parts);
}
function dev_apt_collect_rows($filters = array()){
  $args = array('post_type'=>'apartment','post_status'=>'any','posts_per_page'=>-1);
  $taxq = array();
  if(!empty($filters['status_slug'])){
    $taxq[] = array('taxonomy'=>'apartment_status','field'=>'slug','terms'=>array(sanitize_title($filters['status_slug'])));
  }
  if(!empty($filters['project_structure'])){
    $taxq[] = array('taxonomy'=>'project_structure','field'=>'term_id','terms'=>array(intval($filters['project_structure'])),'include_children'=>true);
  }
  if(!empty($taxq)) $args['tax_query'] = $taxq;

  $metaq = array();
  if(isset($filters['rooms_min']) && $filters['rooms_min'] !== ''){
    $metaq[] = array('key'=>'apartment_rooms','value'=>intval($filters['rooms_min']),'type'=>'NUMERIC','compare'=>'>=');
  }
  if(isset($filters['rooms_max']) && $filters['rooms_max'] !== ''){
    $metaq[] = array('key'=>'apartment_rooms','value'=>intval($filters['rooms_max']),'type'=>'NUMERIC','compare'=>'<=' );
  }
  if(!empty($metaq)) $args['meta_query'] = $metaq;

  $rows=[]; $q=new WP_Query($args);
  while($q->have_posts()){ $q->the_post(); $pid=get_the_ID();
    $st=wp_get_post_terms($pid,'apartment_status',['number'=>1]);
    $st_slug=($st&&!is_wp_error($st))?$st[0]->slug:''; $st_name=($st&&!is_wp_error($st))?$st[0]->name:'';
    $ps=wp_get_post_terms($pid,'project_structure',['number'=>1]);
    $ps_slug=($ps&&!is_wp_error($ps))?$ps[0]->slug:''; $ps_id=($ps&&!is_wp_error($ps))?(int)$ps[0]->term_id:0; $ps_path=$ps_id?dev_apt_build_project_path($ps_id):'';
    $img_id=get_post_thumbnail_id($pid); $img_url=$img_id?wp_get_attachment_url($img_id):'';
    $rows[]=[
      'apartment_code'=>get_post_meta($pid,'apartment_code',true),
      'ID'=>$pid,
      'slug'=>get_post_field('post_name',$pid),
      'title'=>get_the_title($pid),
      'status_slug'=>$st_slug,
      'status_name'=>$st_name,
      'project_structure_slug'=>$ps_slug,
      'project_structure_path'=>$ps_path,
      'rooms'=>get_post_meta($pid,'apartment_rooms',true),
      'area_interior'=>get_post_meta($pid,'apt_area_interior',true),
      'area_exterior'=>get_post_meta($pid,'apt_area_exterior',true),
      'area_total'=>get_post_meta($pid,'apt_area_total',true),
      'cellar_area'=>get_post_meta($pid,'apt_cellar_area',true),
      'cellar_yes'=>get_post_meta($pid,'apt_cellar_yes',true),
      'price_list'=>get_post_meta($pid,'apt_price_list',true),
      'price_discount'=>get_post_meta($pid,'apt_price_discount',true),
      'price_presale'=>get_post_meta($pid,'apt_price_presale',true),
      'permalink'=>get_permalink($pid),
      'featured_image_id'=>$img_id,
      'featured_image_url'=>$img_url,
    ];
  }
  wp_reset_postdata();
  return $rows;
}
function dev_apt_headers(){
  return ['apartment_code','ID','slug','title','status_slug','status_name','project_structure_slug','project_structure_path','rooms','area_interior','area_exterior','area_total','cellar_area','cellar_yes','price_list','price_discount','price_presale','permalink','featured_image_id','featured_image_url'];
}
function dev_apt_headers_pricing(){
  return ['apartment_code','ID','slug','title','status_slug','price_list','price_discount','price_presale'];
}

// =========== EXPORT HELPERS ==========
function dev_apt_output_csv($headers,$rows,$delimiter,$filename='apartmany-export.csv'){
  nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="'.basename($filename).'"');
  echo "\xEF\xBB\xBF"; // BOM
  if ($delimiter === '\\t') $delimiter = "\t"; // stored literal
  $allowed = array_keys( dev_apt_available_delimiters() );
  $allowed_runtime = array_map(function($v){ return $v==='\\t' ? "\t" : $v; }, $allowed);
  if ( ! in_array($delimiter, $allowed_runtime, true) ) $delimiter = ';';
  $out=fopen('php://output','w'); fputcsv($out,$headers,$delimiter);
  foreach($rows as $r){ $line=[]; foreach($headers as $h){ $line[] = isset($r[$h])?$r[$h]:''; } fputcsv($out,$line,$delimiter); }
  fclose($out); exit;
}
function dev_apt_xmlesc($s){ return str_replace(['&','<','>','"','\''],['&amp;','&lt;','&gt;','&quot;','&apos;'], (string)$s ); }
function dev_apt_output_xlsx($headers,$rows,$filename='apartmany-export.xlsx'){
  if(!class_exists('ZipArchive')) wp_die(__('Chýba PHP ZipArchive.','developer-apartments'));
  $tmp=wp_tempnam('dev-apt'); $z=new ZipArchive(); $z->open($tmp,ZipArchive::OVERWRITE);
  $z->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>');
  $z->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
  $sheet.='<row r="1">'; foreach($headers as $h){ $sheet.='<c t="inlineStr"><is><t>'.dev_apt_xmlesc($h).'</t></is></c>'; } $sheet.='</row>';
  $r=2; foreach($rows as $row){ $sheet.='<row r="'.$r.'">'; foreach($headers as $h){ $v = isset($row[$h]) ? $row[$h] : ''; $sheet.='<c t="inlineStr"><is><t>'.dev_apt_xmlesc($v).'</t></is></c>'; } $sheet.='</row>'; $r++; }
  $sheet.='</sheetData></worksheet>';
  $z->addFromString('xl/worksheets/sheet1.xml',$sheet);
  $z->addFromString('docProps/app.xml','<?xml version="1.0" encoding="UTF-8"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Developer Apartments</Application></Properties>');
  $z->addFromString('docProps/core.xml','<?xml version="1.0" encoding="UTF-8"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Export</dc:title></cp:coreProperties>');
  $z->close();
  nocache_headers(); header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="'.basename($filename).'"'); readfile($tmp); @unlink($tmp); exit;
}

// =========== IMPORT HELPERS ==========
function dev_apt_detect_csv_delimiter($path){
  $sample = file_get_contents($path, false, null, 0, 4096);
  if ($sample === false) return ';';
  $candidates = [',',';','\t'];
  $best = ';'; $bestHits = -1;
  foreach($candidates as $d){
    $lines = preg_split("/\r\n|\r|\n/", $sample);
    $hits = 0;
    foreach($lines as $i=>$ln){ if ($i>=5) break; $parts = str_getcsv($ln, $d==='\t'?"\t":$d); $hits += max(0, count($parts)-1); }
    if ($hits > $bestHits) { $bestHits=$hits; $best=$d; }
  }
  return $best;
}
function dev_apt_parse_csv_file($path,$ui_delimiter){
  if ($ui_delimiter === '\\t') $ui_delimiter = "\t";
  $rows = []; $head = null;
  $try = function($delimiter) use ($path, &$rows, &$head){
    $rows = []; if(($h=fopen($path,'r'))){ $head = fgetcsv($h,0,$delimiter); if(!$head) $head=[]; while(($row=fgetcsv($h,0,$delimiter))!==false){ $assoc=[]; foreach($head as $i=>$k){ $assoc[$k] = $row[$i]??''; } $rows[]=$assoc; } fclose($h); return true; } return false; };
  $ok = $try($ui_delimiter);
  $need_auto = (!$ok) || (is_array($head) && count($head) <= 1);
  if ($need_auto){ $auto = dev_apt_detect_csv_delimiter($path); $try($auto==='\\t'?"\t":$auto); }
  return $rows;
}
function dev_apt_parse_xlsx_file($path){
  $out=[]; if(!class_exists('ZipArchive')) return $out; $z=new ZipArchive(); if($z->open($path)!==true) return $out;
  // sharedStrings
  $sst = []; $ss_xml = $z->getFromName('xl/sharedStrings.xml');
  if ($ss_xml){ $sx = @simplexml_load_string($ss_xml); if($sx && isset($sx->si)){ foreach($sx->si as $si){ $text=''; if(isset($si->t)) $text.=(string)$si->t; if(isset($si->r)) foreach($si->r as $r){ if(isset($r->t)) $text.=(string)$r->t; } $sst[]=$text; } } }
  // sheet1
  $sheet = $z->getFromName('xl/worksheets/sheet1.xml'); if(!$sheet){ $z->close(); return $out; }
  $sx=@simplexml_load_string($sheet); if(!$sx || !isset($sx->sheetData->row)){ $z->close(); return $out; }
  $headers=[]; $rowIndex=0;
  foreach($sx->sheetData->row as $r){ $cells=[]; foreach($r->c as $c){ $t=(string)$c['t']; if($t==='s'){ $idx=intval((string)$c->v); $cells[] = isset($sst[$idx])?$sst[$idx]:''; } elseif($t==='inlineStr'){ $cells[]=(string)$c->is->t; } else { $cells[]=(string)$c->v; } }
    if($rowIndex===0){ $headers=$cells; } else { $assoc=[]; foreach($headers as $i=>$k){ $assoc[$k]=$cells[$i]??''; } $out[]=$assoc; } $rowIndex++; }
  $z->close(); return $out;
}

// =========== APPLY ROWS ==========
function dev_apt_find_post($row){
  $pid=0; if(!empty($row['apartment_code'])){ $q=new WP_Query(['post_type'=>'apartment','meta_key'=>'apartment_code','meta_value'=>$row['apartment_code'],'posts_per_page'=>1,'fields'=>'ids']); if($q->have_posts()){ $pid=(int)$q->posts[0]; } wp_reset_postdata(); }
  if(!$pid && !empty($row['ID']))   $pid=(int)$row['ID'];
  if(!$pid && !empty($row['slug'])) { $p=get_page_by_path(sanitize_title($row['slug']), OBJECT, 'apartment'); if($p) $pid=(int)$p->ID; }
  return $pid;
}
function dev_apt_apply_row($row,$opts){
  $pid=dev_apt_find_post($row); if(!$pid) return ['status'=>'miss','msg'=>'Post not found'];
  if(!empty($row['status_slug'])){ $t=get_term_by('slug', sanitize_title($row['status_slug']), 'apartment_status'); if($t&&!is_wp_error($t)) wp_set_post_terms($pid, [$t->term_id], 'apartment_status', false); }
  if(!empty($row['project_structure_slug'])){ $t=get_term_by('slug', sanitize_title($row['project_structure_slug']), 'project_structure'); if($t&&!is_wp_error($t)) wp_set_post_terms($pid, [$t->term_id], 'project_structure', false); }
  $map=[ 'apartment_code','apartment_rooms', 'apt_area_interior'=>'area_interior','apt_area_exterior'=>'area_exterior','apt_area_total'=>'area_total','apt_cellar_area'=>'cellar_area','apt_cellar_yes'=>'cellar_yes','apt_price_list'=>'price_list','apt_price_discount'=>'price_discount','apt_price_presale'=>'price_presale' ];
  foreach($map as $mk=>$src){ if(is_int($mk)){ $mk=$src; } $val = $row[$src]??''; if($val==='') delete_post_meta($pid,$mk); else update_post_meta($pid,$mk,$val); }
  return ['status'=>'ok','msg'=>'updated','ID'=>$pid];
}
function dev_apt_apply_row_pricing($row){
  $pid=dev_apt_find_post($row); if(!$pid) return ['status'=>'miss','msg'=>'Post not found'];
  if(isset($row['status_slug'])){ $t=get_term_by('slug', sanitize_title($row['status_slug']), 'apartment_status'); if($t&&!is_wp_error($t)) wp_set_post_terms($pid, [$t->term_id], 'apartment_status', false); }
  foreach(['price_list'=>'apt_price_list','price_discount'=>'apt_price_discount','price_presale'=>'apt_price_presale'] as $src=>$mk){
    if(array_key_exists($src,$row)){
      $v = trim((string)$row[$src]);
      if($v==='') delete_post_meta($pid,$mk); else update_post_meta($pid,$mk, $v);
    }
  }
  return ['status'=>'ok','msg'=>'updated','ID'=>$pid];
}

// =========== HANDLERS ==========
add_action('admin_post_dev_apt_export', function(){
  if(!current_user_can('manage_options')) wp_die('forbidden');
  check_admin_referer('dev_apt_export');
  $opts=dev_apt_get_options();
  $filters = array();
  if(isset($_POST['filter_status']) && $_POST['filter_status']!=='') $filters['status_slug'] = sanitize_title($_POST['filter_status']);
  if(isset($_POST['filter_structure']) && $_POST['filter_structure']!=='') $filters['project_structure'] = intval($_POST['filter_structure']);
  if(isset($_POST['rooms_min']) && $_POST['rooms_min']!=='') $filters['rooms_min'] = intval($_POST['rooms_min']);
  if(isset($_POST['rooms_max']) && $_POST['rooms_max']!=='') $filters['rooms_max'] = intval($_POST['rooms_max']);
  $headers=dev_apt_headers();
  $rows=dev_apt_collect_rows($filters);
  $fmt=isset($_POST['format']) && $_POST['format']==='xlsx' ? 'xlsx':'csv';
  if($fmt==='xlsx') dev_apt_output_xlsx($headers,$rows,'apartmany-export.xlsx'); else dev_apt_output_csv($headers,$rows,$opts['csv_delimiter'],'apartmany-export.csv');
});

add_action('admin_post_dev_apt_export_pricing', function(){
  if(!current_user_can('manage_options')) wp_die('forbidden');
  check_admin_referer('dev_apt_export_pricing');
  $opts=dev_apt_get_options();
  $filters = array();
  if(isset($_POST['filter_status']) && $_POST['filter_status']!=='') $filters['status_slug'] = sanitize_title($_POST['filter_status']);
  if(isset($_POST['filter_structure']) && $_POST['filter_structure']!=='') $filters['project_structure'] = intval($_POST['filter_structure']);
  if(isset($_POST['rooms_min']) && $_POST['rooms_min']!=='') $filters['rooms_min'] = intval($_POST['rooms_min']);
  if(isset($_POST['rooms_max']) && $_POST['rooms_max']!=='') $filters['rooms_max'] = intval($_POST['rooms_max']);

  $full = dev_apt_collect_rows($filters);
  $headers = dev_apt_headers_pricing();
  $rows = array();
  foreach($full as $r){ $rows[] = array_intersect_key($r, array_flip($headers)); }
  $fmt=isset($_POST['format']) && $_POST['format']==='xlsx' ? 'xlsx':'csv';
  if($fmt==='xlsx') dev_apt_output_xlsx($headers,$rows,'apartmany-ceny-statusy.xlsx'); else dev_apt_output_csv($headers,$rows,$opts['csv_delimiter'],'apartmany-ceny-statusy.csv');
});

add_action('admin_post_dev_apt_import', function(){
  if(!current_user_can('manage_options')) wp_die('forbidden');
  check_admin_referer('dev_apt_import');
  if(empty($_FILES['import_file']['tmp_name'])) wp_die('No file');
  $opts=dev_apt_get_options(); $tmp=$_FILES['import_file']['tmp_name']; $name=$_FILES['import_file']['name']; $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
  if($ext==='xlsx'){ $rows=dev_apt_parse_xlsx_file($tmp); } else { $rows=dev_apt_parse_csv_file($tmp, $opts['csv_delimiter']); }
  $dry = isset($_POST['mode']) && $_POST['mode']==='dry'; $headers=dev_apt_headers();
  if($dry){ echo '<div class="wrap"><h1>Developer Apartments – Dry‑run import</h1><p>'.esc_html(count($rows)).' riadkov.</p><table class="widefat striped"><thead><tr>'; foreach($headers as $h){ echo '<th>'.esc_html($h).'</th>'; } echo '</tr></thead><tbody>'; foreach($rows as $r){ echo '<tr>'; foreach($headers as $h){ echo '<td>'.esc_html($r[$h]??'').'</td>'; } echo '</tr>'; } echo '</tbody></table><p><a class="button" href="'.esc_url(admin_url('edit.php?post_type=apartment&page=dev-apt-settings')).'">Späť</a></p></div>'; exit; }
  // logging (TXT + CSV)
  $u = wp_upload_dir(); $dir = trailingslashit($u['basedir']).'dev-apt-logs/'; if( ! file_exists($dir)) wp_mkdir_p($dir);
  $base = 'import-'.date('Ymd-His'); $txt=$dir.$base.'.log'; $csv=$dir.$base.'.csv';
  $fh_t=fopen($txt,'a'); $fh_c=fopen($csv,'w'); fputcsv($fh_c,['row','apartment_code','ID','slug','result','message'],';');
  fwrite($fh_t, "Developer Apartments import\nFile: $name\nRows: ".count($rows)."\n\n");
  $ok=0;$miss=0;$i=1; foreach($rows as $r){ $res=dev_apt_apply_row($r,$opts); if($res['status']==='ok'){ $ok++; fwrite($fh_t,'OK  ID='.$res['ID'].' code='.(isset($r['apartment_code'])?$r['apartment_code']:'')."\n"); fputcsv($fh_c,[$i,$r['apartment_code']??'',$r['ID']??'',$r['slug']??'','OK','updated'],';'); } else { $miss++; fwrite($fh_t,'MISS code='.(isset($r['apartment_code'])?$r['apartment_code']:'').' slug='.(isset($r['slug'])?$r['slug']:'').' ID='.(isset($r['ID'])?$r['ID']:'')."\n"); fputcsv($fh_c,[$i,$r['apartment_code']??'',$r['ID']??'',$r['slug']??'','MISS','not matched'],';'); } $i++; }
  fclose($fh_t); fclose($fh_c);
  // bump cache
  dev_apt_cache_bump();
  wp_safe_redirect( add_query_arg(['post_type'=>'apartment','page'=>'dev-apt-settings','import_done'=>1,'ok'=>$ok,'miss'=>$miss,'log'=>rawurlencode(basename($txt)),'logcsv'=>rawurlencode(basename($csv))], admin_url('edit.php')) ); exit; 
});

add_action('admin_post_dev_apt_import_pricing', function(){
  if(!current_user_can('manage_options')) wp_die('forbidden');
  check_admin_referer('dev_apt_import_pricing');
  if(empty($_FILES['import_file']['tmp_name'])) wp_die('No file');
  $opts=dev_apt_get_options(); $tmp=$_FILES['import_file']['tmp_name']; $name=$_FILES['import_file']['name']; $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
  if($ext==='xlsx'){ $rows=dev_apt_parse_xlsx_file($tmp); } else { $rows=dev_apt_parse_csv_file($tmp, $opts['csv_delimiter']); }
  $dry = isset($_POST['mode']) && $_POST['mode']==='dry'; $headers=dev_apt_headers_pricing();
  if($dry){ echo '<div class="wrap"><h1>Developer Apartments – Dry‑run import (Ceny & Statusy)</h1><p>'.esc_html(count($rows)).' riadkov.</p><table class="widefat striped"><thead><tr>'; foreach($headers as $h){ echo '<th>'.esc_html($h).'</th>'; } echo '</tr></thead><tbody>'; foreach($rows as $r){ echo '<tr>'; foreach($headers as $h){ echo '<td>'.esc_html($r[$h]??'').'</td>'; } echo '</tr>'; } echo '</tbody></table><p><a class="button" href="'.esc_url(admin_url('edit.php?post_type=apartment&page=dev-apt-settings')).'">Späť</a></p></div>'; exit; }
  // logging
  $u = wp_upload_dir(); $dir = trailingslashit($u['basedir']).'dev-apt-logs/'; if( ! file_exists($dir)) wp_mkdir_p($dir);
  $base = 'import-'.date('Ymd-His'); $txt=$dir.$base.'.log'; $csv=$dir.$base.'.csv'; $fh_t=fopen($txt,'a'); $fh_c=fopen($csv,'w'); fputcsv($fh_c,['row','apartment_code','ID','slug','result','message'],';');
  fwrite($fh_t, "Developer Apartments import (pricing)\nFile: $name\nRows: ".count($rows)."\n\n");
  $ok=0;$miss=0;$i=1; foreach($rows as $r){ $res=dev_apt_apply_row_pricing($r); if($res['status']==='ok'){ $ok++; fwrite($fh_t,'OK  ID='.$res['ID'].' code='.(isset($r['apartment_code'])?$r['apartment_code']:'')."\n"); fputcsv($fh_c,[$i,$r['apartment_code']??'',$r['ID']??'',$r['slug']??'','OK','updated'],';'); } else { $miss++; fwrite($fh_t,'MISS code='.(isset($r['apartment_code'])?$r['apartment_code']:'').' slug='.(isset($r['slug'])?$r['slug']:'').' ID='.(isset($r['ID'])?$r['ID']:'')."\n"); fputcsv($fh_c,[$i,$r['apartment_code']??'',$r['ID']??'',$r['slug']??'','MISS','not matched'],';'); } $i++; }
  fclose($fh_t); fclose($fh_c);
  dev_apt_cache_bump();
  wp_safe_redirect( add_query_arg(['post_type'=>'apartment','page'=>'dev-apt-settings','import_done'=>1,'ok'=>$ok,'miss'=>$miss,'log'=>rawurlencode(basename($txt)),'logcsv'=>rawurlencode(basename($csv))], admin_url('edit.php')) ); exit; 
});

// =========== UI RENDER ==========
function dev_apt_render_settings_page(){ $o=dev_apt_get_options(); $delims = dev_apt_available_delimiters(); $ttl_opts = [0=>'Vypnuté (bez cache)',600=>'10 min',1800=>'30 min',3600=>'1 h',21600=>'6 h',43200=>'12 h']; ?>
<div class="wrap">
  <h1><?php _e('Developer Apartments – Nastavenia & Import/Export','developer-apartments');?></h1>

  <?php if(isset($_GET['import_done'])): ?>
    <div class="notice notice-success"><p><?php printf(__('Import hotový: %d aktualizovaných, %d nespárovaných.','developer-apartments'), (int)$_GET['ok'], (int)$_GET['miss']); 
      if(isset($_GET['log'])){ $u = wp_upload_dir(); $url = trailingslashit($u['baseurl']).'dev-apt-logs/'.sanitize_file_name($_GET['log']); echo ' – <a href="'.esc_url($url).'" target="_blank" rel="noopener">'.__('Stiahnuť log','developer-apartments').'</a>'; }
      if(isset($_GET['logcsv'])){ $u = wp_upload_dir(); $url = trailingslashit($u['baseurl']).'dev-apt-logs/'.sanitize_file_name($_GET['logcsv']); echo ' – <a href="'.esc_url($url).'" target="_blank" rel="noopener">CSV log</a>'; }
    ?></p></div>
  <?php endif; ?>

  <form method="post" action="options.php" style="margin-top:10px;">
    <?php settings_fields('dev_apt_options'); ?>
    <table class="form-table" role="presentation"><tbody>
      <tr>
        <th scope="row"><label><?php _e('CSV oddeľovač','developer-apartments');?></label></th>
        <td>
          <?php foreach ($delims as $value => $label): $checked = checked($o['csv_delimiter'], $value, false); ?>
            <label style="display:inline-block; margin-right:12px;">
              <input type="radio" name="dev_apt_options[csv_delimiter]" value="<?php echo esc_attr($value); ?>" <?php echo $checked; ?> />
              <?php echo esc_html($label); ?>
            </label>
          <?php endforeach; ?>
        </td>
      </tr>
      <tr>
        <th scope="row"><label><?php _e('Slug statusu „Voľný“','developer-apartments');?></label></th>
        <td><input type="text" name="dev_apt_options[free_status_slug]" value="<?php echo esc_attr($o['free_status_slug']); ?>" /></td>
      </tr>
      <tr>
        <th scope="row"><label><?php _e('TTL cache pre mapu (free_count)','developer-apartments');?></label></th>
        <td>
          <select name="dev_apt_options[cache_ttl]">
            <?php foreach($ttl_opts as $sec=>$lbl){ echo '<option value="'.esc_attr($sec).'" '.selected($o['cache_ttl'],$sec,false).'>'.esc_html($lbl).'</option>'; } ?>
          </select>
          <p class="description"><?php _e('0 = vypnuté. Zmena statusu/štruktúr cache automaticky zneplatní.','developer-apartments');?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php _e('Odinštalácia – čo zmazať','developer-apartments');?></th>
        <td>
          <label><input type="checkbox" name="dev_apt_options[uninstall_delete_posts]" value="1" <?php checked($o['uninstall_delete_posts'],1); ?>> <?php _e('Byty (CPT)','developer-apartments');?></label><br/>
          <label><input type="checkbox" name="dev_apt_options[uninstall_delete_terms]" value="1" <?php checked($o['uninstall_delete_terms'],1); ?>> <?php _e('Taxonómie (termíny)','developer-apartments');?></label><br/>
          <label><input type="checkbox" name="dev_apt_options[uninstall_delete_term_meta]" value="1" <?php checked($o['uninstall_delete_term_meta'],1); ?>> <?php _e('Term meta / Mapy','developer-apartments');?></label><br/>
          <label><input type="checkbox" name="dev_apt_options[uninstall_delete_options]" value="1" <?php checked($o['uninstall_delete_options'],1); ?>> <?php _e('Nastavenia','developer-apartments');?></label>
        </td>
      </tr>
    </tbody></table>
    <?php submit_button(); ?>
  </form>

  <hr/>
  <h2><?php _e('Export','developer-apartments');?></h2>
  <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
    <?php wp_nonce_field('dev_apt_export'); ?>
    <input type="hidden" name="action" value="dev_apt_export" />
    <fieldset style="margin:8px 0;padding:8px;border:1px solid #ddd;">
      <legend><?php _e('Filtre','developer-apartments'); ?></legend>
      <label><?php _e('Status','developer-apartments'); ?>:
        <select name="filter_status">
          <option value="">— <?php _e('všetko','developer-apartments'); ?> —</option>
          <?php $sts = get_terms(array('taxonomy'=>'apartment_status','hide_empty'=>false)); if(!is_wp_error($sts)) foreach($sts as $t){ echo '<option value="'.esc_attr($t->slug).'">'.esc_html($t->name).'</option>'; } ?>
        </select>
      </label>
      <label style="margin-left:12px;"><?php _e('Štruktúra','developer-apartments'); ?>:
        <select name="filter_structure">
          <option value="">— <?php _e('všetko','developer-apartments'); ?> —</option>
          <?php $ps = get_terms(array('taxonomy'=>'project_structure','hide_empty'=>false)); if(!is_wp_error($ps)) foreach($ps as $t){ echo '<option value="'.esc_attr($t->term_id).'">'.esc_html($t->name).'</option>'; } ?>
        </select>
      </label>
      <label style="margin-left:12px;"><?php _e('Izby od','developer-apartments'); ?>: <input type="number" name="rooms_min" min="0" step="1" style="width:90px"></label>
      <label style="margin-left:6px;"><?php _e('do','developer-apartments'); ?>: <input type="number" name="rooms_max" min="0" step="1" style="width:90px"></label>
    </fieldset>
    <label><input type="radio" name="format" value="csv" checked> CSV</label>
    <label style="margin-left:10px"><input type="radio" name="format" value="xlsx"> XLSX</label>
    <?php submit_button(__('Stiahnuť export','developer-apartments'), 'primary', 'submit', false, ['style'=>'margin-left:10px']); ?>
  </form>

  <h2><?php _e('Import','developer-apartments');?></h2>
  <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('dev_apt_import'); ?>
    <input type="hidden" name="action" value="dev_apt_import" />
    <input type="file" name="import_file" accept=".csv,.xlsx" required />
    <label style="margin-left:10px"><input type="radio" name="mode" value="dry" checked> <?php _e('Dry‑run (len náhľad)','developer-apartments');?></label>
    <label style="margin-left:10px"><input type="radio" name="mode" value="commit"> <?php _e('Importovať','developer-apartments');?></label>
    <?php submit_button(__('Spustiť import','developer-apartments'), 'secondary', 'submit', false, ['style'=>'margin-left:10px']); ?>
    <p class="description"><?php _e('Pozn.: XLSX import podporuje inline strings aj sharedStrings.','developer-apartments');?></p>
  </form>

  <hr/>
  <h2><?php _e('Ceny & Statusy – rýchly export/import','developer-apartments');?></h2>
  <p class="description"><?php _e('Tento export obsahuje len identifikátory, status a tri cenové stĺpce. Po úprave ho môžete priamo naimportovať (menia sa len ceny a status).','developer-apartments');?></p>
  <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin-bottom:8px;">
    <?php wp_nonce_field('dev_apt_export_pricing'); ?>
    <input type="hidden" name="action" value="dev_apt_export_pricing" />
    <fieldset style="margin:8px 0;padding:8px;border:1px solid #ddd;">
      <legend><?php _e('Filtre','developer-apartments'); ?></legend>
      <label><?php _e('Status','developer-apartments'); ?>:
        <select name="filter_status">
          <option value="">— <?php _e('všetko','developer-apartments'); ?> —</option>
          <?php $sts = get_terms(array('taxonomy'=>'apartment_status','hide_empty'=>false)); if(!is_wp_error($sts)) foreach($sts as $t){ echo '<option value="'.esc_attr($t->slug).'">'.esc_html($t->name).'</option>'; } ?>
        </select>
      </label>
      <label style="margin-left:12px;"><?php _e('Štruktúra','developer-apartments'); ?>:
        <select name="filter_structure">
          <option value="">— <?php _e('všetko','developer-apartments'); ?> —</option>
          <?php $ps = get_terms(array('taxonomy'=>'project_structure','hide_empty'=>false)); if(!is_wp_error($ps)) foreach($ps as $t){ echo '<option value="'.esc_attr($t->term_id).'">'.esc_html($t->name).'</option>'; } ?>
        </select>
      </label>
      <label style="margin-left:12px;"><?php _e('Izby od','developer-apartments'); ?>: <input type="number" name="rooms_min" min="0" step="1" style="width:90px"></label>
      <label style="margin-left:6px;"><?php _e('do','developer-apartments'); ?>: <input type="number" name="rooms_max" min="0" step="1" style="width:90px"></label>
    </fieldset>
    <label><input type="radio" name="format" value="csv" checked> CSV</label>
    <label style="margin-left:10px"><input type="radio" name="format" value="xlsx"> XLSX</label>
    <?php submit_button(__('Stiahnuť ceny & statusy','developer-apartments'), 'primary', 'submit', false, ['style'=>'margin-left:10px']); ?>
  </form>
  <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('dev_apt_import_pricing'); ?>
    <input type="hidden" name="action" value="dev_apt_import_pricing" />
    <input type="file" name="import_file" accept=".csv,.xlsx" required />
    <label style="margin-left:10px"><input type="radio" name="mode" value="dry" checked> <?php _e('Dry‑run (len náhľad)','developer-apartments');?></label>
    <label style="margin-left:10px"><input type="radio" name="mode" value="commit"> <?php _e('Importovať','developer-apartments');?></label>
    <?php submit_button(__('Importovať ceny & statusy','developer-apartments'), 'secondary', 'submit', false, ['style'=>'margin-left:10px']); ?>
  </form>
</div>
<?php }

// =========== CACHE BUMP ==========
function dev_apt_cache_bump(){
  $o = dev_apt_get_options(); $o['cache_bump'] = max(1, intval($o['cache_bump'])) + 1; update_option('dev_apt_options', $o);
}
add_action('save_post_apartment', function(){ dev_apt_cache_bump(); });
add_action('set_object_terms', function($object_id,$terms,$tt_ids,$taxonomy){ if( get_post_type($object_id) === 'apartment' && in_array($taxonomy, array('apartment_status','project_structure'), true) ) dev_apt_cache_bump(); }, 10, 4);
foreach(array('apartment_status','project_structure') as $tax){ add_action('created_'.$tax, 'dev_apt_cache_bump'); add_action('edited_'.$tax,  'dev_apt_cache_bump'); add_action('delete_'.$tax,  'dev_apt_cache_bump'); }
