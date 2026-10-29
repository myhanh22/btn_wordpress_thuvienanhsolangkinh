<?php
/** Isolated boundary checks; HTTP overflow is additionally tested on LocalWP. */
define( 'ABSPATH', __DIR__ );
function add_shortcode() {}
function add_action() {}
require __DIR__ . '/../astra-child/inc/upload-form.php';
foreach ( array( '12M' => 12582912, '512k' => 524288, '1G' => 1073741824, '1024' => 1024, '0' => 0, '-1' => 0, ' 2m ' => 2097152, 'invalid' => null ) as $input => $expected ) {
    if ( lk_ini_size_bytes( $input ) !== ( is_int( $expected ) && $expected > 0 ? (float) $expected : $expected ) ) {
        throw new RuntimeException( 'Unexpected ini size conversion: ' . $input );
    }
}
class WP_Post { public $post_content = '[lk_upload_form]'; }
function get_queried_object() { return new WP_Post(); }
function is_singular() { return true; }
function has_shortcode() { return true; }
function nocache_headers() {}
function is_user_logged_in() { return true; }
function current_user_can() { return true; }
function get_permalink() { return '/upload/'; }
function sanitize_text_field( $v ) { return $v; }
function wp_unslash( $v ) { return $v; }
function status_header( $v ) { $GLOBALS['test_status'] = $v; }
function size_format( $v ) { return (string) $v; }
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_LENGTH'] = (string) ( lk_ini_size_bytes( ini_get( 'post_max_size' ) ) + 1 );
$_POST = $_FILES = array();
lk_handle_upload();
if ( 413 !== ( $GLOBALS['test_status'] ?? null ) || ! str_contains( $GLOBALS['lk_upload_error'] ?? '', 'PHP đã hủy' ) ) {
    throw new RuntimeException( 'Empty PHP POST/FILES must report overflow before checking submit/nonce.' );
}
echo "PASS: PHP quantities and empty POST/FILES overflow boundary\n";