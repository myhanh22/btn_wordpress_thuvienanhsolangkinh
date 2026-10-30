<?php

/** Login-protected frontend submission form for the lk_photo post type. */

// Chặn truy cập trực tiếp vào tệp PHP; chỉ cho phép chạy trong môi trường WordPress.
if ( ! defined( 'ABSPATH' ) ) {

    exit;

}

// Đăng ký shortcode [lk_upload_form] để hiển thị biểu mẫu gửi tác phẩm.
add_shortcode( 'lk_upload_form', 'lk_upload_form_shortcode' );

// Khi kích hoạt theme, tự tạo trang "Đăng tác phẩm" nếu trang này chưa tồn tại.
add_action( 'after_switch_theme', 'lk_create_upload_page' );

/** Resolve the real permalink, including plain-permalink installations. */

// Lấy URL trang gửi tác phẩm theo slug; có URL dự phòng nếu chưa tìm thấy trang.
function lk_upload_page_url() {

    $page = get_page_by_path( 'dang-tac-pham' );

    return $page ? get_permalink( $page ) : home_url( '/dang-tac-pham/' );

}

/** Create a missing page without altering an existing user's page. */

// Tạo trang chứa shortcode; không ghi đè nội dung trang đã có của người dùng.
function lk_create_upload_page() {

    if ( get_page_by_path( 'dang-tac-pham' ) ) {

        return true;

    }

    $page_id = wp_insert_post(

        array(

            'post_type'    => 'page',

            'post_status'  => 'publish',

            'post_title'   => 'Đăng tác phẩm',

            'post_name'    => 'dang-tac-pham',

            'post_content' => '[lk_upload_form]',

        ),

        true

    );

    return ! is_wp_error( $page_id ) && $page_id > 0;

}

/** Resolve a validated choice; create a new term only after the photo is saved. */

// Kiểm tra chủ đề được chọn hoặc tạo chủ đề mới (chỉ tạo sau khi ảnh đã lưu thành công).
function lk_submission_topic( $choice, $new_name, $create = false ) {

    // Người dùng chọn "Khác": kiểm tra tên, dùng lại chủ đề trùng hoặc tạo mới khi được yêu cầu.
    if ( 'other' === $choice ) {

        if ( '' === $new_name || mb_strlen( $new_name ) > 80 ) {

            return new WP_Error( 'topic_name', 'Hãy nhập tên chủ đề mới, tối đa 80 ký tự.' );

        }

        $existing = term_exists( $new_name, 'lk_topic' );

        if ( $existing ) { return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing ); }

        if ( ! $create ) { return 0; }

        $term = wp_insert_term( $new_name, 'lk_topic' );

        if ( is_wp_error( $term ) ) {

            return 'term_exists' === $term->get_error_code() ? (int) $term->get_error_data() : $term;

        }

        return (int) $term['term_id'];

    }

    // Giá trị rỗng/0 tương ứng với chủ đề mặc định "Tự do".