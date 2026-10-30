<?php
/** Build shipped demo derivatives using Image Watermark; never alter originals. */
if ( PHP_SAPI !== 'cli' || ! getenv( 'LK_WP_ROOT' ) ) {
    exit( "CLI only. Set LK_WP_ROOT to WordPress with Image Watermark installed/active.\n" );
}
define( 'DISABLE_WP_CRON', true );
require rtrim( getenv( 'LK_WP_ROOT' ), '/' ) . '/wp-load.php';
if ( ! function_exists( 'Image_Watermark' ) ) { throw new RuntimeException( 'Activate Image Watermark first.' ); }
$plugin = Image_Watermark();
$plugin->check_extensions();
$options = $plugin->options;
$options['watermark_image'] = array_merge( $options['watermark_image'], array(
    'type' => 'text', 'text_string' => '© Lăng Kính · Nhóm 1',
    'text_font' => 'OpenSans-Regular.ttf', 'text_color' => '#ffffff',
    'position' => 'bottom_right', 'rotation' => 0, 'transparent' => 65,
    'offset_unit' => 'pixels', 'offset_width' => 18, 'offset_height' => 18,
    'watermark_size_type' => 0, 'quality' => 90,
) );
$source_dir = dirname( __DIR__ ) . '/astra-child/assets/demo';
$output_dir = dirname( __DIR__ ) . '/astra-child/assets/demo-watermarked';
$files = glob( $source_dir . '/*.jpg' );
if ( count( $files ) !== 30 ) { throw new RuntimeException( 'Expected exactly 30 original demo photos.' ); }
if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0755, true ) ) { throw new RuntimeException( 'Cannot create output directory.' ); }
$manifest = array( 'text' => $options['watermark_image']['text_string'], 'font' => 'OpenSans-Regular.ttf', 'opacity' => 65, 'photos' => array() );
foreach ( $files as $source ) {
    $filename = basename( $source );
    $output = $output_dir . '/' . $filename;
    $temp = $output_dir . '/.' . $filename . '.tmp.jpg';
    $size = getimagesize( $source );
    $options['watermark_image']['text_size'] = max( 20, min( 32, (int) round( $size[0] * 0.025 ) ) );
    if ( ! copy( $source, $temp ) || ! $plugin->get_upload_handler()->do_watermark( 0, $temp, 'full', wp_upload_dir(), array(), $options ) ) {
        if ( is_file( $temp ) ) { unlink( $temp ); }
        throw new RuntimeException( 'Watermark failed: ' . $filename );
    }
    if ( getimagesize( $temp ) !== $size || hash_file( 'sha256', $temp ) === hash_file( 'sha256', $source ) ) {
        unlink( $temp );
        throw new RuntimeException( 'Invalid derivative: ' . $filename );
    }
    if ( ! rename( $temp, $output ) ) { throw new RuntimeException( 'Cannot save derivative: ' . $filename ); }
    $manifest['photos'][ $filename ] = array( 'original_sha256' => hash_file( 'sha256', $source ), 'watermarked_sha256' => hash_file( 'sha256', $output ) );
    echo 'Watermarked: ', $filename, PHP_EOL;
}
file_put_contents( $output_dir . '/manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
echo "Built 30 derivatives. Originals and WordPress attachments unchanged.\n";