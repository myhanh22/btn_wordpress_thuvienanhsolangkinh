<?php
/** A fixed identity photo, independent of the gallery's publication order. */
// Ảnh nhận diện/banner cố định, không phụ thuộc thứ tự đăng hoặc hiển thị tác phẩm trong thư viện.
if ( ! defined( 'ABSPATH' ) ) { exit; } // Chặn truy cập trực tiếp vào tệp; chỉ cho phép chạy trong WordPress.


// Làm sạch và xác thực ID ảnh được chọn trong phần tùy biến giao diện.
function lk_sanitize_hero_image( $value ) {
    $id = absint( $value ); // Ép giá trị về số nguyên không âm để tránh ID không hợp lệ.
    return $id && wp_attachment_is_image( $id ) ? $id : 0; // Chỉ trả về ID nếu đó thực sự là tệp đính kèm dạng ảnh; nếu không trả về 0.
}


// Lấy thông tin ảnh banner; ưu tiên ảnh quản trị viên đã chọn, nếu không thì dùng ảnh mẫu.
function lk_hero_image() {
    // Khai báo thông tin ảnh mặc định dùng khi chưa chọn ảnh banner hợp lệ.
    $hero = array(
        'src' => get_stylesheet_directory_uri() . '/assets/demo-watermarked/phong-canh-que-15.jpg', // Đường dẫn ảnh mẫu trong thư mục theme/child theme.
        'title' => 'Lối về bình yên', 'alt' => 'Lối về bình yên', 'srcset' => '', // Tiêu đề, văn bản thay thế và các kích thước ảnh responsive mặc định.
    );
    // Lấy ID ảnh đã lưu trong Theme Customizer rồi kiểm tra lại tính hợp lệ.
    $id = lk_sanitize_hero_image( get_theme_mod( 'lk_hero_image', 0 ) );
    // Lấy URL ảnh ở kích thước large; nếu không có ảnh hợp lệ, kết quả là false.
    $src = $id ? wp_get_attachment_image_url( $id, 'large' ) : false;
    if ( $src ) {
        $hero['src'] = $src; // Thay ảnh mẫu bằng URL ảnh banner đã chọn.
        $hero['title'] = get_the_title( $id ) ?: 'Lăng Kính'; // Dùng tiêu đề của ảnh; nếu trống thì dùng tên website.
        $hero['alt'] = get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: $hero['title']; // Lấy văn bản alt trong Media; nếu chưa có thì dùng tiêu đề.
        $hero['srcset'] = wp_get_attachment_image_srcset( $id, 'large' ); // Tạo danh sách kích thước ảnh để trình duyệt chọn ảnh phù hợp màn hình.
    }
    // Lấy chú thích banner do quản trị viên nhập và loại bỏ khoảng trắng ở hai đầu.
    $caption = trim( get_theme_mod( 'lk_hero_caption', '' ) );
    if ( '' !== $caption ) { $hero['title'] = $caption; } // Nếu có chú thích riêng, dùng chú thích làm title hiển thị.
    return $hero; // Trả về mảng thông tin ảnh banner cho phần giao diện sử dụng.
}


// Đăng ký khu vực và các trường cấu hình ảnh banner trong WordPress Theme Customizer.
add_action( 'customize_register', function ( $customize ) {
    // Tạo một nhóm tùy chỉnh riêng cho ảnh banner ở trang chủ.
    $customize->add_section( 'lk_hero', array(
        'title' => 'Lăng Kính — Ảnh banner', 'priority' => 35, // Tên khu vực và thứ tự hiển thị trong Customizer.
        'description' => 'Ảnh nhận diện cố định ở đầu trang chủ. Đăng tác phẩm mới không thay ảnh này.', // Giải thích mục đích của ảnh banner.
    ) );
    // Đăng ký setting lưu ID ảnh banner được chọn.
    $customize->add_setting( 'lk_hero_image', array(
        'default' => 0, 'sanitize_callback' => 'lk_sanitize_hero_image', // Giá trị mặc định là 0; luôn xác thực ID ảnh trước khi lưu.
        'capability' => 'edit_theme_options', 'transport' => 'refresh', // Chỉ người có quyền sửa giao diện được thay đổi; tải lại bản xem trước khi cập nhật.
    ) );
    // Tạo bộ điều khiển chọn ảnh từ Media Library hoặc tải ảnh mới lên.
    $customize->add_control( new WP_Customize_Media_Control( $customize, 'lk_hero_image', array(
        'section' => 'lk_hero', 'label' => 'Ảnh banner cố định', 'mime_type' => 'image', // Chỉ cho phép chọn tệp thuộc loại ảnh.
        'description' => 'Chọn ảnh trong Media hoặc tải lên. Khi chưa chọn hoặc đã xóa ảnh, dùng ảnh mẫu “Lối về bình yên” có watermark.', // Mô tả cách hoạt động khi chưa có ảnh được chọn.
    ) ) );
    // Đăng ký setting lưu chú thích riêng cho ảnh banner.
    $customize->add_setting( 'lk_hero_caption', array(
        'default' => '', 'sanitize_callback' => 'sanitize_text_field', // Mặc định để trống; làm sạch nội dung văn bản trước khi lưu.
        'capability' => 'edit_theme_options', 'transport' => 'refresh', // Giới hạn quyền chỉnh sửa và cập nhật bản xem trước.
    ) );
    // Tạo ô nhập chú thích ảnh banner trong Customizer.
    $customize->add_control( 'lk_hero_caption', array(
        'section' => 'lk_hero', 'label' => 'Chú thích ảnh banner', 'type' => 'text', // Ô nhập văn bản một dòng.
        'description' => 'Để trống để dùng tên ảnh. Văn bản thay thế lấy từ ảnh trong Media.', // Nếu không nhập chú thích, tiêu đề ảnh được sử dụng; alt vẫn lấy từ Media.
    ) );
} ); // Kết thúc hàm đăng ký các thiết lập tùy biến.
