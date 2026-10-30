<?php
/** Lăng Kính — Astra child theme for the Group 1 gallery. */
// Ngăn truy cập trực tiếp vào tệp; mã chỉ được thực thi trong môi trường WordPress.
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Khai báo các tính năng và kích thước ảnh mà giao diện con sử dụng.
add_action( 'after_setup_theme', function () {
    // Cho phép bài viết/tác phẩm sử dụng ảnh đại diện (Featured Image).
    add_theme_support( 'post-thumbnails' );
    // Để WordPress tự quản lý thẻ tiêu đề <title> của trang.
    add_theme_support( 'title-tag' );
    // Đăng ký kích thước ảnh riêng cho thư viện: rộng 800, cao 1000; không cắt ảnh theo khung cố định.
    add_image_size( 'lk-gallery', 800, 1000, false );
} );

// Nạp CSS và JavaScript của theme cha và theme con ở giao diện người dùng.
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'astra-parent', get_template_directory_uri() . '/style.css', array(), wp_get_theme( 'astra' )->get( 'Version' ) );
    wp_enqueue_style( 'lk-style', get_stylesheet_uri(), array( 'astra-parent' ), filemtime( get_stylesheet_directory() . '/style.css' ) );
    wp_enqueue_script( 'lk-gallery', get_stylesheet_directory_uri() . '/js/custom.js', array(), filemtime( get_stylesheet_directory() . '/js/custom.js' ), true );
} );

// Đăng ký loại nội dung tùy chỉnh và phân loại chủ đề trong WordPress.
add_action( 'init', function () {
    // Tạo Custom Post Type lk_photo để quản lý các tác phẩm ảnh độc lập với bài viết thông thường.
    register_post_type( 'lk_photo', array(
        'labels' => array( 'name' => 'Tác phẩm', 'singular_name' => 'Tác phẩm', 'add_new_item' => 'Thêm tác phẩm', 'edit_item' => 'Sửa tác phẩm', 'featured_image' => 'Ảnh tác phẩm', 'set_featured_image' => 'Chọn ảnh tác phẩm' ),
        // Dùng bộ quyền riêng cho tác phẩm, giúp phân biệt quyền gửi và quyền duyệt.
        'capability_type' => array( 'lk_photo', 'lk_photos' ),
        // Để WordPress ánh xạ quyền meta-capability sang quyền người dùng phù hợp.
        'map_meta_cap' => true,
        // Liên kết loại nội dung tác phẩm với phân loại chủ đề/album lk_topic.
        'taxonomies' => array( 'lk_topic' ),
        // Cho phép hiển thị công khai, hỗ trợ REST API và đặt biểu tượng trong menu quản trị.
        'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-format-gallery',
        // Bật các trường: tiêu đề, nội dung, ảnh đại diện, tác giả và trích đoạn.
        'supports' => array( 'title', 'editor', 'thumbnail', 'author', 'excerpt' ),
        // Đường dẫn thân thiện cho từng tác phẩm có dạng /tac-pham/ten-tac-pham/.
        'rewrite' => array( 'slug' => 'tac-pham' ),
    ) );
    // Tạo taxonomy lk_topic và gắn taxonomy này với loại nội dung lk_photo.
    register_taxonomy( 'lk_topic', 'lk_photo', array(
        'labels' => array( 'name' => 'Chủ đề / Album', 'singular_name' => 'Chủ đề', 'add_new_item' => 'Thêm chủ đề' ),
        // Cho phép phân cấp chủ đề cha/con, công khai và hỗ trợ REST API.
        'hierarchical' => true, 'show_in_rest' => true, 'public' => true,
        // Đường dẫn trang chủ đề có slug /chu-de/.
        'rewrite' => array( 'slug' => 'chu-de' ),
    ) );
} );

/** One catalog for bundled photos and the submission topic choices. */
// Danh mục dữ liệu tập trung cho 30 ảnh mẫu và các lựa chọn chủ đề khi gửi tác phẩm.
/** One catalog for bundled photos and the submission topic choices. */
// Trả về danh sách ảnh mẫu kèm tên file, tiêu đề, slug chủ đề và tên chủ đề.
function lk_demo_photos() {
    // Mỗi phần tử gồm: tên file ảnh, tiêu đề hiển thị, mã chủ đề và tên chủ đề.
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
// Lấy tác phẩm đã xuất bản có ảnh hợp lệ trước, sau đó nối thêm 30 ảnh mẫu đi kèm theme.
/** Published works with valid images first, followed by the 30 bundled demos. */
// Gom dữ liệu ảnh từ tác phẩm do người dùng đăng và bộ ảnh mẫu thành một danh sách thống nhất.
function lk_gallery_items() {
    // Mảng kết quả chứa dữ liệu ảnh để giao diện thư viện sử dụng.