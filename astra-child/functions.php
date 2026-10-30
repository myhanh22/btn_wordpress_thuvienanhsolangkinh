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
    $items = array();
    $photos = get_posts( array( 'post_type' => 'lk_photo', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
    // Duyệt từng tác phẩm đã xuất bản để lấy ảnh và thông tin liên quan.
    foreach ( $photos as $photo ) {
        // Lấy ID ảnh đại diện của tác phẩm.
        $id = get_post_thumbnail_id( $photo->ID );
        // Bỏ qua tác phẩm chưa có ảnh đại diện.
        if ( ! $id ) { continue; }
        // Lấy URL ảnh ở kích thước dành cho lưới thư viện.
        $src = wp_get_attachment_image_url( $id, 'lk-gallery' );
        // Lấy URL ảnh kích thước đầy đủ để xem ảnh lớn.
        $full = wp_get_attachment_image_url( $id, 'full' );
        // Bỏ qua nếu không lấy được URL ảnh thu nhỏ hoặc ảnh đầy đủ.
        if ( ! $src || ! $full ) { continue; }
        // Lấy các chủ đề/album đã gán cho tác phẩm.
        $terms = get_the_terms( $photo->ID, 'lk_topic' );
        // Chuẩn bị mảng lưu slug và tên của các chủ đề.
        $topics = array();
        // Chỉ duyệt danh sách chủ đề khi kết quả hợp lệ và không phải WP_Error.
        if ( $terms && ! is_wp_error( $terms ) ) {
            // Lưu tên chủ đề theo khóa là slug để tiện lọc và đối chiếu ở frontend.
            foreach ( $terms as $term ) { $topics[ $term->slug ] = $term->name; }
        }
        // Nếu tác phẩm chưa được gán chủ đề, xếp vào nhóm mặc định Tự do.
        if ( ! $topics ) { $topics = array( 'tu-do' => 'Tự do' ); }
        // Thêm thông tin tác phẩm vào danh sách dữ liệu thư viện.
        $items[] = array(
            'title' => get_the_title( $photo ), 'author' => get_the_author_meta( 'display_name', $photo->post_author ),
            'topics' => $topics, 'src' => $src,
            'full' => $full,
            // Cung cấp các phiên bản kích thước ảnh để trình duyệt chọn theo màn hình.
            'srcset' => wp_get_attachment_image_srcset( $id, 'lk-gallery' ),
            // Lấy văn bản thay thế ảnh; nếu chưa có thì dùng tiêu đề tác phẩm.
            'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title( $photo ),
        );
    }
    // Thu thập các slug và tên chủ đề xuất hiện trong danh sách ảnh mẫu.
    foreach ( lk_demo_photos() as $row ) {
        $term = get_term_by( 'slug', $row[2], 'lk_topic' ) ?: get_term_by( 'name', $row[3], 'lk_topic' );
        // Ưu tiên slug thực tế của chủ đề; nếu chưa tồn tại thì dùng slug trong dữ liệu mẫu.
        $topic_slug = $term ? $term->slug : $row[2];
        // Ưu tiên tên chủ đề trong cơ sở dữ liệu; nếu chưa có thì dùng tên trong dữ liệu mẫu.
        $topic_name = $term ? $term->name : $row[3];
        $url = get_stylesheet_directory_uri() . '/assets/demo-watermarked/' . $row[0] . '.jpg';
        $items[] = array( 'title' => $row[1], 'author' => 'Bộ sưu tập mẫu', 'topics' => array( $topic_slug => $topic_name ), 'src' => $url, 'full' => $url, 'srcset' => '', 'alt' => $row[1] );
    }
    // Trả về toàn bộ dữ liệu ảnh để các phần giao diện gọi và hiển thị.
    return $items;
}

// Nạp module biểu mẫu cho phép người dùng gửi tác phẩm từ giao diện website.
require_once get_stylesheet_directory() . '/inc/upload-form.php';
// Nạp module quản lý ảnh banner cố định của trang chủ.
require_once get_stylesheet_directory() . '/inc/hero.php';

/** Versioned setup also runs when files are updated on an already active theme. */
// Chạy cập nhật cấu hình theo phiên bản, kể cả khi theme đã kích hoạt từ trước.
/** Versioned setup also runs when files are updated on an already active theme. */
// Gọi hàm nâng cấp ở hook init với độ ưu tiên 20 (sau các tác vụ init ưu tiên mặc định).
add_action('init', 'lk_upgrade_theme', 20);
// Thiết lập quyền người dùng, chủ đề mẫu và trang gửi tác phẩm khi nâng cấp theme.
function lk_upgrade_theme() {
    // Nếu cấu hình phiên bản 2.4.0 đã hoàn tất thì không chạy lại để tránh thao tác lặp.
    if ( '2.4.0' === get_option( 'lk_setup_version' ) ) {
        return;
    }
    // Các quyền cơ bản dành cho người gửi tác phẩm: chỉnh sửa và xóa tác phẩm của mình.
    $submit_caps = array( 'edit_lk_photos', 'delete_lk_photos' );
    // Quyền của nhóm duyệt: gồm quyền gửi cơ bản và các quyền quản lý/duyệt tác phẩm khác.
    $review_caps = array_merge( $submit_caps, array(
        'publish_lk_photos', 'edit_others_lk_photos', 'delete_others_lk_photos',
        'read_private_lk_photos', 'edit_private_lk_photos', 'delete_private_lk_photos',
        'edit_published_lk_photos', 'delete_published_lk_photos',
    ) );
    // Áp dụng cấu hình quyền cho ba vai trò Administrator, Editor và Author.
    foreach ( array( 'administrator', 'editor', 'author' ) as $role_name ) {
        // Lấy đối tượng vai trò hiện tại để thêm hoặc gỡ quyền.
        $role = get_role( $role_name );
        // Bỏ qua nếu vai trò không tồn tại trên website.
        if ( ! $role ) { continue; }
        // Với Author, gỡ các quyền duyệt/quản lý tác phẩm của người khác.
        if ( 'author' === $role_name ) {
            // Lấy phần quyền có trong nhóm duyệt nhưng không thuộc nhóm quyền gửi cơ bản.
            foreach ( array_diff( $review_caps, $submit_caps ) as $cap ) {
                // Gỡ từng quyền không dành cho Author.
                $role->remove_cap( $cap );
            }
        }
        // Author chỉ nhận quyền gửi; Administrator và Editor nhận đầy đủ quyền duyệt.
        foreach ( 'author' === $role_name ? $submit_caps : $review_caps as $cap ) {
            // Thêm quyền tương ứng cho vai trò hiện tại.
            $role->add_cap( $cap );
        }
    }
    // Chỉ đánh dấu hoàn tất sau khi tạo/cập nhật chủ đề mẫu và trang gửi tác phẩm thành công.
    if ( lk_seed_gallery_topics() && lk_create_upload_page() ) {
        // Làm mới quy tắc đường dẫn permalink để các URL loại nội dung/taxonomy hoạt động.
        flush_rewrite_rules( false );
        // Lưu phiên bản thiết lập để lần sau không chạy lại quy trình nâng cấp.
        update_option( 'lk_setup_version', '2.4.0' );
    }
}

/** Add the existing demo topics once, preserving names and terms already on site. */
// Tạo các chủ đề mẫu còn thiếu; không ghi đè hoặc tạo trùng chủ đề đã có.
/** Add the existing demo topics once, preserving names and terms already on site. */
// Khởi tạo các chủ đề cần thiết cho thư viện ảnh mẫu.
function lk_seed_gallery_topics() {
    // Luôn có chủ đề mặc định Tự do.
    $topics = array( 'tu-do' => 'Tự do' );
    // Thu thập các slug và tên chủ đề xuất hiện trong danh sách ảnh mẫu.
    foreach ( lk_demo_photos() as $row ) {
        // Lưu chủ đề theo slug để tự loại bỏ các chủ đề trùng slug.
        $topics[ $row[2] ] = $row[3];
    }
    // Kiểm tra và tạo lần lượt từng chủ đề còn thiếu.
    foreach ( $topics as $slug => $name ) {
        // Nếu đã tồn tại theo slug hoặc tên thì giữ nguyên và chuyển sang chủ đề tiếp theo.
        if ( get_term_by( 'slug', $slug, 'lk_topic' ) || get_term_by( 'name', $name, 'lk_topic' ) ) { continue; }
        // Tạo chủ đề mới với tên và slug đã khai báo.
        $result = wp_insert_term( $name, 'lk_topic', array( 'slug' => $slug ) );
        // Báo thất bại nếu gặp lỗi khác lỗi chủ đề đã tồn tại; lỗi trùng được chấp nhận.
        if ( is_wp_error( $result ) && 'term_exists' !== $result->get_error_code() ) { return false; }
    }
    // Trả về true khi các chủ đề mẫu đã được tạo hoặc đã tồn tại đầy đủ.
    return true;
}