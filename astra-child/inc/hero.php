<?php
/** A fixed identity photo, independent of the gallery's publication order. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function lk_sanitize_hero_image( $value ) {
    $id = absint( $value );
    return $id && wp_attachment_is_image( $id ) ? $id : 0;
}

function lk_hero_image() {
    $hero = array(
        'src' => get_stylesheet_directory_uri() . '/assets/demo-watermarked/phong-canh-que-15.jpg',
        'title' => 'Lối về bình yên', 'alt' => 'Lối về bình yên', 'srcset' => '',
    );
    $id = lk_sanitize_hero_image( get_theme_mod( 'lk_hero_image', 0 ) );
    $src = $id ? wp_get_attachment_image_url( $id, 'large' ) : false;
    if ( $src ) {
        $hero['src'] = $src;
        $hero['title'] = get_the_title( $id ) ?: 'Lăng Kính';
        $hero['alt'] = get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: $hero['title'];
        $hero['srcset'] = wp_get_attachment_image_srcset( $id, 'large' );
    }
    $caption = trim( get_theme_mod( 'lk_hero_caption', '' ) );
    if ( '' !== $caption ) { $hero['title'] = $caption; }
    return $hero;
}

add_action( 'customize_register', function ( $customize ) {
    $customize->add_section( 'lk_hero', array(
        'title' => 'Lăng Kính — Ảnh banner', 'priority' => 35,
        'description' => 'Ảnh nhận diện cố định ở đầu trang chủ. Đăng tác phẩm mới không thay ảnh này.',
    ) );
    $customize->add_setting( 'lk_hero_image', array(
        'default' => 0, 'sanitize_callback' => 'lk_sanitize_hero_image',
        'capability' => 'edit_theme_options', 'transport' => 'refresh',
    ) );
    $customize->add_control( new WP_Customize_Media_Control( $customize, 'lk_hero_image', array(
        'section' => 'lk_hero', 'label' => 'Ảnh banner cố định', 'mime_type' => 'image',
        'description' => 'Chọn ảnh trong Media hoặc tải lên. Khi chưa chọn hoặc đã xóa ảnh, dùng ảnh mẫu “Lối về bình yên” có watermark.',
    ) ) );
    $customize->add_setting( 'lk_hero_caption', array(
        'default' => '', 'sanitize_callback' => 'sanitize_text_field',
        'capability' => 'edit_theme_options', 'transport' => 'refresh',
    ) );
    $customize->add_control( 'lk_hero_caption', array(
        'section' => 'lk_hero', 'label' => 'Chú thích ảnh banner', 'type' => 'text',
        'description' => 'Để trống để dùng tên ảnh. Văn bản thay thế lấy từ ảnh trong Media.',
    ) );
} );
