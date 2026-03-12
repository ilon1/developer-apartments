<?php
if ( ! defined('ABSPATH') ) exit;
/**
 * Developer Apartments – Settings & Import/Export
 * - Export: plný (všetky polia + taxonomie + obrázok + pôdorys + galéria) alebo len Ceny & Statusy.
 * - Formáty: CSV a XLSX pre export aj import.
 * - Import: nové byty vytvára podľa slug/title; pri update mení len polia s neprázdnou hodnotou v súbore.
 */

/* ========================= MENU ========================= */
function dev_apt_can_manage_settings() {
    return current_user_can( 'manage_options' );
}

function dev_apt_can_manage_pricing() {
    return current_user_can( 'manage_options' ) || ( defined( 'DEV_APT_CAP_PRICING' ) && current_user_can( DEV_APT_CAP_PRICING ) );
}

add_action('admin_menu', function(){
    $cap = defined( 'DEV_APT_CAP_PRICING' ) ? DEV_APT_CAP_PRICING : 'manage_options';
    add_submenu_page(
        'edit.php?post_type=apartment',
        __('Developer Apartments','developer-apartments'),
        __('Nastavenia','developer-apartments'),
        $cap,
        'dev-apt-settings',
        'dev_apt_render_settings_page',
        30
    );
});

/* ========================= OPTIONS ========================= */
add_action('admin_init', function(){
    register_setting('dev_apt_options','dev_apt_options','dev_apt_sanitize_options');
});

function dev_apt_default_options(){
    return [
        'csv_delimiter'              => ';',     // ; , \t
        'csv_decimal_comma'          => 1,       // CSV: zapisovať čísla s čiarkou
        'free_status_slug'           => 'volny',
        'vat_percent'                => 23,      // DPH v percentách pre výpočet ceny bez DPH
        'uninstall_delete_posts'     => 0,
        'uninstall_delete_terms'     => 0,
        'uninstall_delete_term_meta' => 0,
        'uninstall_delete_options'   => 0,
        'cache_bump'                 => 1,
        'cache_ttl'                  => 3600,
        'import_create_status'       => 'publish', // 'draft' alebo 'publish'
    ];
}
function dev_apt_get_options(){
    return wp_parse_args( get_option('dev_apt_options',[]), dev_apt_default_options() );
}
function dev_apt_available_delimiters(){
    $list = [
        ';'   => __('Bodkočiarka ; (default)','developer-apartments'),
        ','   => __('Čiarka ,','developer-apartments'),
        '\\t' => __('Tabulátor \\t','developer-apartments'),
    ];
    $list = apply_filters('dev_apt_csv_delimiters', $list);
    $clean = [];
    foreach($list as $k=>$label){
        $ks=(string)$k; if($ks==='') continue; $clean[$ks]=$label;
    }
    if (!isset($clean[';'])){
        $clean = array_merge([';'=>__('Bodkočiarka ; (default)','developer-apartments')], $clean);
    }
    return $clean;
}
function dev_apt_sanitize_options($in){
    $o = dev_apt_get_options();

    $allowed = array_keys( dev_apt_available_delimiters() );
    $new_delim = isset($in['csv_delimiter']) ? (string)$in['csv_delimiter'] : ';';
    if ( ! in_array($new_delim, $allowed, true) ) { $new_delim = ';'; }
    $o['csv_delimiter'] = $new_delim;

    $o['csv_decimal_comma'] = !empty($in['csv_decimal_comma']) ? 1 : 0;
    $o['free_status_slug']  = sanitize_title($in['free_status_slug'] ?? 'volny');

    // DPH percento – jednoduché číslo 0–100 (môže byť aj desatinné, napr. 23.5)
    $vat = isset($in['vat_percent']) ? floatval($in['vat_percent']) : 23.0;
    if ($vat < 0)   $vat = 0;
    if ($vat > 100) $vat = 100;
    $o['vat_percent'] = $vat;

    $ttl = isset($in['cache_ttl']) ? intval($in['cache_ttl']) : 3600;
    $allowed_ttl = array(0,600,1800,3600,21600,43200);
    if ( !in_array($ttl,$allowed_ttl,true) ) $ttl = 3600;
    $o['cache_ttl'] = $ttl;

    foreach(['uninstall_delete_posts','uninstall_delete_terms','uninstall_delete_term_meta','uninstall_delete_options'] as $k){
        $o[$k] = !empty($in[$k]) ? 1 : 0;
    }

    $status = in_array(($in['import_create_status'] ?? 'publish'), ['draft','publish'], true) ? $in['import_create_status'] : 'publish';
    $o['import_create_status'] = $status;

    return $o;
}

/**
 * Vráti nastavenú sadzbu DPH v percentách (0–100). Štandardne 23 %.
 */
function dev_apt_get_vat_rate(){
    $opts = dev_apt_get_options();
    $vat  = isset($opts['vat_percent']) ? floatval($opts['vat_percent']) : 23.0;
    if ($vat < 0)   $vat = 0;
    if ($vat > 100) $vat = 100;
    return $vat;
}

/* ========================= EXPORT DATA HELPERS ========================= */
function dev_apt_build_project_path($tid){
    $parts = [];
    $anc = array_reverse(get_ancestors($tid,'project_structure'));
    foreach($anc as $a){ $t=get_term($a,'project_structure'); if($t && !is_wp_error($t)) $parts[]=$t->name; }
    $self = get_term($tid,'project_structure'); if($self && !is_wp_error($self)) $parts[]=$self->name;
    return implode(' / ',$parts);
}

/** HLAVIČKA EXPORTU – všetky dostupné polia bytu vrátane taxonomií, obrázkov a galérie */
function dev_apt_headers(){
    return [
        'apartment_code',
        'ID',
        'slug',
        'title',
        'status_slug',
        'status_name',
        'project_structure_slug',
        'project_structure_path',
        'apartment_type_slug',
        'apartment_type_name',
        'rooms',
        'area_interior',
        'area_exterior',
        'area_total',
        'cellar_area',
        'cellar_yes',
        'price_list',
        'price_discount',
        'price_presale',
        'permalink',
        'featured_image_id',
        'featured_image_url',
        'floorplan_file_id',
        'floorplan_label',
        'gallery_ids',
    ];
}

/** Nazbieraj riadky + typ bytu */
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

    $rows=[];
    $q=new WP_Query($args);
    while($q->have_posts()){
        $q->the_post(); $pid=get_the_ID();

        // Status
        $st = wp_get_post_terms($pid,'apartment_status',['number'=>1]);
        $st_slug = ($st && !is_wp_error($st)) ? $st[0]->slug : '';
        $st_name = ($st && !is_wp_error($st)) ? $st[0]->name : '';

        // Štruktúra
        $ps = wp_get_post_terms($pid,'project_structure',['number'=>1]);
        $ps_slug = ($ps && !is_wp_error($ps)) ? $ps[0]->slug : '';
        $ps_id   = ($ps && !is_wp_error($ps)) ? (int)$ps[0]->term_id : 0;
        $ps_path = $ps_id ? dev_apt_build_project_path($ps_id) : '';

        // Typ bytu
        $ty = wp_get_post_terms($pid,'apartment_type',['number'=>1]);
        $ty_slug = ($ty && !is_wp_error($ty)) ? $ty[0]->slug : '';
        $ty_name = ($ty && !is_wp_error($ty)) ? $ty[0]->name : '';

        $img_id  = get_post_thumbnail_id($pid);
        $img_url = $img_id ? wp_get_attachment_url($img_id) : '';

        $floorplan_id = (int) get_post_meta($pid, 'apt_floorplan_file_id', true);
        $gallery_raw  = get_post_meta($pid, 'apt_gallery_ids', true);
        $gallery_ids  = is_string($gallery_raw) ? trim($gallery_raw) : '';

        $rows[] = [
            'apartment_code'         => get_post_meta($pid,'apartment_code',true),
            'ID'                     => $pid,
            'slug'                   => get_post_field('post_name',$pid),
            'title'                  => get_the_title($pid),
            'status_slug'            => $st_slug,
            'status_name'            => $st_name,
            'project_structure_slug' => $ps_slug,
            'project_structure_path' => $ps_path,
            'apartment_type_slug'    => $ty_slug,
            'apartment_type_name'    => $ty_name,
            'rooms'                  => get_post_meta($pid,'apartment_rooms',true),
            'area_interior'          => get_post_meta($pid,'apt_area_interior',true),
            'area_exterior'          => get_post_meta($pid,'apt_area_exterior',true),
            'area_total'             => get_post_meta($pid,'apt_area_total',true),
            'cellar_area'            => get_post_meta($pid,'apt_cellar_area',true),
            'cellar_yes'             => get_post_meta($pid,'apt_cellar_yes',true),
            'price_list'             => get_post_meta($pid,'apt_price_list',true),
            'price_discount'         => get_post_meta($pid,'apt_price_discount',true),
            'price_presale'          => get_post_meta($pid,'apt_price_presale',true),
            'permalink'              => get_permalink($pid),
            'featured_image_id'      => $img_id,
            'featured_image_url'     => $img_url,
            'floorplan_file_id'      => $floorplan_id ? $floorplan_id : '',
            'floorplan_label'        => get_post_meta($pid,'apt_floorplan_label',true),
            'gallery_ids'            => $gallery_ids,
        ];
    }
    wp_reset_postdata();
    return $rows;
}

function dev_apt_headers_pricing(){
    return ['apartment_code','ID','slug','title','status_slug','price_list','price_discount','price_presale'];
}

/* ========================= EXPORT FILES ========================= */
function dev_apt_is_decimal($v){
    if ($v === '' || $v === null) return false;
    $s = (string)$v;
    return (bool)preg_match('/^-?\d+(\.\d+)?$/', str_replace(' ', '', $s));
}
function dev_apt_output_csv($headers,$rows,$delimiter,$filename='apartmany-export.csv'){
    $opts = dev_apt_get_options();
    $decimal_to_comma = !empty($opts['csv_decimal_comma']);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.basename($filename).'"');

    echo "\xEF\xBB\xBF"; // BOM

    if ($delimiter === '\t') $delimiter = "\t";
    $allowed = array_keys( dev_apt_available_delimiters() );
    $allowed_runtime = array_map(function($v){ return $v==='\t' ? "\t" : $v; }, $allowed);
    if ( ! in_array($delimiter, $allowed_runtime, true) ) $delimiter = ';';

    $out=fopen('php://output','w');
    fputcsv($out,$headers,$delimiter);

    $numeric_keys = ['rooms','area_interior','area_exterior','area_total','cellar_area','price_list','price_discount','price_presale'];

    foreach($rows as $r){
        $line=[];
        foreach($headers as $h){
            $val = isset($r[$h]) ? $r[$h] : '';
            if ($decimal_to_comma && in_array($h,$numeric_keys,true) && dev_apt_is_decimal($val)){
                // 1234.56 -> 1234,56 (SK CSV)
                $val = str_replace('.', ',', (string)$val);
            }
            $line[] = $val;
        }
        fputcsv($out,$line,$delimiter);
    }
    fclose($out); exit;
}
function dev_apt_xmlesc($s){
    return str_replace(['&','<','>','"','\''],['&amp;','&lt;','&gt;','&quot;','&apos;'], (string)$s );
}
function dev_apt_output_xlsx($headers,$rows,$filename='apartmany-export.xlsx'){
    if(!class_exists('ZipArchive')) wp_die(__('Chýba PHP ZipArchive.','developer-apartments'));
    $tmp=wp_tempnam('dev-apt'); $z=new ZipArchive(); $z->open($tmp,ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>');
    $z->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    $sheet.='<row r="1">'; foreach($headers as $h){ $sheet.='<c t="inlineStr"><is><t>'.dev_apt_xmlesc($h).'</t></is></c>'; } $sheet.='</row>';
    $r=2; foreach($rows as $row){
        $sheet.='<row r="'.$r.'">'; foreach($headers as $h){ $v = isset($row[$h]) ? $row[$h] : ''; $sheet.='<c t="inlineStr"><is><t>'.dev_apt_xmlesc($v).'</t></is></c>'; } $sheet.='</row>'; $r++;
    }
    $sheet.='</sheetData></worksheet>';
    $z->addFromString('xl/worksheets/sheet1.xml',$sheet);
    $z->addFromString('docProps/app.xml','<?xml version="1.0" encoding="UTF-8"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Developer Apartments</Application></Properties>');
    $z->addFromString('docProps/core.xml','<?xml version="1.0" encoding="UTF-8"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Export</dc:title></cp:coreProperties>');
    $z->close();
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.basename($filename).'"');
    readfile($tmp); @unlink($tmp); exit;
}

/* ========================= PARSERY ========================= */
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
    if ($ui_delimiter === '\t') $ui_delimiter = "\t";
    $rows = []; $head = null;
    $try = function($delimiter) use ($path, &$rows, &$head){
        $rows = []; if(($h=fopen($path,'r'))){
            $head = fgetcsv($h,0,$delimiter); if(!$head) $head=[];
            while(($row=fgetcsv($h,0,$delimiter))!==false){
                $assoc=[];
                foreach($head as $i=>$k){ $assoc[$k] = $row[$i]??''; }
                $rows[]=$assoc;
            }
            fclose($h); return true;
        } return false;
    };
    $ok = $try($ui_delimiter);
    $need_auto = (!$ok) || (is_array($head) && count($head) <= 1);
    if ($need_auto){ $auto = dev_apt_detect_csv_delimiter($path); $try($auto==='\t'?"\t":$auto); }
    return $rows;
}
function dev_apt_parse_xlsx_file($path){
    $out=[]; if(!class_exists('ZipArchive')) return $out; $z=new ZipArchive(); if($z->open($path)!==true) return $out;
    // sharedStrings
    $sst = []; $ss_xml = $z->getFromName('xl/sharedStrings.xml');
    if ($ss_xml){ $sx = @simplexml_load_string($ss_xml);
        if($sx && isset($sx->si)){
            foreach($sx->si as $si){
                $text=''; if(isset($si->t)) $text=(string)$si->t;
                if(isset($si->r)) foreach($si->r as $r){ if(isset($r->t)) $text.=(string)$r->t; }
                $sst[]=$text;
            }
        }
    }
    // sheet1
    $sheet = $z->getFromName('xl/worksheets/sheet1.xml'); if(!$sheet){ $z->close(); return $out; }
    $sx=@simplexml_load_string($sheet); if(!$sx || !isset($sx->sheetData->row)){ $z->close(); return $out; }
    $headers=[]; $rowIndex=0;
    foreach($sx->sheetData->row as $r){ $cells=[]; foreach($r->c as $c){ $t=(string)$c['t']; if($t==='s'){ $idx=intval((string)$c->v); $cells[] = isset($sst[$idx])?$sst[$idx]:''; } elseif($t==='inlineStr'){ $cells[]=(string)$c->is->t; } else { $cells[]=(string)$c->v; } }
        if($rowIndex===0){ $headers=$cells; } else { $assoc=[]; foreach($headers as $i=>$k){ $assoc[$k]=$cells[$i]??''; } $out[]=$assoc; } $rowIndex++; }
    $z->close(); return $out;
}

/* ========================= IMPORT HELPERS ========================= */
/** Stiahne súbor z URL (iba HTTP/HTTPS) do dočasného súboru. Vráti [ 'path' => ..., 'name' => ... ] alebo WP_Error. */
function dev_apt_fetch_file_from_url( $url ) {
    $url = esc_url_raw( trim( $url ) );
    if ( ! $url ) {
        return new WP_Error( 'missing_url', __( 'Zadajte URL súboru.', 'developer-apartments' ) );
    }
    $parsed = parse_url( $url );
    $scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : '';
    $name = isset( $parsed['path'] ) ? basename( $parsed['path'] ) : 'import.csv';

    if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
        return new WP_Error( 'unsupported_scheme', __( 'Podporované sú len http: a https: URL. Pre FTP použite sekciu Import z FTP.', 'developer-apartments' ) );
    }

    $tmp = download_url( $url, 30 );
    if ( is_wp_error( $tmp ) ) {
        return $tmp;
    }
    return [ 'path' => $tmp, 'name' => $name ];
}

/* ========================= FTP OPTIONS (samostatné polia: server, user, heslo, cesta) ========================= */
const DEV_APT_FTP_OPTION = 'dev_apt_ftp_options';

function dev_apt_ftp_default_options() {
    return [
        'host'          => '',
        'user'          => '',
        'pass'          => '',
        'path'          => '',   // adresár alebo celá cesta k súboru
        'auto_enabled'  => 0,
        'auto_interval' => 3600, // sekundy: 900=15min, 3600=1h, 21600=6h
    ];
}

function dev_apt_get_ftp_options() {
    return wp_parse_args( get_option( DEV_APT_FTP_OPTION, [] ), dev_apt_ftp_default_options() );
}

add_action( 'admin_init', function() {
    register_setting( 'dev_apt_ftp_options', DEV_APT_FTP_OPTION, [
        'sanitize_callback' => function( $in ) {
            $o = dev_apt_ftp_default_options();
            $old = get_option( DEV_APT_FTP_OPTION, [] );
            $o['host'] = isset( $in['host'] ) ? sanitize_text_field( $in['host'] ) : '';
            $o['user'] = isset( $in['user'] ) ? sanitize_text_field( $in['user'] ) : '';
            $o['pass'] = isset( $in['pass'] ) && $in['pass'] !== '' ? sanitize_text_field( $in['pass'] ) : ( isset( $old['pass'] ) ? $old['pass'] : '' );
            $o['path'] = isset( $in['path'] ) ? sanitize_text_field( $in['path'] ) : '';
            $o['auto_enabled'] = ! empty( $in['auto_enabled'] ) ? 1 : 0;
            $allowed = [ 900 => 900, 3600 => 3600, 21600 => 21600 ];
            $o['auto_interval'] = isset( $in['auto_interval'] ) && isset( $allowed[ (int) $in['auto_interval'] ] ) ? (int) $in['auto_interval'] : 3600;
            return $o;
        },
    ] );
}, 5 );

/** Pripojí na FTP a vráti connection alebo null. */
function dev_apt_ftp_connect( $host, $user, $pass, $port = 21 ) {
    if ( ! function_exists( 'ftp_connect' ) ) return null;
    $conn = @ftp_connect( $host, $port, 10 );
    if ( ! $conn ) return null;
    if ( ! @ftp_login( $conn, $user ?: 'anonymous', $pass ?: 'anonymous@' ) ) {
        ftp_close( $conn );
        return null;
    }
    @ftp_pasv( $conn, true );
    return $conn;
}

/**
 * Nájde na FTP súbor (podľa cesty – adresár alebo konkrétny súbor), stiahne ho.
 * Vráti [ 'path' => local_tmp_path, 'name' => filename, 'remote_path' => full remote path ] alebo WP_Error.
 * Ak path je adresár: hľadá najnovší .csv alebo .xlsx. Ak path je súbor: stiahne ten súbor.
 */
function dev_apt_ftp_fetch_file( $host, $user, $pass, $path ) {
    $path = trim( $path );
    if ( ! $host || ! $path ) {
        return new WP_Error( 'ftp_missing', __( 'Vyplňte FTP server a cestu.', 'developer-apartments' ) );
    }
    $conn = dev_apt_ftp_connect( $host, $user, $pass );
    if ( ! $conn ) {
        return new WP_Error( 'ftp_connect', __( 'Nepodarilo sa pripojiť na FTP server.', 'developer-apartments' ) );
    }
    $remote = preg_replace( '#/+#', '/', '/' . trim( $path, '/' ) );
    $is_file = ( substr( strtolower( $remote ), -4 ) === '.csv' || substr( strtolower( $remote ), -5 ) === '.xlsx' );
    $remote_file = $remote;
    if ( ! $is_file ) {
        $list = @ftp_nlist( $conn, $remote );
        if ( ! is_array( $list ) ) {
            ftp_close( $conn );
            return new WP_Error( 'ftp_list', __( 'Nepodarilo sa získať zoznam súborov z FTP.', 'developer-apartments' ) );
        }
        $candidates = [];
        foreach ( $list as $item ) {
            $base = basename( $item );
            if ( preg_match( '/\.(csv|xlsx)$/i', $base ) ) {
                $candidates[] = $item;
            }
        }
        if ( empty( $candidates ) ) {
            ftp_close( $conn );
            return new WP_Error( 'ftp_no_file', __( 'V zadanom adresári nie je žiadny súbor .csv alebo .xlsx.', 'developer-apartments' ) );
        }
        $newest = null;
        $newest_mtime = 0;
        foreach ( $candidates as $c ) {
            $m = @ftp_mdtm( $conn, $c );
            if ( $m > $newest_mtime ) {
                $newest_mtime = $m;
                $newest = $c;
            }
        }
        $remote_file = $newest;
    }
    $tmp = wp_tempnam( 'dev-apt-ftp' );
    $ok = @ftp_get( $conn, $tmp, $remote_file, FTP_BINARY );
    ftp_close( $conn );
    if ( ! $ok ) {
        @unlink( $tmp );
        return new WP_Error( 'ftp_get', __( 'Nepodarilo sa stiahnuť súbor z FTP.', 'developer-apartments' ) );
    }
    $name = basename( $remote_file );
    return [ 'path' => $tmp, 'name' => $name, 'remote_path' => $remote_file ];
}

/** Vymaže súbor na FTP po úspešnom importe. */
function dev_apt_ftp_delete_file( $host, $user, $pass, $remote_path ) {
    if ( ! $host || ! $remote_path ) return false;
    $conn = dev_apt_ftp_connect( $host, $user, $pass );
    if ( ! $conn ) return false;
    $ok = @ftp_delete( $conn, $remote_path );
    ftp_close( $conn );
    return $ok;
}

/** Overí, či je ID platný attachment v médiách (obrázok, PDF, atď.). */
function dev_apt_is_valid_attachment_id( $attachment_id ) {
    if ( ! $attachment_id || absint( $attachment_id ) <= 0 ) {
        return false;
    }
    $post = get_post( absint( $attachment_id ) );
    return $post && $post->post_type === 'attachment';
}
function dev_apt_find_post($row){
    $pid=0;
    if(!empty($row['apartment_code'])){
        $q=new WP_Query(['post_type'=>'apartment','meta_key'=>'apartment_code','meta_value'=>$row['apartment_code'],'posts_per_page'=>1,'fields'=>'ids']);
        if($q->have_posts()){ $pid=(int)$q->posts[0]; }
        wp_reset_postdata();
    }
    if(!$pid && !empty($row['ID'])) $pid=(int)$row['ID'];
    if(!$pid && !empty($row['slug'])) {
        $p=get_page_by_path(sanitize_title($row['slug']), OBJECT, 'apartment');
        if($p) $pid=(int)$p->ID;
    }
    return $pid;
}
function dev_apt_normalize_decimal($v){
    if ($v === '' || $v === null) return '';
    $s = str_replace([' ', "\xC2\xA0"], '', (string)$v);
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? $s : $v;
}
function dev_apt_resolve_type_term_id($row){
    if (!empty($row['apartment_type_slug'])){
        $t = get_term_by('slug', sanitize_title($row['apartment_type_slug']), 'apartment_type');
        if ($t && !is_wp_error($t)) return (int)$t->term_id;
    }
    if (!empty($row['apartment_type_name'])){
        $t = get_term_by('name', (string)$row['apartment_type_name'], 'apartment_type');
        if ($t && !is_wp_error($t)) return (int)$t->term_id;
        $terms = get_terms(['taxonomy'=>'apartment_type','hide_empty'=>false]);
        if (!is_wp_error($terms)){
            $needle = mb_strtolower((string)$row['apartment_type_name'],'UTF-8');
            foreach($terms as $it){ $nm = mb_strtolower($it->name,'UTF-8'); if (strpos($nm, $needle) === 0) return (int)$it->term_id; }
        }
    }
    return 0;
}
function dev_apt_maybe_create_post($row){
    $title = isset($row['title']) ? sanitize_text_field($row['title']) : '';
    $slug  = isset($row['slug'])  ? sanitize_title($row['slug'])      : '';
    if (!$title && $slug) { $title = ucwords(str_replace('-', ' ', $slug)); }
    if (!$slug  && $title){ $slug  = sanitize_title($title); }
    if (!$slug && !$title){ return 0; }
    $existing = get_page_by_path($slug, OBJECT, 'apartment');
    if ($existing) return (int)$existing->ID;
    $status = dev_apt_get_options()['import_create_status'] ?? 'publish';
    $pid = wp_insert_post([
        'post_type'   => 'apartment',
        'post_status' => ($status === 'draft' ? 'draft' : 'publish'),
        'post_title'  => $title ?: $slug,
        'post_name'   => $slug,
    ], true);
    if (is_wp_error($pid)) return 0;
    if (!empty($row['apartment_code'])) { update_post_meta($pid, 'apartment_code', sanitize_text_field($row['apartment_code'])); }
    return (int)$pid;
}
function dev_apt_apply_row($row,$opts){
    $pid = dev_apt_find_post($row);
    $created = false;
    $changed = array(); // pri update: zoznam zmieneních polí (názvy stĺpcov/meta)

    if(!$pid){ $pid = dev_apt_maybe_create_post($row); if($pid){ $created = true; } else { return ['status'=>'miss','msg'=>'Post not found and could not be created']; } }

    // Taxonómie – pri update meníme len ak je v riadku neprázdna hodnota
    if(!empty($row['status_slug'])){ $t=get_term_by('slug', sanitize_title($row['status_slug']), 'apartment_status'); if($t && !is_wp_error($t)){ wp_set_post_terms($pid, [$t->term_id], 'apartment_status', false); if(!$created) $changed[]='status_slug'; } }
    if(!empty($row['project_structure_slug'])){ $t=get_term_by('slug', sanitize_title($row['project_structure_slug']), 'project_structure'); if($t && !is_wp_error($t)){ wp_set_post_terms($pid, [$t->term_id], 'project_structure', false); if(!$created) $changed[]='project_structure_slug'; } }
    $type_tid = dev_apt_resolve_type_term_id($row); if ($type_tid>0){ wp_set_post_terms($pid, [$type_tid], 'apartment_type', false); if(!$created) $changed[]='apartment_type'; }

    $norm = function($k) use ($row){ return dev_apt_normalize_decimal( $row[$k] ?? '' ); };

    // Meta mapovanie: exportovaný názov stĺpca => meta kľúč
    $meta_map = [
        'apartment_code'         => ['key' => 'apartment_code',         'norm' => false ],
        'area_interior'         => ['key' => 'apt_area_interior',      'norm' => true  ],
        'area_exterior'         => ['key' => 'apt_area_exterior',      'norm' => true  ],
        'area_total'            => ['key' => 'apt_area_total',         'norm' => true  ],
        'cellar_area'           => ['key' => 'apt_cellar_area',       'norm' => true  ],
        'cellar_yes'             => ['key' => 'apt_cellar_yes',        'norm' => false ],
        'price_list'            => ['key' => 'apt_price_list',        'norm' => true  ],
        'price_discount'        => ['key' => 'apt_price_discount',     'norm' => true  ],
        'price_presale'         => ['key' => 'apt_price_presale',      'norm' => true  ],
        'rooms'                 => ['key' => 'apartment_rooms',       'norm' => false ],
        'floorplan_file_id'     => ['key' => 'apt_floorplan_file_id',  'norm' => false ],
        'floorplan_label'       => ['key' => 'apt_floorplan_label',   'norm' => false ],
        'gallery_ids'           => ['key' => 'apt_gallery_ids',        'norm' => false ],
    ];

    foreach ($meta_map as $col => $def) {
        $meta_key = $def['key'];
        $raw = isset($row[$col]) ? $row[$col] : '';
        if ($def['norm']) {
            $val = dev_apt_normalize_decimal($raw);
        } else {
            $val = $raw;
        }

        // Pri update existujúceho bytu: prázdna hodnota = nechať súčasný stav (neprepisovať)
        if (!$created && (string)$val === '') {
            continue;
        }

        if ($meta_key === 'apt_cellar_yes') {
            $val = !empty($raw) ? '1' : '';
        }
        if ($meta_key === 'apt_floorplan_file_id') {
            $aid = absint($val);
            if ($aid > 0 && ! dev_apt_is_valid_attachment_id( $aid ) ) {
                $val = ''; // neplatné ID – nepoužiť
            } else {
                $val = $aid;
            }
        }
        if ($meta_key === 'apt_gallery_ids') {
            $ids = is_string($val) ? array_filter( array_map( 'absint', explode( ',', str_replace( ' ', '', $val ) ) ) ) : array();
            $valid = array_filter( $ids, 'dev_apt_is_valid_attachment_id' );
            $val = implode( ',', $valid );
        }
        if ($meta_key === 'apartment_rooms') {
            $val = trim((string)$val);
        }
        if ($meta_key === 'apartment_code' || $meta_key === 'apt_floorplan_label') {
            $val = sanitize_text_field($val);
        }

        if ((string)$val === '' && $meta_key !== 'apt_cellar_yes') {
            if ($created) delete_post_meta($pid, $meta_key);
        } else {
            update_post_meta($pid, $meta_key, $val);
            if (!$created) $changed[] = $col;
        }
    }

    // Hlavný obrázok – len ak je v riadku uvedené platné ID attachmentu
    $feat_id = isset($row['featured_image_id']) ? absint($row['featured_image_id']) : 0;
    if ( $feat_id > 0 && dev_apt_is_valid_attachment_id( $feat_id ) ) {
        set_post_thumbnail($pid, $feat_id);
        if (!$created) $changed[] = 'featured_image_id';
    } elseif ($created) {
        delete_post_thumbnail($pid);
    }

    // Post title/slug – pri update meniť len ak sú vyplnené
    $update = [];
    if (!empty($row['title'])){ $update['post_title'] = sanitize_text_field($row['title']); if(!$created) $changed[]='title'; }
    if (!empty($row['slug'])){  $update['post_name']  = sanitize_title($row['slug']); if(!$created) $changed[]='slug'; }
    if ($update) {
        $update['ID'] = $pid;
        wp_update_post($update);
    }

    return ['status'=>'ok','msg'=> ($created ? 'created' : 'updated'), 'ID'=>$pid, 'changed'=>$changed];
}
function dev_apt_apply_row_pricing($row){
    $pid=dev_apt_find_post($row); if(!$pid){ return ['status'=>'miss','msg'=>'Post not found']; }
    // Status: 1:1 – ak je stĺpec v súbore, prepíš (aj prázdna hodnota = vymazať status)
    if (array_key_exists('status_slug', $row)) {
        $slug = is_string($row['status_slug']) ? trim($row['status_slug']) : '';
        if ($slug === '') {
            wp_set_post_terms($pid, [], 'apartment_status', false);
        } else {
            $t = get_term_by('slug', sanitize_title($slug), 'apartment_status');
            if ($t && !is_wp_error($t)) {
                wp_set_post_terms($pid, [$t->term_id], 'apartment_status', false);
            }
        }
    }
    // Ceny: 1:1 – prázdna bunka = vymazať meta
    foreach(['price_list'=>'apt_price_list','price_discount'=>'apt_price_discount','price_presale'=>'apt_price_presale'] as $src=>$mk){
        if (array_key_exists($src, $row)) {
            $v = dev_apt_normalize_decimal($row[$src]);
            if ($v === '') {
                delete_post_meta($pid, $mk);
            } else {
                update_post_meta($pid, $mk, $v);
            }
        }
    }
    return ['status'=>'ok','msg'=>'updated','ID'=>$pid];
}

/* ========================= HANDLERY ========================= */
add_action('admin_post_dev_apt_export', function(){
    if(!current_user_can('manage_options')) wp_die('forbidden');
    check_admin_referer('dev_apt_export');
    $opts=dev_apt_get_options();
    $filters = [];
    if(isset($_POST['filter_status']) && $_POST['filter_status']!=='') $filters['status_slug'] = sanitize_title($_POST['filter_status']);
    if(isset($_POST['filter_structure']) && $_POST['filter_structure']!=='') $filters['project_structure'] = intval($_POST['filter_structure']);
    $headers=dev_apt_headers(); $rows=dev_apt_collect_rows($filters);
    $fmt=isset($_POST['format']) && $_POST['format']==='xlsx' ? 'xlsx':'csv';
    if($fmt==='xlsx') dev_apt_output_xlsx($headers,$rows,'apartmany-export.xlsx'); else dev_apt_output_csv($headers,$rows,$opts['csv_delimiter'],'apartmany-export.csv');
});
add_action('admin_post_dev_apt_export_pricing', function(){
    if(!current_user_can('manage_options') && !current_user_can(DEV_APT_CAP_PRICING)) wp_die('forbidden');
    check_admin_referer('dev_apt_export_pricing');
    $opts=dev_apt_get_options();
    $filters = [];
    if(isset($_POST['filter_status']) && $_POST['filter_status']!=='') $filters['status_slug'] = sanitize_title($_POST['filter_status']);
    if(isset($_POST['filter_structure']) && $_POST['filter_structure']!=='') $filters['project_structure'] = intval($_POST['filter_structure']);
    $full = dev_apt_collect_rows($filters); $headers = dev_apt_headers_pricing(); $rows = array();
    foreach($full as $r){ $rows[] = array_intersect_key($r, array_flip($headers)); }
    $fmt=isset($_POST['format']) && $_POST['format']==='xlsx' ? 'xlsx':'csv';
    if($fmt==='xlsx') dev_apt_output_xlsx($headers,$rows,'apartmany-ceny-statusy.xlsx'); else dev_apt_output_csv($headers,$rows,$opts['csv_delimiter'],'apartmany-ceny-statusy.csv');
});
add_action('admin_post_dev_apt_import', function(){
    if(!current_user_can('manage_options')) wp_die('forbidden');
    check_admin_referer('dev_apt_import');
    if(empty($_FILES['import_file']['tmp_name'])) wp_die('No file');
    $opts=dev_apt_get_options(); $tmp=$_FILES['import_file']['tmp_name']; $name=$_FILES['import_file']['name'];
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    if(!in_array($ext, ['csv','xlsx'], true)) wp_die(__('Povolené formáty sú len .csv a .xlsx','developer-apartments'));
    if($ext==='xlsx'){ $rows=dev_apt_parse_xlsx_file($tmp); } else { $rows=dev_apt_parse_csv_file($tmp, $opts['csv_delimiter']); }
    $dry = isset($_POST['mode']) && $_POST['mode']==='dry'; $headers=dev_apt_headers();
    if($dry){ echo '<div class="wrap"><h1>Developer Apartments – Dry‑run import</h1><p>'.esc_html(count($rows)).' riadkov.</p><table class="widefat striped"><thead><tr>'; foreach($headers as $h){ echo '<th>'.esc_html($h).'</th>'; } echo '</tr></thead><tbody>'; foreach($rows as $r){ echo '<tr>'; foreach($headers as $h){ echo '<td>'.esc_html($r[$h]??'').'</td>'; } echo '</tr>'; } echo '</tbody></table><p><a class="button" href="'.esc_url(admin_url('edit.php?post_type=apartment&page=dev-apt-settings')).'">Späť</a></p></div>'; exit; }
    $u = wp_upload_dir(); $dir = trailingslashit($u['basedir']).'dev-apt-logs/'; if( ! file_exists($dir)) wp_mkdir_p($dir);
    $base = 'import-'.date('Ymd-His'); $txt=$dir.$base.'.log'; $csv=$dir.$base.'.csv';
    $fh_t=fopen($txt,'a'); $fh_c=fopen($csv,'w'); fputcsv($fh_c,['row','apartment_code','ID','slug','result','message','changed_fields'],';'); fwrite($fh_t, "Developer Apartments import\nFile: $name\nRows: ".count($rows)."\n\n");
    $ok=0;$miss=0;$i=1; foreach($rows as $r){ $res=dev_apt_apply_row($r,$opts); if($res['status']==='ok'){ $ok++; $ch = isset($res['changed']) && is_array($res['changed']) ? implode(', ',$res['changed']) : ''; fwrite($fh_t,'OK ID='.$res['ID'].' code='.(isset($r['apartment_code'])?$r['apartment_code']:'')." msg=".$res['msg'].($ch ? " changed=".$ch : '')."\n"); fputcsv($fh_c,[$i,$r['apartment_code']??'',$r['ID']??'',$r['slug']??'','OK', ($res['msg'] ?? 'updated'), $ch ],';'); } else { $miss++; fwrite($fh_t,'MISS code='.(isset($r['apartment_code'])?$r['apartment_code']:'').' slug='.(isset($r['slug'])?$r['slug']:'').' ID='.(isset($r['ID'])?$r['ID']:'')." msg=".$res['msg']."\n"); fputcsv($fh_c,[$i,$r['apartment_code']??'',$r['ID']??'',$r['slug']??'','MISS',$res['msg']??'not matched',''],';'); } $i++; }
    fclose($fh_t); fclose($fh_c); dev_apt_cache_bump(); wp_safe_redirect( add_query_arg(['post_type'=>'apartment','page'=>'dev-apt-settings','import_done'=>1,'ok'=>$ok,'miss'=>$miss,'log'=>rawurlencode(basename($txt)),'logcsv'=>rawurlencode(basename($csv))], admin_url('edit.php')) ); exit;
});
add_action('admin_post_dev_apt_import_pricing', function(){
    if(!current_user_can('manage_options') && !current_user_can(DEV_APT_CAP_PRICING)) wp_die('forbidden');
    check_admin_referer('dev_apt_import_pricing');

    $opts = dev_apt_get_options();
    $tmp_path = null;
    $name = '';

    if ( ! empty( $_POST['import_url'] ) ) {
        $fetched = dev_apt_fetch_file_from_url( $_POST['import_url'] );
        if ( is_wp_error( $fetched ) ) {
            wp_die( esc_html( $fetched->get_error_message() ) );
        }
        $tmp_path = $fetched['path'];
        $name = $fetched['name'];
    } elseif ( ! empty( $_FILES['import_file']['tmp_name'] ) ) {
        $tmp_path = $_FILES['import_file']['tmp_name'];
        $name = $_FILES['import_file']['name'];
    }

    if ( ! $tmp_path || ! is_readable( $tmp_path ) ) {
        wp_die( __( 'Nahrajte súbor alebo zadajte URL (HTTP/HTTPS).', 'developer-apartments' ) );
    }

    $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'csv', 'xlsx' ], true ) ) {
        if ( $tmp_path && strpos( $tmp_path, 'dev-apt' ) !== false ) {
            @unlink( $tmp_path );
        }
        wp_die( __( 'Povolené formáty sú len .csv a .xlsx', 'developer-apartments' ) );
    }

    if ( $ext === 'xlsx' ) {
        $rows = dev_apt_parse_xlsx_file( $tmp_path );
    } else {
        $rows = dev_apt_parse_csv_file( $tmp_path, $opts['csv_delimiter'] );
    }

    if ( $tmp_path && strpos( $tmp_path, 'dev-apt' ) !== false ) {
        @unlink( $tmp_path );
    }

    $dry = isset( $_POST['mode'] ) && $_POST['mode'] === 'commit' ? false : true;
    $headers = dev_apt_headers_pricing();
    if ( $dry ) {
        echo '<div class="wrap"><h1>Developer Apartments – Dry‑run import (Ceny & Statusy)</h1><p>' . esc_html( count( $rows ) ) . ' riadkov.</p><table class="widefat striped"><thead><tr>';
        foreach ( $headers as $h ) { echo '<th>' . esc_html( $h ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $r ) {
            echo '<tr>';
            foreach ( $headers as $h ) { echo '<td>' . esc_html( $r[ $h ] ?? '' ) . '</td>'; }
            echo '</tr>';
        }
        echo '</tbody></table><p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=apartment&page=dev-apt-settings&tab=pricing' ) ) . '">Späť</a></p></div>';
        exit;
    }

    $u = wp_upload_dir();
    $dir = trailingslashit( $u['basedir'] ) . 'dev-apt-logs/';
    if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
    $base = 'import-' . date( 'Ymd-His' );
    $txt = $dir . $base . '.log';
    $csv = $dir . $base . '.csv';
    $fh_t = fopen( $txt, 'a' );
    $fh_c = fopen( $csv, 'w' );
    fputcsv( $fh_c, [ 'row', 'apartment_code', 'ID', 'slug', 'result', 'message' ], ';' );
    fwrite( $fh_t, "Developer Apartments import (pricing)\nFile: $name\nRows: " . count( $rows ) . "\n\n" );
    $ok = 0;
    $miss = 0;
    $i = 1;
    foreach ( $rows as $r ) {
        $res = dev_apt_apply_row_pricing( $r );
        if ( $res['status'] === 'ok' ) {
            $ok++;
            fwrite( $fh_t, 'OK ID=' . $res['ID'] . ' code=' . ( isset( $r['apartment_code'] ) ? $r['apartment_code'] : '' ) . "\n" );
            fputcsv( $fh_c, [ $i, $r['apartment_code'] ?? '', $r['ID'] ?? '', $r['slug'] ?? '', 'OK', 'updated' ], ';' );
        } else {
            $miss++;
            fwrite( $fh_t, 'MISS code=' . ( isset( $r['apartment_code'] ) ? $r['apartment_code'] : '' ) . ' slug=' . ( isset( $r['slug'] ) ? $r['slug'] : '' ) . ' ID=' . ( isset( $r['ID'] ) ? $r['ID'] : '' ) . "\n" );
            fputcsv( $fh_c, [ $i, $r['apartment_code'] ?? '', $r['ID'] ?? '', $r['slug'] ?? '', 'MISS', $res['msg'] ?? 'not matched' ], ';' );
        }
        $i++;
    }
    fclose( $fh_t );
    fclose( $fh_c );
    dev_apt_cache_bump();
    wp_safe_redirect( add_query_arg( [ 'post_type' => 'apartment', 'page' => 'dev-apt-settings', 'tab' => 'pricing', 'import_done' => 1, 'ok' => $ok, 'miss' => $miss, 'log' => rawurlencode( basename( $txt ) ), 'logcsv' => rawurlencode( basename( $csv ) ) ], admin_url( 'edit.php' ) ) );
    exit;
});

/* ========================= FTP IMPORT (samostatné polia + vymazanie po importe) ========================= */
add_action( 'admin_post_dev_apt_import_pricing_ftp', function() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( DEV_APT_CAP_PRICING ) ) wp_die( 'forbidden' );
    check_admin_referer( 'dev_apt_import_pricing_ftp' );
    $ftp = dev_apt_get_ftp_options();
    if ( ! $ftp['host'] || ! $ftp['path'] ) {
        wp_safe_redirect( add_query_arg( [ 'post_type' => 'apartment', 'page' => 'dev-apt-settings', 'tab' => 'pricing', 'ftp_error' => 'missing' ], admin_url( 'edit.php' ) ) );
        exit;
    }
    $fetched = dev_apt_ftp_fetch_file( $ftp['host'], $ftp['user'], $ftp['pass'], $ftp['path'] );
    if ( is_wp_error( $fetched ) ) {
        wp_safe_redirect( add_query_arg( [ 'post_type' => 'apartment', 'page' => 'dev-apt-settings', 'tab' => 'pricing', 'ftp_error' => urlencode( $fetched->get_error_message() ) ], admin_url( 'edit.php' ) ) );
        exit;
    }
    $tmp_path = $fetched['path'];
    $name = $fetched['name'];
    $remote_path = isset( $fetched['remote_path'] ) ? $fetched['remote_path'] : '';

    $opts = dev_apt_get_options();
    $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'csv', 'xlsx' ], true ) ) {
        @unlink( $tmp_path );
        wp_safe_redirect( add_query_arg( [ 'post_type' => 'apartment', 'page' => 'dev-apt-settings', 'tab' => 'pricing', 'ftp_error' => 'invalid_format' ], admin_url( 'edit.php' ) ) );
        exit;
    }
    $rows = $ext === 'xlsx' ? dev_apt_parse_xlsx_file( $tmp_path ) : dev_apt_parse_csv_file( $tmp_path, $opts['csv_delimiter'] );
    @unlink( $tmp_path );

    $u = wp_upload_dir();
    $dir = trailingslashit( $u['basedir'] ) . 'dev-apt-logs/';
    if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
    $base = 'import-ftp-' . date( 'Ymd-His' );
    $txt = $dir . $base . '.log';
    $csv = $dir . $base . '.csv';
    $fh_t = fopen( $txt, 'a' );
    $fh_c = fopen( $csv, 'w' );
    fputcsv( $fh_c, [ 'row', 'apartment_code', 'ID', 'slug', 'result', 'message' ], ';' );
    fwrite( $fh_t, "Developer Apartments import (FTP)\nFile: $name\nRows: " . count( $rows ) . "\n\n" );
    $ok = 0;
    $miss = 0;
    $i = 1;
    foreach ( $rows as $r ) {
        $res = dev_apt_apply_row_pricing( $r );
        if ( $res['status'] === 'ok' ) {
            $ok++;
            fwrite( $fh_t, 'OK ID=' . $res['ID'] . "\n" );
            fputcsv( $fh_c, [ $i, $r['apartment_code'] ?? '', $r['ID'] ?? '', $r['slug'] ?? '', 'OK', 'updated' ], ';' );
        } else {
            $miss++;
            fwrite( $fh_t, 'MISS code=' . ( $r['apartment_code'] ?? '' ) . "\n" );
            fputcsv( $fh_c, [ $i, $r['apartment_code'] ?? '', $r['ID'] ?? '', $r['slug'] ?? '', 'MISS', $res['msg'] ?? 'not matched' ], ';' );
        }
        $i++;
    }
    fclose( $fh_t );
    fclose( $fh_c );
    dev_apt_cache_bump();

    if ( $remote_path ) {
        dev_apt_ftp_delete_file( $ftp['host'], $ftp['user'], $ftp['pass'], $remote_path );
    }

    wp_safe_redirect( add_query_arg( [ 'post_type' => 'apartment', 'page' => 'dev-apt-settings', 'tab' => 'pricing', 'import_done' => 1, 'ok' => $ok, 'miss' => $miss, 'log' => rawurlencode( basename( $txt ) ), 'logcsv' => rawurlencode( basename( $csv ) ), 'ftp_deleted' => 1 ], admin_url( 'edit.php' ) ) );
    exit;
});

/* ========================= CRON: automatická kontrola FTP ========================= */
const DEV_APT_CRON_FTP = 'dev_apt_ftp_auto_import';

add_action( DEV_APT_CRON_FTP, function() {
    $ftp = dev_apt_get_ftp_options();
    if ( empty( $ftp['auto_enabled'] ) || ! $ftp['host'] || ! $ftp['path'] ) return;
    $fetched = dev_apt_ftp_fetch_file( $ftp['host'], $ftp['user'], $ftp['pass'], $ftp['path'] );
    if ( is_wp_error( $fetched ) ) return;
    $tmp_path = $fetched['path'];
    $name = $fetched['name'];
    $remote_path = isset( $fetched['remote_path'] ) ? $fetched['remote_path'] : '';
    $opts = dev_apt_get_options();
    $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'csv', 'xlsx' ], true ) ) { @unlink( $tmp_path ); return; }
    $rows = $ext === 'xlsx' ? dev_apt_parse_xlsx_file( $tmp_path ) : dev_apt_parse_csv_file( $tmp_path, $opts['csv_delimiter'] );
    @unlink( $tmp_path );
    foreach ( $rows as $r ) { dev_apt_apply_row_pricing( $r ); }
    dev_apt_cache_bump();
    if ( $remote_path ) { dev_apt_ftp_delete_file( $ftp['host'], $ftp['user'], $ftp['pass'], $remote_path ); }
} );

function dev_apt_ftp_schedule_cron() {
    $ftp = dev_apt_get_ftp_options();
    $interval = (int) $ftp['auto_interval'];
    wp_clear_scheduled_hook( DEV_APT_CRON_FTP );
    if ( ! empty( $ftp['auto_enabled'] ) && $ftp['host'] && $ftp['path'] && $interval > 0 ) {
        wp_schedule_event( time(), dev_apt_ftp_cron_interval_name( $interval ), DEV_APT_CRON_FTP );
    }
}

function dev_apt_ftp_cron_interval_name( $seconds ) {
    $map = [ 900 => 'dev_apt_15min', 3600 => 'dev_apt_1hour', 21600 => 'dev_apt_6hour' ];
    return isset( $map[ $seconds ] ) ? $map[ $seconds ] : 'hourly';
}

add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['dev_apt_15min'] = [ 'interval' => 900, 'display' => __( 'Každých 15 minút', 'developer-apartments' ) ];
    $schedules['dev_apt_1hour'] = [ 'interval' => 3600, 'display' => __( 'Každú hodinu', 'developer-apartments' ) ];
    $schedules['dev_apt_6hour'] = [ 'interval' => 21600, 'display' => __( 'Každých 6 hodín', 'developer-apartments' ) ];
    return $schedules;
} );

add_action( 'update_option_' . DEV_APT_FTP_OPTION, 'dev_apt_ftp_schedule_cron' );
add_action( 'add_option_' . DEV_APT_FTP_OPTION, 'dev_apt_ftp_schedule_cron' );

/* ========================= CACHE BUMP ========================= */
function dev_apt_cache_bump(){ $o = dev_apt_get_options(); $o['cache_bump'] = max(1, intval($o['cache_bump'])) + 1; update_option('dev_apt_options', $o); }
add_action('save_post_apartment', function(){ dev_apt_cache_bump(); });
add_action('set_object_terms', function($object_id,$terms,$tt_ids,$taxonomy){ if( get_post_type($object_id) === 'apartment' && in_array($taxonomy, array('apartment_status','project_structure','apartment_type'), true) ) dev_apt_cache_bump(); }, 10, 4);
foreach(array('apartment_status','project_structure','apartment_type') as $tax){ add_action('created_'.$tax, 'dev_apt_cache_bump'); add_action('edited_'.$tax, 'dev_apt_cache_bump'); add_action('delete_'.$tax, 'dev_apt_cache_bump'); }

/* ========================= UI RENDER ========================= */
function dev_apt_render_settings_page() {
    $o = dev_apt_get_options();
    $delims = dev_apt_available_delimiters();
    $ttl_opts = [ 0 => 'Vypnuté (bez cache)', 600 => '10 min', 1800 => '30 min', 3600 => '1 h', 21600 => '6 h', 43200 => '12 h' ];

    $is_pricing_only = current_user_can( DEV_APT_CAP_PRICING ) && ! current_user_can( 'manage_options' );
    $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ( $is_pricing_only ? 'pricing' : 'general' );
    $tabs = [
        'general'  => __( 'Všeobecné', 'developer-apartments' ),
        'export'   => __( 'Export', 'developer-apartments' ),
        'import'   => __( 'Import', 'developer-apartments' ),
        'pricing'  => __( 'Ceny & Statusy', 'developer-apartments' ),
        'polygons' => __( 'Export polygonov', 'developer-apartments' ),
    ];
    if ( $is_pricing_only ) {
        $tabs = [ 'pricing' => $tabs['pricing'] ];
        $current_tab = 'pricing';
    } elseif ( ! isset( $tabs[ $current_tab ] ) ) {
        $current_tab = 'general';
    }

    $base_url = add_query_arg( [ 'post_type' => 'apartment', 'page' => 'dev-apt-settings' ], admin_url( 'edit.php' ) );
    ?>
<div class="wrap">
    <h1><?php echo esc_html( $is_pricing_only ? __( 'Ceny & Statusy – export/import', 'developer-apartments' ) : __( 'Developer Apartments – Nastavenia & Import/Export', 'developer-apartments' ) ); ?></h1>

    <?php if ( isset( $_GET['import_done'] ) ) : ?>
        <div class="notice notice-success"><p>
            <?php printf( __( 'Import hotový: %d aktualizovaných, %d nespárovaných.', 'developer-apartments' ), (int) $_GET['ok'], (int) $_GET['miss'] ); ?>
            <?php if ( ! empty( $_GET['ftp_deleted'] ) ) echo ' ' . __( 'Súbor bol po importe vymazaný z FTP.', 'developer-apartments' ); ?>
            <?php if ( isset( $_GET['log'] ) ) { $u = wp_upload_dir(); $url = trailingslashit( $u['baseurl'] ) . 'dev-apt-logs/' . sanitize_file_name( $_GET['log'] ); echo ' – <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . __( 'Stiahnuť log', 'developer-apartments' ) . '</a>'; } ?>
            <?php if ( isset( $_GET['logcsv'] ) ) { $u = wp_upload_dir(); $url = trailingslashit( $u['baseurl'] ) . 'dev-apt-logs/' . sanitize_file_name( $_GET['logcsv'] ); echo ' – <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">CSV log</a>'; } ?>
        </p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['ftp_error'] ) && $_GET['ftp_error'] !== '' ) : ?>
        <?php
        $err = (string) $_GET['ftp_error'];
        $err_msg = $err === 'missing' ? __( 'Vyplňte FTP server a cestu.', 'developer-apartments' ) : ( $err === 'invalid_format' ? __( 'Na FTP sa nenašiel platný súbor .csv alebo .xlsx.', 'developer-apartments' ) : esc_html( urldecode( $err ) ) );
        ?>
        <div class="notice notice-error"><p><?php echo $err_msg; ?></p></div>
    <?php endif; ?>

    <?php if ( ! $is_pricing_only ) : ?>
    <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 16px;">
        <?php foreach ( $tabs as $tab => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'tab', $tab, $base_url ) ); ?>" class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php
    if ( $current_tab === 'general' ) {
        dev_apt_render_tab_general( $o, $delims, $ttl_opts );
    } elseif ( $current_tab === 'export' ) {
        dev_apt_render_tab_export();
    } elseif ( $current_tab === 'import' ) {
        dev_apt_render_tab_import();
    } elseif ( $current_tab === 'pricing' ) {
        dev_apt_render_tab_pricing();
    } elseif ( $current_tab === 'polygons' ) {
        dev_apt_render_polygons_export_section();
    }
    ?>
</div>
<?php
}

function dev_apt_render_tab_general( $o, $delims, $ttl_opts ) {
    ?>
    <h2><?php _e( 'Shortcodes a Divi moduly', 'developer-apartments' ); ?></h2>
    <p><?php _e( 'Pre použitie v moduloch Kód (Code) alebo pri manuálnom vkladaní do stránky.', 'developer-apartments' ); ?></p>
    <table class="widefat striped" style="max-width:800px;margin-bottom:20px">
        <thead><tr><th>Shortcode</th><th><?php _e( 'Popis', 'developer-apartments' ); ?></th></tr></thead>
        <tbody>
            <tr><td><code>[dev_apt_structure_breadcrumb]</code></td><td>Breadcrumb zo štruktúry projektu</td></tr>
            <tr><td><code>[dev_apt_featured_image]</code></td><td>Náhľadový obrázok bytu (fallback pre et_pb_post_featured_image)</td></tr>
            <tr><td><code>[dev_apt_price]</code></td><td>Cena s logikou: predpredaj → zvýhodnená → list → Na vyžiadanie</td></tr>
            <tr><td><code>[dev_apt_price_ex_vat]</code></td><td>Cena bez DPH podľa nastaveného percenta DPH (predvolene 23&nbsp;%)</td></tr>
            <tr><td><code>[dev_apt_cellar]</code></td><td>Pivnica: „Áno“ alebo výmera m²</td></tr>
            <tr><td><code>[dev_apt_floor]</code></td><td>Podlažie (najnižší term project_structure)</td></tr>
            <tr><td><code>[dev_apt_single_stats]</code></td><td>Všetky štatistiky naraz. Parametre: <code>layout="blocks"</code> (predvolene) alebo <code>layout="inline"</code></td></tr>
            <tr><td><code>[dev_apt_similar]</code></td><td>Podobné byty. Parametre: <code>limit_desktop="4"</code> <code>limit_tablet="3"</code> <code>limit_mobile="2"</code></td></tr>
            <tr><td><code>[dev_floorplan_button]</code></td><td>Tlačidlo „Stiahnuť pôdorys“</td></tr>
            <tr><td><code>[dev_apartment_gallery lightbox="yes"]</code></td><td>Galéria obrázkov s Lightbox efektom</td></tr>
        </tbody>
    </table>
    <p><strong><?php _e( 'Divi moduly', 'developer-apartments' ); ?>:</strong> Mapa Bytov (v2), Tabuľka Bytov (v2) s náhľadom obrázka pri hoveri, <strong>Údaje bytu</strong>, <strong>Breadcrumb štruktúry</strong>, <strong>Náhľadový obrázok bytu</strong>, <strong>Údaje bytu (mriežka)</strong>, <strong>Galéria bytu</strong>, <strong>Podobné byty</strong>, <strong>Tlačidlo pôdorys</strong>, <strong>Tlačidlo Mám záujem</strong> – všetky s editovateľným štýlom.</p>

    <hr/>

    <form method="post" action="options.php" style="margin-top:10px;">
        <?php settings_fields( 'dev_apt_options' ); ?>
        <table class="form-table" role="presentation"><tbody>
            <tr>
                <th scope="row"><label><?php _e( 'CSV oddeľovač', 'developer-apartments' ); ?></label></th>
                <td>
                    <?php foreach ( $delims as $value => $label ) : ?>
                    <label style="display:inline-block; margin-right:12px;">
                        <input type="radio" name="dev_apt_options[csv_delimiter]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $o['csv_delimiter'], $value ); ?> />
                        <?php echo esc_html( $label ); ?>
                    </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e( 'CSV desatinné čiarky (SK)', 'developer-apartments' ); ?></label></th>
                <td><label><input type="checkbox" name="dev_apt_options[csv_decimal_comma]" value="1" <?php checked( 1, $o['csv_decimal_comma'] ); ?>>
                    <?php _e( 'Exportovať čísla v CSV s čiarkou (napr. 47,09)', 'developer-apartments' ); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e( 'Slug statusu „Voľný“', 'developer-apartments' ); ?></label></th>
                <td><input type="text" name="dev_apt_options[free_status_slug]" value="<?php echo esc_attr( $o['free_status_slug'] ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e( 'DPH pre výpočet ceny bez DPH (%)', 'developer-apartments' ); ?></label></th>
                <td>
                    <input type="number" step="0.1" min="0" max="100" name="dev_apt_options[vat_percent]" value="<?php echo esc_attr( isset( $o['vat_percent'] ) ? $o['vat_percent'] : 23 ); ?>" style="width:80px;" />
                    <p class="description"><?php _e( 'Používa sa pri výpočte poľa „Cena bez DPH“ (tabuľka a shortcode). Štandardne 23 %.', 'developer-apartments' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e( 'TTL cache (free_count)', 'developer-apartments' ); ?></label></th>
                <td>
                    <select name="dev_apt_options[cache_ttl]">
                        <?php foreach ( $ttl_opts as $sec => $lbl ) { echo '<option value="' . esc_attr( $sec ) . '" ' . selected( $o['cache_ttl'], $sec, false ) . '>' . esc_html( $lbl ) . '</option>'; } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e( 'Stav novovytvorených bytov pri importe', 'developer-apartments' ); ?></label></th>
                <td>
                    <label><input type="radio" name="dev_apt_options[import_create_status]" value="publish" <?php checked( $o['import_create_status'], 'publish' ); ?>> <?php _e( 'publish', 'developer-apartments' ); ?></label>
                    <label style="margin-left:10px;"><input type="radio" name="dev_apt_options[import_create_status]" value="draft" <?php checked( $o['import_create_status'], 'draft' ); ?>> <?php _e( 'draft', 'developer-apartments' ); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Odinštalácia – čo zmazať', 'developer-apartments' ); ?></th>
                <td>
                    <label><input type="checkbox" name="dev_apt_options[uninstall_delete_posts]" value="1" <?php checked( $o['uninstall_delete_posts'], 1 ); ?>> <?php _e( 'Byty (CPT)', 'developer-apartments' ); ?></label><br/>
                    <label><input type="checkbox" name="dev_apt_options[uninstall_delete_terms]" value="1" <?php checked( $o['uninstall_delete_terms'], 1 ); ?>> <?php _e( 'Taxonómie (termíny)', 'developer-apartments' ); ?></label><br/>
                    <label><input type="checkbox" name="dev_apt_options[uninstall_delete_term_meta]" value="1" <?php checked( $o['uninstall_delete_term_meta'], 1 ); ?>> <?php _e( 'Term meta / Mapy', 'developer-apartments' ); ?></label><br/>
                    <label><input type="checkbox" name="dev_apt_options[uninstall_delete_options]" value="1" <?php checked( $o['uninstall_delete_options'], 1 ); ?>> <?php _e( 'Nastavenia', 'developer-apartments' ); ?></label>
                </td>
            </tr>
        </tbody></table>
        <?php submit_button(); ?>
    </form>
    <?php
}

function dev_apt_render_tab_export() {
    ?>
    <h2><?php _e( 'Export', 'developer-apartments' ); ?></h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'dev_apt_export' ); ?>
        <input type="hidden" name="action" value="dev_apt_export" />
        <fieldset style="margin:8px 0;padding:8px;border:1px solid #ddd;">
            <legend><?php _e( 'Filtre', 'developer-apartments' ); ?></legend>
            <label><?php _e( 'Status', 'developer-apartments' ); ?>:
                <select name="filter_status">
                    <option value="">— <?php _e( 'všetko', 'developer-apartments' ); ?> —</option>
                    <?php $sts = get_terms( array( 'taxonomy' => 'apartment_status', 'hide_empty' => false ) ); if ( ! is_wp_error( $sts ) ) foreach ( $sts as $t ) { echo '<option value="' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</option>'; } ?>
                </select>
            </label>
            <label style="margin-left:12px;"><?php _e( 'Štruktúra', 'developer-apartments' ); ?>:
                <select name="filter_structure">
                    <option value="">— <?php _e( 'všetko', 'developer-apartments' ); ?> —</option>
                    <?php $ps = get_terms( array( 'taxonomy' => 'project_structure', 'hide_empty' => false ) ); if ( ! is_wp_error( $ps ) ) foreach ( $ps as $t ) { echo '<option value="' . esc_attr( $t->term_id ) . '">' . esc_html( $t->name ) . '</option>'; } ?>
                </select>
            </label>
        </fieldset>
        <label><input type="radio" name="format" value="csv" checked> CSV</label>
        <label style="margin-left:10px"><input type="radio" name="format" value="xlsx"> XLSX</label>
        <?php submit_button( __( 'Stiahnuť export', 'developer-apartments' ), 'primary', 'submit', false, [ 'style' => 'margin-left:10px' ] ); ?>
    </form>
    <?php
}

function dev_apt_render_tab_import() {
    ?>
    <h2><?php _e( 'Import', 'developer-apartments' ); ?></h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field( 'dev_apt_import' ); ?>
        <input type="hidden" name="action" value="dev_apt_import" />
        <input type="file" name="import_file" accept=".csv,.xlsx" required />
        <label style="margin-left:10px"><input type="radio" name="mode" value="dry" checked> <?php _e( 'Dry‑run (len náhľad)', 'developer-apartments' ); ?></label>
        <label style="margin-left:10px"><input type="radio" name="mode" value="commit"> <?php _e( 'Importovať', 'developer-apartments' ); ?></label>
        <?php submit_button( __( 'Spustiť import', 'developer-apartments' ), 'secondary', 'submit', false, [ 'style' => 'margin-left:10px' ] ); ?>
        <p class="description"><?php _e( 'XLSX import podporuje inline strings aj sharedStrings. CSV môže mať desatinné čiarky (budú znormalizované).', 'developer-apartments' ); ?></p>
    </form>
    <?php
}

function dev_apt_render_tab_pricing() {
    $base_url = add_query_arg( [ 'post_type' => 'apartment', 'page' => 'dev-apt-settings', 'tab' => 'pricing' ], admin_url( 'edit.php' ) );
    ?>
    <h2><?php _e( 'Ceny & Statusy – rýchly export/import', 'developer-apartments' ); ?></h2>
    <p class="description"><?php _e( 'Export: stĺpce apartment_code, ID, slug, title, status_slug, price_list, price_discount, price_presale. Import prepíše status a všetky tri ceny 1:1 podľa súboru (prázdna bunka = vymazať hodnotu).', 'developer-apartments' ); ?></p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
        <?php wp_nonce_field( 'dev_apt_export_pricing' ); ?>
        <input type="hidden" name="action" value="dev_apt_export_pricing" />
        <fieldset style="margin:8px 0;padding:8px;border:1px solid #ddd;">
            <legend><?php _e( 'Filtre', 'developer-apartments' ); ?></legend>
            <label><?php _e( 'Status', 'developer-apartments' ); ?>:
                <select name="filter_status">
                    <option value="">— <?php _e( 'všetko', 'developer-apartments' ); ?> —</option>
                    <?php $sts = get_terms( array( 'taxonomy' => 'apartment_status', 'hide_empty' => false ) ); if ( ! is_wp_error( $sts ) ) foreach ( $sts as $t ) { echo '<option value="' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</option>'; } ?>
                </select>
            </label>
            <label style="margin-left:12px;"><?php _e( 'Štruktúra', 'developer-apartments' ); ?>:
                <select name="filter_structure">
                    <option value="">— <?php _e( 'všetko', 'developer-apartments' ); ?> —</option>
                    <?php $ps = get_terms( array( 'taxonomy' => 'project_structure', 'hide_empty' => false ) ); if ( ! is_wp_error( $ps ) ) foreach ( $ps as $t ) { echo '<option value="' . esc_attr( $t->term_id ) . '">' . esc_html( $t->name ) . '</option>'; } ?>
                </select>
            </label>
        </fieldset>
        <label><input type="radio" name="format" value="csv" checked> CSV</label>
        <label style="margin-left:10px"><input type="radio" name="format" value="xlsx"> XLSX</label>
        <?php submit_button( __( 'Stiahnuť ceny & statusy', 'developer-apartments' ), 'primary', 'submit', false, [ 'style' => 'margin-left:10px' ] ); ?>
    </form>

    <h3><?php _e( 'Import z počítača', 'developer-apartments' ); ?></h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-bottom:16px;">
        <?php wp_nonce_field( 'dev_apt_import_pricing' ); ?>
        <input type="hidden" name="action" value="dev_apt_import_pricing" />
        <input type="file" name="import_file" accept=".csv,.xlsx" />
        <label style="margin-left:10px"><input type="radio" name="mode" value="dry" checked> <?php _e( 'Dry‑run (len náhľad)', 'developer-apartments' ); ?></label>
        <label style="margin-left:10px"><input type="radio" name="mode" value="commit"> <?php _e( 'Importovať', 'developer-apartments' ); ?></label>
        <?php submit_button( __( 'Importovať ceny & statusy', 'developer-apartments' ), 'secondary', 'submit', false, [ 'style' => 'margin-left:10px' ] ); ?>
    </form>

    <h3><?php _e( 'Import z URL (HTTP/HTTPS)', 'developer-apartments' ); ?></h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'dev_apt_import_pricing' ); ?>
        <input type="hidden" name="action" value="dev_apt_import_pricing" />
        <p>
            <label for="dev_apt_import_url"><?php _e( 'URL súboru (CSV alebo XLSX):', 'developer-apartments' ); ?></label><br/>
            <input type="url" id="dev_apt_import_url" name="import_url" class="large-text" placeholder="https://example.com/ceny.csv" style="max-width:600px;" />
        </p>
        <label><input type="radio" name="mode" value="dry" checked> <?php _e( 'Dry‑run (len náhľad)', 'developer-apartments' ); ?></label>
        <label style="margin-left:10px"><input type="radio" name="mode" value="commit"> <?php _e( 'Importovať', 'developer-apartments' ); ?></label>
        <?php submit_button( __( 'Stiahnuť a importovať', 'developer-apartments' ), 'secondary', 'submit', false, [ 'style' => 'margin-left:10px' ] ); ?>
    </form>

    <h3><?php _e( 'Import z FTP', 'developer-apartments' ); ?></h3>
    <p class="description"><?php _e( 'Samostatné polia pre pripojenie. Po úspešnom importe sa súbor na FTP vymaže. Ak je cesta adresár, použije sa najnovší súbor .csv alebo .xlsx.', 'developer-apartments' ); ?></p>
    <form method="post" action="options.php" style="margin-bottom:16px;">
        <?php settings_fields( 'dev_apt_ftp_options' ); ?>
        <?php $ftp = dev_apt_get_ftp_options(); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="dev_apt_ftp_host"><?php _e( 'FTP server', 'developer-apartments' ); ?></label></th>
                <td><input type="text" id="dev_apt_ftp_host" name="<?php echo esc_attr( DEV_APT_FTP_OPTION ); ?>[host]" value="<?php echo esc_attr( $ftp['host'] ); ?>" class="regular-text" placeholder="ftp.example.com" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="dev_apt_ftp_user"><?php _e( 'Používateľské meno', 'developer-apartments' ); ?></label></th>
                <td><input type="text" id="dev_apt_ftp_user" name="<?php echo esc_attr( DEV_APT_FTP_OPTION ); ?>[user]" value="<?php echo esc_attr( $ftp['user'] ); ?>" class="regular-text" autocomplete="off" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="dev_apt_ftp_pass"><?php _e( 'Heslo', 'developer-apartments' ); ?></label></th>
                <td><input type="password" id="dev_apt_ftp_pass" name="<?php echo esc_attr( DEV_APT_FTP_OPTION ); ?>[pass]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $ftp['pass'] !== '' ? '••••••••' : ''; ?>" />
                    <p class="description"><?php _e( 'Nechajte prázdne, ak nemeníte.', 'developer-apartments' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="dev_apt_ftp_path"><?php _e( 'Cesta', 'developer-apartments' ); ?></label></th>
                <td>
                    <input type="text" id="dev_apt_ftp_path" name="<?php echo esc_attr( DEV_APT_FTP_OPTION ); ?>[path]" value="<?php echo esc_attr( $ftp['path'] ); ?>" class="large-text" placeholder="/export/ alebo /export/ceny.csv" style="max-width:400px;" />
                    <p class="description"><?php _e( 'Adresár (nájde najnovší .csv/.xlsx) alebo celá cesta k súboru.', 'developer-apartments' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Automatická kontrola', 'developer-apartments' ); ?></th>
                <td>
                    <label><input type="checkbox" name="<?php echo esc_attr( DEV_APT_FTP_OPTION ); ?>[auto_enabled]" value="1" <?php checked( $ftp['auto_enabled'], 1 ); ?> /> <?php _e( 'Zapnúť automatickú kontrolu: nájde nový súbor, importuje a vymaže z FTP', 'developer-apartments' ); ?></label>
                    <p class="description" style="margin-top:6px;">
                        <label><?php _e( 'Interval:', 'developer-apartments' ); ?></label>
                        <select name="<?php echo esc_attr( DEV_APT_FTP_OPTION ); ?>[auto_interval]">
                            <option value="900" <?php selected( $ftp['auto_interval'], 900 ); ?>><?php _e( 'Každých 15 minút', 'developer-apartments' ); ?></option>
                            <option value="3600" <?php selected( $ftp['auto_interval'], 3600 ); ?>><?php _e( 'Každú hodinu', 'developer-apartments' ); ?></option>
                            <option value="21600" <?php selected( $ftp['auto_interval'], 21600 ); ?>><?php _e( 'Každých 6 hodín', 'developer-apartments' ); ?></option>
                        </select>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Uložiť FTP nastavenia', 'developer-apartments' ), 'primary', 'submit', false ); ?>
    </form>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
        <?php wp_nonce_field( 'dev_apt_import_pricing_ftp' ); ?>
        <input type="hidden" name="action" value="dev_apt_import_pricing_ftp" />
        <?php submit_button( __( 'Skontrolovať FTP a importovať teraz', 'developer-apartments' ), 'secondary', 'submit', false ); ?>
        <span class="description" style="margin-left:8px;"><?php _e( 'Stiahne súbor z FTP, spracuje import a súbor na FTP vymaže.', 'developer-apartments' ); ?></span>
    </form>
    <?php
}
