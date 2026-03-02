<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function dev_get_field( $post_id, $key ) {
  if ( function_exists('get_field') ) {
    $value = get_field( $key, $post_id );
    return $value !== false ? $value : null;
  }
  $value = get_post_meta( $post_id, $key, true );
  return $value !== '' ? $value : null;
}
function dev_get_status( $post_id ) {
  $terms = wp_get_post_terms( $post_id, 'apartment_status', [ 'number' => 1 ] );
  if ( is_wp_error($terms) || empty($terms) ) return null;
  return $terms[0];
}

/** Štýly pre .dev-apt-stats-blocks – výstup len raz na stránku (modul + shortcode) */
function dev_apt_stats_blocks_style_once() {
  static $done = false;
  if ( $done ) return '';
  $done = true;
  return '<style>.dev-apt-stats-blocks{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start}.dev-apt-stat-block{padding:12px 16px;background:#fff;border-radius:6px;min-width:120px}.dev-apt-stat-prominent{flex:1;min-width:200px}.dev-apt-stat-prominent .dev-apt-stat-value{font-size:1.25em;font-weight:700}.dev-apt-stat-label{font-size:.85em;opacity:.85;margin-bottom:4px}.dev-apt-stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;flex:1;min-width:0}@media(max-width:767px){.dev-apt-stats-blocks{flex-direction:column}.dev-apt-stat-grid{grid-template-columns:repeat(2,1fr)!important}}</style>';
}
