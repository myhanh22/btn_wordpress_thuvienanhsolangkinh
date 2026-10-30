<?php
/** One-off handover preparation. CLI-only, only the explicitly named disposable clone. */
if ( PHP_SAPI !== 'cli' || 'lk_handover_demo' !== DB_NAME || '1' !== getenv( 'LK_PREPARE_DEMO_CLONE' ) ) {
    throw new RuntimeException( 'This command is restricted to the disposable handover clone.' );
}
global $wpdb;
$core = array( 'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts', 'term_relationships', 'term_taxonomy', 'termmeta', 'terms', 'usermeta', 'users' );
foreach ( $wpdb->get_col( 'SHOW TABLES' ) as $table ) {
    if ( ! in_array( $table, array_map( fn( $name ) => $wpdb->prefix . $name, $core ), true ) ) {
        $wpdb->query( 'DROP TABLE `' . str_replace( '`', '``', $table ) . '`' );
    }
}
$keep_options = array( 'siteurl', 'home', 'blogname', 'blogdescription', 'blog_public', 'db_version', 'initial_db_version', 'template', 'stylesheet', 'current_theme', 'permalink_structure', 'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page', 'timezone_string', 'gmt_offset', 'date_format', 'time_format', 'start_of_week', 'WPLANG', 'charset', 'blog_charset', 'html_type', 'uploads_use_yearmonth_folders', 'wp_user_roles', 'theme_mods_astra-child', 'lk_setup_version' );
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" ) as $name ) {
    if ( ! in_array( $name, $keep_options, true ) ) { delete_option( $name ); }
}
update_option( 'blogname', 'Lăng Kính — Demo Nhóm 1' );
update_option( 'blogdescription', 'Bản demo bàn giao đã ẩn danh; dữ liệu bổ sung chỉ ở clone.' );
update_option( 'admin_email', 'admin@example.invalid' );
update_option( 'users_can_register', 0 );
update_option( 'active_plugins', array( 'lang-kinh-security/lang-kinh-security.php' ) );
update_option( 'lk_handover_demo', 1 );
update_option( 'upload_path', '' );
update_option( 'upload_url_path', '' );
$wpdb->query( "DELETE FROM {$wpdb->usermeta}" );
$users = $wpdb->get_col( "SELECT ID FROM {$wpdb->users} ORDER BY ID" );
foreach ( $users as $i => $id ) {
    $wpdb->update( $wpdb->users, array( 'user_login' => 'demo_author_' . ( $i + 1 ), 'user_pass' => '!HANDOVER_DISABLED!', 'user_nicename' => 'demo-author-' . ( $i + 1 ), 'user_email' => 'author' . ( $i + 1 ) . '@example.invalid', 'user_url' => '', 'user_registered' => '2026-10-01 00:00:00', 'user_activation_key' => '', 'display_name' => 'Tác giả demo ' . ( $i + 1 ) ), array( 'ID' => $id ) );
    clean_user_cache( $id );
    ( new WP_User( $id ) )->set_role( 'author' );
}
$wpdb->query( "DELETE FROM {$wpdb->comments}" );
$wpdb->query( "DELETE FROM {$wpdb->commentmeta}" );
$wpdb->query( "DELETE FROM {$wpdb->links}" );
$allowed = array( 'page', 'lk_photo', 'attachment' );
foreach ( $wpdb->get_results( "SELECT ID,post_type,post_status FROM {$wpdb->posts}" ) as $post ) {
    if ( ! in_array( $post->post_type, $allowed, true ) || 'auto-draft' === $post->post_status ) {
        $wpdb->delete( $wpdb->posts, array( 'ID' => $post->ID ) );
        $wpdb->delete( $wpdb->postmeta, array( 'post_id' => $post->ID ) );
        $wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $post->ID ) );
        continue;
    }
    $title = 'attachment' === $post->post_type ? 'Ảnh demo ' . $post->ID : ( 'lk_photo' === $post->post_type ? 'Tác phẩm lưu trữ demo ' . $post->ID : 'Trang demo ' . $post->ID );
    $page = get_post( $post->ID );
    $content = 'page' === $post->post_type && has_shortcode( $page->post_content, 'lk_upload_form' ) ? '[lk_upload_form]' : '';
    $is_upload_page = '[lk_upload_form]' === $content;
    $wpdb->update( $wpdb->posts, array( 'post_title' => $is_upload_page ? 'Đăng tác phẩm' : $title, 'post_content' => $content, 'post_excerpt' => '', 'post_content_filtered' => '', 'post_password' => '', 'post_name' => $is_upload_page ? 'dang-tac-pham' : 'demo-' . $post->ID, 'guid' => 'http://lang-kinh-demo.invalid/?p=' . $post->ID ), array( 'ID' => $post->ID ) );
}
// Keep only public relationship/image metadata, never edit locks, author PII or plugin logs.
$keep_meta = array( '_thumbnail_id', '_wp_attached_file', '_wp_attachment_metadata', '_wp_attachment_image_alt', '_wp_trash_meta_status', '_wp_trash_meta_time' );
foreach ( $wpdb->get_results( "SELECT meta_id,meta_key FROM {$wpdb->postmeta}" ) as $m ) {
    if ( ! in_array( $m->meta_key, $keep_meta, true ) ) { $wpdb->delete( $wpdb->postmeta, array( 'meta_id' => $m->meta_id ) ); }
}
$wpdb->query( "DELETE FROM {$wpdb->termmeta}" );
// Banner remains fixed; only clone settings are normalized to the bundled default.
update_option( 'theme_mods_astra-child', array() );
wp_cache_flush();
require_once ABSPATH . 'wp-admin/includes/image.php';
$manifest = array();
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1 ) ) as $attachment ) {
    $relative = 'gallery-demo/demo-' . $attachment->ID . '.jpg';
    $source_relative = 'astra-child/assets/demo-watermarked/hoa-sen-26.jpg';
    $source = rtrim( getenv( 'LK_REPO_ROOT' ), '/' ) . '/' . $source_relative;
    $file = wp_upload_dir()['basedir'] . '/' . $relative;
    wp_mkdir_p( dirname( $file ) );
    copy( $source, $file );
    update_attached_file( $attachment->ID, $file );
    wp_update_attachment_metadata( $attachment->ID, wp_generate_attachment_metadata( $attachment->ID, $file ) );
    wp_update_post( array( 'ID' => $attachment->ID, 'post_mime_type' => 'image/jpeg', 'post_content' => '', 'post_excerpt' => '' ) );
    update_post_meta( $attachment->ID, '_wp_attachment_image_alt', 'Ảnh minh họa demo đã watermark' );
    $manifest[] = array( 'attachment_id' => $attachment->ID, 'source' => $source_relative, 'destination' => $relative, 'source_sha256' => hash_file( 'sha256', $source ) );
}
$author = (int) $users[0];
$attachment_id = (int) $manifest[0]['attachment_id'];
foreach ( array( 'publish', 'pending' ) as $status ) {
    $id = wp_insert_post( array( 'post_type' => 'lk_photo', 'post_status' => $status, 'post_title' => 'Tác phẩm demo bổ sung trên clone — ' . $status, 'post_content' => 'Dữ liệu minh họa bàn giao, không phải tác phẩm đã xuất bản trên site gốc.', 'post_author' => $author ) );
    set_post_thumbnail( $id, $attachment_id );
    wp_set_object_terms( $id, array( (int) get_term_by( 'slug', 'tu-do', 'lk_topic' )->term_id ), 'lk_topic' );
}
lk_create_upload_page();
// Remove unrelated categories/navigation but retain the gallery topic vocabulary/relationships.
foreach ( $wpdb->get_results( "SELECT term_taxonomy_id,term_id,taxonomy FROM {$wpdb->term_taxonomy}" ) as $term ) {
    if ( 'lk_topic' !== $term->taxonomy ) {
        $wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
        $wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
        $wpdb->delete( $wpdb->terms, array( 'term_id' => $term->term_id ) );
    }
}
// Public catalog topics remain; other user-entered topic labels are anonymized.
$catalog_slugs = array( 'tu-do', 'doi-song', 'chan-dung', 'phong-canh', 'thien-nhien', 'dong-vat', 'tinh-vat' );
foreach ( get_terms( array( 'taxonomy' => 'lk_topic', 'hide_empty' => false ) ) as $term ) {
    if ( ! in_array( $term->slug, $catalog_slugs, true ) ) {
        wp_update_term( $term->term_id, 'lk_topic', array( 'name' => 'Chủ đề demo ' . $term->term_id, 'slug' => 'chu-de-demo-' . $term->term_id, 'description' => '' ) );
    }
}
foreach ( $wpdb->get_results( "SELECT ID,post_parent FROM {$wpdb->posts} WHERE post_parent<>0" ) as $post ) {
    if ( ! get_post( $post->post_parent ) ) { $wpdb->update( $wpdb->posts, array( 'post_parent' => 0 ), array( 'ID' => $post->ID ) ); }
}
wp_update_term_count_now( $wpdb->get_col( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy='lk_topic'" ), 'lk_topic' );
file_put_contents( getenv( 'LK_MANIFEST_OUTPUT' ), wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo "Clone cleaned; disabled pseudonymous accounts, public metadata, two explicitly added clone demo works.\n";