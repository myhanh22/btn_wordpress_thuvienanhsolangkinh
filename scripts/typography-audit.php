<?php
/** Read-only: wp eval-file scripts/typography-audit.php. No credentials exported. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$keys = array( 'stylesheet', 'template', 'blog_charset' );
$out = array();
foreach ( $keys as $key ) { $out[$key] = get_option( $key ); }
$out['theme_directory'] = get_stylesheet_directory();
$out['theme_version'] = wp_get_theme()->get( 'Version' );
$out['additional_css'] = wp_get_custom_css();
$out['astra_typography'] = array_filter( (array) get_option( 'astra-settings', array() ), function ( $key ) {
    return (bool) preg_match( '/font|line-height|letter-spacing|text-transform/', $key );
}, ARRAY_FILTER_USE_KEY );
$out['files_sha256'] = array();
foreach ( array( 'style.css', 'functions.php', 'front-page.php', 'js/custom.js', 'inc/hero.php', 'assets/fonts/lora/lora-normal.woff2', 'assets/fonts/lora/lora-italic.woff2' ) as $file ) {
    if ( ! is_file( get_stylesheet_directory() . '/' . $file ) ) {
        $out['files_sha256'][$file] = null; // Older deployments may not include these fonts.
        continue;
    }
    $out['files_sha256'][$file] = hash_file( 'sha256', get_stylesheet_directory() . '/' . $file );
}
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";