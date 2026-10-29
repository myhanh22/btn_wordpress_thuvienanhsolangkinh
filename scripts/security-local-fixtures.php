<?php
/** Private fixture helper. No credentials or login slug are stored in this repo. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'LK_ALLOW_LOCAL_SECURITY_FIXTURES' ) || ! getenv( 'LK_SECURITY_PRIVATE' ) ) { exit( 1 ); }
$private_file = getenv( 'LK_SECURITY_PRIVATE' );
$c = json_decode( file_get_contents( $private_file ), true );
if ( ! str_ends_with( parse_url( $c['base'], PHP_URL_HOST ), '.local' ) ) { throw new RuntimeException( 'Local scope only.' ); }
define( 'DISABLE_WP_CRON', true );
require $c['site_root'] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$command = $argv[1] ?? 'status';
if ( 'lock-snapshot' === $command ) {
    foreach ( array( 'lockouts', 'retries', 'retries_valid' ) as $key ) { $c['lock_snapshot'][ $key ] = get_option( 'limit_login_' . $key, array() ); }
    file_put_contents( $private_file, json_encode( $c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    echo "Lock state snapshotted privately\n";
} elseif ( 'unlock' === $command ) {
    if ( ! isset( $c['lock_snapshot'] ) ) { throw new RuntimeException( 'Missing pre-test lock state.' ); }
    foreach ( $c['lock_snapshot'] as $key => $value ) { update_option( 'limit_login_' . $key, $value ); }
    echo "Restored pre-test lock state\n";
} elseif ( 'lock-status' === $command ) {
    echo wp_json_encode( array( 'active_locks' => count( get_option( 'limit_login_lockouts', array() ) ), 'threshold' => get_option( 'limit_login_allowed_retries' ), 'duration_seconds' => get_option( 'limit_login_lockout_duration' ) ) );
} elseif ( in_array( $command, array( 'reset-posts', 'cleanup' ), true ) ) {
    foreach ( $c['users'] as $info ) {
        $user = get_user_by( 'id', $info['id'] );
        if ( ! $user ) { continue; }
        if ( $user->user_login !== $info['login'] || ! str_starts_with( $user->user_login, 'lk_sec_' . $c['suffix'] . '_' ) ) { throw new RuntimeException( 'Fixture identity mismatch.' ); }
        $posts = get_posts( array( 'post_type' => array( 'lk_photo', 'attachment' ), 'post_status' => array_values( get_post_stati() ), 'author' => $user->ID, 'posts_per_page' => -1 ) );
        foreach ( $posts as $post ) {
            if ( 'attachment' === $post->post_type ) { wp_delete_attachment( $post->ID, true ); }
            else { wp_delete_post( $post->ID, true ); }
        }
        if ( 'cleanup' === $command ) { wp_delete_user( $user->ID ); }
    }
    if ( 'reset-posts' === $command ) { echo "Deleted only disposable fixture works/media\n"; return; }
    global $wpdb;
    $post_ids = implode( ',', array_map( 'absint', $c['original_posts'] ) );
    $user_ids = implode( ',', array_map( 'absint', $c['original_users'] ) );
    $posts_hash = hash( 'sha256', json_encode( $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE ID IN ($post_ids) ORDER BY ID", ARRAY_A ) ) );
    $featured_hash = hash( 'sha256', json_encode( $wpdb->get_results( "SELECT post_id,meta_value FROM $wpdb->postmeta WHERE meta_key='_thumbnail_id' AND post_id IN ($post_ids) ORDER BY post_id", ARRAY_A ) ) );
    $pass_hash = hash( 'sha256', json_encode( $wpdb->get_results( "SELECT ID,user_pass FROM $wpdb->users WHERE ID IN ($user_ids) ORDER BY ID", ARRAY_A ) ) );
    echo wp_json_encode( array( 'original_posts_unchanged' => $posts_hash === $c['original_posts_hash'], 'original_featured_images_unchanged' => $featured_hash === $c['original_featured_hash'], 'original_password_hashes_unchanged' => $pass_hash === $c['original_admin_pass_hash'], 'theme_mods_unchanged' => get_theme_mods() === $c['theme_mods'], 'remaining_fixture_users' => count( get_users( array( 'search' => 'lk_sec_' . $c['suffix'] . '_*' ) ) ) ), JSON_PRETTY_PRINT );
} else {
    echo wp_json_encode( array( 'hardening_active' => is_plugin_active( 'lang-kinh-security/lang-kinh-security.php' ), 'wordfence_active' => is_plugin_active( 'wordfence/wordfence.php' ), 'wordfence_key_present' => class_exists( 'wfConfig' ) && '' !== wfConfig::get( 'apiKey', '' ), 'wordfence_onboarding_prompt' => class_exists( 'wfConfig' ) ? (bool) wfConfig::get( 'touppPromptNeeded' ) : null, 'wordfence_login_security' => class_exists( 'wfConfig' ) ? (bool) wfConfig::get( 'loginSecurityEnabled' ) : null, 'file_editor_disabled' => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT, 'converter_active' => is_plugin_active( 'webp-converter-for-media/webp-converter-for-media.php' ), 'config_readable_by_php' => is_readable( ABSPATH . 'wp-config.php' ) ), JSON_PRETTY_PRINT );
}
