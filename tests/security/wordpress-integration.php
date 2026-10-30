<?php
/** Run only against a disposable WordPress installation; see tests/README.md. */
if ( '1' !== getenv( 'LK_DISPOSABLE_WP' ) || ! getenv( 'LK_WP_ROOT' ) || ! getenv( 'LK_TEST_PASSWORD' ) ) {
    fwrite( STDERR, "Set LK_DISPOSABLE_WP=1, LK_WP_ROOT and LK_TEST_PASSWORD for a disposable installation.\n" );
    exit( 1 );
}
require rtrim( getenv( 'LK_WP_ROOT' ), '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
function lk_test_check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    echo "PASS: $message\n";
}
$users = array();
foreach ( array( 'administrator', 'author', 'editor', 'subscriber' ) as $role ) {
    $login = 'lk_fixture_' . $role;
    $id = username_exists( $login );
    if ( ! $id ) {
        $id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => getenv( 'LK_TEST_PASSWORD' ), 'role' => $role ) );
    }
    lk_test_check( ! is_wp_error( $id ), "Fixture user $role" );
    $users[ $role ] = $id;
}
$author = get_user_by( 'id', $users['author'] );
foreach ( array( 'edit_lk_photos', 'upload_files', 'publish_posts' ) as $cap ) {
    lk_test_check( $author->has_cap( $cap ), "Author retains $cap" );
}
lk_test_check( ! $author->has_cap( 'publish_lk_photos' ), 'Author cannot publish gallery works' );
foreach ( array( 'administrator', 'editor' ) as $role ) {
    lk_test_check( user_can( $users[ $role ], 'publish_lk_photos' ), "$role can publish gallery works" );
}
// Simulate an update of an already active theme with a missing upload page.
$page = get_page_by_path( 'dang-tac-pham' );
if ( $page ) { wp_delete_post( $page->ID, true ); }
update_option( 'lk_setup_version', '2.2.0' );
lk_upgrade_theme();
$page = get_page_by_path( 'dang-tac-pham' );
lk_test_check( $page && '[lk_upload_form]' === $page->post_content, 'Upgrade creates missing upload page' );
lk_upgrade_theme();
lk_create_upload_page();
lk_test_check( $page->ID === get_page_by_path( 'dang-tac-pham' )->ID, 'Repeated setup does not duplicate page' );
wp_update_post( array( 'ID' => $page->ID, 'post_content' => '[lk_upload_form]\nUser content stays.' ) );
lk_create_upload_page();
lk_test_check( str_contains( get_post( $page->ID )->post_content, 'User content stays.' ), 'Existing page content is preserved' );
wp_set_current_user( $users['author'] );
$server = rest_get_server();
foreach ( array( 'publish', 'future', 'private' ) as $status ) {
    $request = new WP_REST_Request( 'POST', '/wp/v2/lk_photo' );
    $request->set_body_params( array( 'title' => 'Forbidden ' . $status, 'status' => $status, 'date' => '2035-01-01T12:00:00' ) );
    lk_test_check( 403 === $server->dispatch( $request )->get_status(), "Author REST creation refuses $status" );
}
$pending = wp_insert_post( array( 'post_type' => 'lk_photo', 'post_status' => 'pending', 'post_title' => 'Pending fixture', 'post_author' => $users['author'] ) );
lk_test_check( user_can( $users['author'], 'edit_post', $pending ), 'Author can edit own pending work' );
lk_test_check( ! user_can( $users['subscriber'], 'edit_post', $pending ), 'Subscriber cannot edit work' );
lk_test_check( user_can( $users['editor'], 'edit_post', $pending ) && user_can( $users['editor'], 'read_post', $pending ), 'Editor can view and edit pending work' );
$request = new WP_REST_Request( 'POST', '/wp/v2/lk_photo/' . $pending );
$request->set_body_params( array( 'status' => 'publish' ) );
lk_test_check( 403 === $server->dispatch( $request )->get_status(), 'Author REST promotion refuses publish' );
wp_delete_post( $pending, true );
wp_set_current_user( 0 );
$request = new WP_REST_Request( 'POST', '/wp/v2/lk_photo' );
$request->set_body_params( array( 'title' => 'Guest', 'status' => 'pending' ) );
lk_test_check( 401 === $server->dispatch( $request )->get_status(), 'Guest REST creation is refused' );
$items = lk_gallery_items();
lk_test_check( 30 === count( $items ), 'Empty gallery contains exactly 30 demos' );
foreach ( array( 'draft', 'pending', 'publish' ) as $status ) {
    $id = wp_insert_post( array( 'post_type' => 'lk_photo', 'post_status' => $status, 'post_title' => 'No image ' . $status ) );
    lk_test_check( 30 === count( lk_gallery_items() ), "$status without thumbnail is excluded" );
    wp_delete_post( $id, true );
}
$term = wp_insert_term( 'Kiểm thử', 'lk_topic', array( 'slug' => 'kiem-thu' ) );
lk_test_check( ! is_wp_error( $term ), 'Topic fixture created' );
flush_rewrite_rules( false );
echo "READY: HTTP tests can use lk_fixture_author, lk_fixture_editor, lk_fixture_subscriber\n";