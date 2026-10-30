<?php
/** Run through `wp eval-file`; only the sanitized demo database is accepted. */
if ( PHP_SAPI !== 'cli' || 1 !== (int) get_option( 'lk_handover_demo' ) ) {
    throw new RuntimeException( 'Only a restored handover demo may use this script.' );
}
$repo = realpath( getenv( 'LK_REPO_ROOT' ) ?: dirname( __DIR__ ) );
$manifest = json_decode( file_get_contents( $repo . '/database/media-manifest.json' ), true, 512, JSON_THROW_ON_ERROR );
require_once ABSPATH . 'wp-admin/includes/image.php';
foreach ( $manifest as $item ) {
    if ( ! preg_match( '#^gallery-demo/demo-[0-9]+\.jpg$#', $item['destination'] ) ||
        ! str_starts_with( $item['source'], 'astra-child/assets/demo-watermarked/' ) || str_contains( $item['source'], '..' ) ) {
        throw new RuntimeException( 'Invalid media manifest path.' );
    }
    $source = $repo . '/' . $item['source'];
    if ( hash_file( 'sha256', $source ) !== $item['source_sha256'] ) { throw new RuntimeException( 'Source image checksum mismatch.' ); }
    $id = (int) $item['attachment_id'];
    if ( 'attachment' !== get_post_type( $id ) ) { throw new RuntimeException( 'Missing demo attachment.' ); }
    $target = wp_upload_dir()['basedir'] . '/' . $item['destination'];
    wp_mkdir_p( dirname( $target ) );
    if ( ! copy( $source, $target ) ) { throw new RuntimeException( 'Cannot restore media.' ); }
    chmod( $target, 0644 );
    update_attached_file( $id, $target );
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $target ) );
}
echo 'Restored ' . count( $manifest ) . " demo attachments and generated image sizes.\n";