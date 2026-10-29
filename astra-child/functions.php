<?php
/** Lăng Kính — Astra child theme for the Group 1 gallery. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'after_setup_theme', function () {
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'title-tag' );
    add_image_size( 'lk-gallery', 800, 1000, false );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'astra-parent', get_template_directory_uri() . '/style.css', array(), wp_get_theme( 'astra' )->get( 'Version' ) );
    wp_enqueue_style( 'lk-style', get_stylesheet_uri(), array( 'astra-parent' ), filemtime( get_stylesheet_directory() . '/style.css' ) );
    wp_enqueue_script( 'lk-gallery', get_stylesheet_directory_uri() . '/js/custom.js', array(), filemtime( get_stylesheet_directory() . '/js/custom.js' ), true );
} );

add_action( 'init', function () {
    register_post_type( 'lk_photo', array(
        'labels' => array( 'name' => 'Tác phẩm', 'singular_name' => 'Tác phẩm', 'add_new_item' => 'Thêm tác phẩm', 'edit_item' => 'Sửa tác phẩm', 'featured_image' => 'Ảnh tác phẩm', 'set_featured_image' => 'Chọn ảnh tác phẩm' ),
        'capability_type' => array( 'lk_photo', 'lk_photos' ),
        'map_meta_cap' => true,
        'taxonomies' => array( 'lk_topic' ),
        'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-format-gallery',
        'supports' => array( 'title', 'editor', 'thumbnail', 'author', 'excerpt' ),
        'rewrite' => array( 'slug' => 'tac-pham' ),
    ) );
    register_taxonomy( 'lk_topic', 'lk_photo', array(
        'labels' => array( 'name' => 'Chủ đề / Album', 'singular_name' => 'Chủ đề', 'add_new_item' => 'Thêm chủ đề' ),
        'hierarchical' => true, 'show_in_rest' => true, 'public' => true,
        'rewrite' => array( 'slug' => 'chu-de' ),
    ) );
} );

/** One catalog for bundled photos and the submission topic choices. */
function lk_demo_photos() {
    return array(
        array('phong-canh-que-15','Lối về bình yên','doi-song','Đời sống'),
        array('co-gai-che-o-08','Một thoáng dịu dàng','chan-dung','Chân dung'),
        array('hoang-hon-12','Chạm vào hoàng hôn','phong-canh','Phong cảnh'),
        array('hoa-sen-26','Hương mùa hạ','thien-nhien','Thiên nhiên'),
        array('ganh-hang-rong-22','Nhịp phố','doi-song','Đời sống'),
        array('con-meo-14','Người bạn nhỏ','dong-vat','Động vật'),
        array('duong-la-do-11','Hẹn với mùa thu','phong-canh','Phong cảnh'),
        array('be-gai-ha-giang-19','Nắng trên miền đá','chan-dung','Chân dung'),
        array('danh-ca-24','Ngày trở về','doi-song','Đời sống'),
        array('hoa-tulip-17','Bản hòa ca sắc màu','thien-nhien','Thiên nhiên'),
        array('sa-mac-06','Dấu chân trên cát','phong-canh','Phong cảnh'),
        array('tach-tra-09','Một buổi sáng chậm','tinh-vat','Tĩnh vật'),
        array('mua-thu-02','Khoảng trời xanh','phong-canh','Phong cảnh'),
        array('hoa-huong-duong-03','Phía mặt trời','thien-nhien','Thiên nhiên'),
        array('cho-con-04','Nụ cười bé nhỏ','dong-vat','Động vật'),
        array('doi-mat-05','Điều chưa kể','chan-dung','Chân dung'),
        array('phong-canh-chau-phi-01','Miền đất xa','phong-canh','Phong cảnh'),
        array('banh-ngot-07','Chút ngọt ngào','tinh-vat','Tĩnh vật'),
        array('be-gai-10','Vòng hoa tuổi thơ','chan-dung','Chân dung'),
        array('con-bo-13','Đồng cỏ yên lành','dong-vat','Động vật'),
        array('cuoi-ngua-16','Tự do trên đồng xanh','doi-song','Đời sống'),
        array('canh-dong-hoa-18','Mùa hoa ở lại','thien-nhien','Thiên nhiên'),
        array('ho-guom-20','Hà Nội trong sương','phong-canh','Phong cảnh'),
        array('cay-lua-21','Mùa gieo hy vọng','doi-song','Đời sống'),
        array('ham-ca-map-23','Góc phố thân quen','doi-song','Đời sống'),
        array('chan-trau-25','Tuổi thơ trên lưng trâu','doi-song','Đời sống'),
        array('co-gai-doi-mu-27','Dịu dàng trong nắng','chan-dung','Chân dung'),
        array('lang-bac-28','Một ngày ở Ba Đình','phong-canh','Phong cảnh'),
        array('cho-da-lat-29','Hẹn ở Đà Lạt','doi-song','Đời sống'),
        array('ban-hoa-30','Chở mùa qua phố','doi-song','Đời sống'),
    );
}

/** Published works with valid images first, followed by the 30 bundled demos. */
function lk_gallery_items() {
    $items = array();
    $photos = get_posts( array( 'post_type' => 'lk_photo', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
    foreach ( $photos as $photo ) {
        $id = get_post_thumbnail_id( $photo->ID );
        if ( ! $id ) { continue; }
        $src = wp_get_attachment_image_url( $id, 'lk-gallery' );
        $full = wp_get_attachment_image_url( $id, 'full' );
        if ( ! $src || ! $full ) { continue; }
        $terms = get_the_terms( $photo->ID, 'lk_topic' );
        $topics = array();
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) { $topics[ $term->slug ] = $term->name; }
        }
        if ( ! $topics ) { $topics = array( 'tu-do' => 'Tự do' ); }
        $items[] = array(
            'title' => get_the_title( $photo ), 'author' => get_the_author_meta( 'display_name', $photo->post_author ),
            'topics' => $topics, 'src' => $src,
            'full' => $full,
            'srcset' => wp_get_attachment_image_srcset( $id, 'lk-gallery' ),
            'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title( $photo ),
        );
    }
    foreach ( lk_demo_photos() as $row ) {
        $term = get_term_by( 'slug', $row[2], 'lk_topic' ) ?: get_term_by( 'name', $row[3], 'lk_topic' );
        $topic_slug = $term ? $term->slug : $row[2];
        $topic_name = $term ? $term->name : $row[3];
        $url = get_stylesheet_directory_uri() . '/assets/demo-watermarked/' . $row[0] . '.jpg';
        $items[] = array( 'title' => $row[1], 'author' => 'Bộ sưu tập mẫu', 'topics' => array( $topic_slug => $topic_name ), 'src' => $url, 'full' => $url, 'srcset' => '', 'alt' => $row[1] );
    }
    return $items;
}

require_once get_stylesheet_directory() . '/inc/upload-form.php';
require_once get_stylesheet_directory() . '/inc/hero.php';

/** Versioned setup also runs when files are updated on an already active theme. */
add_action('init', 'lk_upgrade_theme', 20);
function lk_upgrade_theme() {
    if ( '2.4.0' === get_option( 'lk_setup_version' ) ) {
        return;
    }
    $submit_caps = array( 'edit_lk_photos', 'delete_lk_photos' );
    $review_caps = array_merge( $submit_caps, array(
        'publish_lk_photos', 'edit_others_lk_photos', 'delete_others_lk_photos',
        'read_private_lk_photos', 'edit_private_lk_photos', 'delete_private_lk_photos',
        'edit_published_lk_photos', 'delete_published_lk_photos',
    ) );
    foreach ( array( 'administrator', 'editor', 'author' ) as $role_name ) {
        $role = get_role( $role_name );
        if ( ! $role ) { continue; }
        if ( 'author' === $role_name ) {
            foreach ( array_diff( $review_caps, $submit_caps ) as $cap ) {
                $role->remove_cap( $cap );
            }
        }
        foreach ( 'author' === $role_name ? $submit_caps : $review_caps as $cap ) {
            $role->add_cap( $cap );
        }
    }
    if ( lk_seed_gallery_topics() && lk_create_upload_page() ) {
        flush_rewrite_rules( false );
        update_option( 'lk_setup_version', '2.4.0' );
    }
}

/** Add the existing demo topics once, preserving names and terms already on site. */
function lk_seed_gallery_topics() {
    $topics = array( 'tu-do' => 'Tự do' );
    foreach ( lk_demo_photos() as $row ) {
        $topics[ $row[2] ] = $row[3];
    }
    foreach ( $topics as $slug => $name ) {
        if ( get_term_by( 'slug', $slug, 'lk_topic' ) || get_term_by( 'name', $name, 'lk_topic' ) ) { continue; }
        $result = wp_insert_term( $name, 'lk_topic', array( 'slug' => $slug ) );
        if ( is_wp_error( $result ) && 'term_exists' !== $result->get_error_code() ) { return false; }
    }
    return true;
}
