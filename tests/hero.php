<?php
/** Uses existing fixtures on a disposable WordPress installation only. */
if ( '1' !== getenv( 'LK_DISPOSABLE_WP' ) || ! getenv( 'LK_WP_ROOT' ) ) { exit( 1 ); }
set_exception_handler( function ( $error ) { fwrite( STDERR, (string) $error . PHP_EOL ); exit( 1 ); } );
require rtrim( getenv( 'LK_WP_ROOT' ), '/' ) . '/wp-load.php';
function lk_hero_check( $ok, $message ) {
    if ( ! $ok ) { throw new RuntimeException( $message ); }
    echo "PASS: $message\n";
}
$mods = get_theme_mods();
remove_theme_mod( 'lk_hero_image' );
remove_theme_mod( 'lk_hero_caption' );
$default = lk_hero_image();
lk_hero_check( str_ends_with( $default['src'], '/demo-watermarked/phong-canh-que-15.jpg' ), 'Default banner is fixed, watermarked identity photo' );
$attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1 ) );
if ( ! $attachments ) { throw new RuntimeException( 'Run browser fixtures first to create an image.' ); }
$id = $attachments[0]->ID;
$work = wp_insert_post( array( 'post_type' => 'lk_photo', 'post_status' => 'publish', 'post_title' => 'Hero independence fixture' ) );
try {
    set_post_thumbnail( $work, $id );
    lk_hero_check( lk_hero_image() === $default, 'Publishing a new work never replaces default banner' );
    set_theme_mod( 'lk_hero_image', $id );
    set_theme_mod( 'lk_hero_caption', 'Lăng Kính cố định' );
    $custom = lk_hero_image();
    lk_hero_check( $custom['src'] === wp_get_attachment_image_url( $id, 'large' ) && 'Lăng Kính cố định' === $custom['title'], 'Custom photo and caption are used' );
    wp_update_post( array( 'ID' => $work, 'post_title' => 'Newer work' ) );
    lk_hero_check( lk_hero_image() === $custom, 'Editing a gallery work never changes selected banner' );
    lk_hero_check( lk_sanitize_hero_image( $work ) === 0, 'Non-image selection is rejected' );
    set_theme_mod( 'lk_hero_image', 99999999 );
    remove_theme_mod( 'lk_hero_caption' );
    lk_hero_check( lk_hero_image() === $default, 'Missing/deleted image falls back safely' );
    wp_set_current_user( username_exists( 'lk_fixture_administrator' ) );
    require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
    $manager = new WP_Customize_Manager( array( 'settings_previewed' => false ) );
    $manager->register_controls();
    do_action( 'customize_register', $manager );
    lk_hero_check( $manager->get_control( 'lk_hero_image' ) instanceof WP_Customize_Media_Control, 'Customizer exposes media image selector' );
    lk_hero_check( 'edit_theme_options' === $manager->get_setting( 'lk_hero_image' )->capability, 'Only theme managers can change banner' );
    lk_hero_check( $manager->get_control( 'lk_hero_caption' ) !== null, 'Customizer exposes editable caption' );
} finally {
    wp_delete_post( $work, true );
    update_option( 'theme_mods_' . get_option( 'stylesheet' ), $mods ?: array() );
}