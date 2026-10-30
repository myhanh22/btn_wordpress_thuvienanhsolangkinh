<?php
/**
 * Plugin Name: Lăng Kính Security
 * Description: Theme-independent hardening and server-side password policy for the Group 1 gallery.
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 */

// Plugin bảo mật Lăng Kính, hoạt động độc lập với giao diện Astra.
// Các chức năng bảo mật vẫn được duy trì ngay cả khi thay đổi theme.

// Ngăn truy cập trực tiếp vào tệp plugin bên ngoài WordPress.
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Tắt trình chỉnh sửa mã giao diện và plugin trong trang quản trị WordPress.
// Giúp hạn chế nguy cơ kẻ xấu chỉnh sửa trực tiếp mã nguồn khi chiếm được tài khoản quản trị.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) { define( 'DISALLOW_FILE_EDIT', true ); }


// =====================================================
// 1. THIẾT LẬP CÁC BIỆN PHÁP BẢO MẬT CƠ BẢN
// =====================================================

add_action( 'init', function () {

    // LocalWP có thể tạo lại tệp hỗ trợ này khi khởi động với quyền file phụ thuộc umask của người dùng.
    $local_debug = ABSPATH . 'local-xdebuginfo.php';

    // Kiểm tra môi trường LocalWP và các điều kiện an toàn trước khi thay đổi quyền tệp.
    // Chỉ xử lý khi tệp tồn tại, không phải liên kết tượng trưng và có thể ghi.
    if ( 'local' === wp_get_environment_type() && is_file( $local_debug ) && ! is_link( $local_debug ) &&
        is_writable( $local_debug ) && 0644 !== ( fileperms( $local_debug ) & 0777 ) ) {

        // Thiết lập quyền truy cập tệp thành 0644:
        // Chủ sở hữu được đọc/ghi, những người khác chỉ được đọc.
        chmod( $local_debug, 0644 );
    }

    // Xóa thông tin phiên bản WordPress khỏi phần <head> của website.
    // Giúp hạn chế việc công khai thông tin phiên bản hệ thống.
    remove_action( 'wp_head', 'wp_generator' );

    // Kiểm tra xem yêu cầu hiện tại có phải là yêu cầu sử dụng XML-RPC hay không.
    // Nếu đúng thì từ chối truy cập bằng mã HTTP 403.
    if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {

        // Trả về mã trạng thái HTTP 403 - Forbidden (Từ chối truy cập).
        status_header( 403 );

        // Khai báo nội dung phản hồi là văn bản thuần với mã hóa UTF-8.
        header( 'Content-Type: text/plain; charset=UTF-8' );

        // Dừng xử lý yêu cầu XML-RPC.
        exit( 'XML-RPC is disabled.' );
    }
} );

// Xóa thông tin phiên bản WordPress được cung cấp thông qua bộ lọc Generator.
add_filter( 'the_generator', '__return_empty_string' );

// Vô hiệu hóa trạng thái XML-RPC trong WordPress.
add_filter( 'xmlrpc_enabled', '__return_false' );

// Không công khai danh sách các phương thức XML-RPC.
add_filter( 'xmlrpc_methods', '__return_empty_array' );


// Bổ sung các HTTP Security Headers để tăng cường bảo mật cho website.
add_filter( 'wp_headers', function ( $headers ) {

    // Xóa header X-Pingback nhằm hạn chế việc công khai endpoint Pingback.
    unset( $headers['X-Pingback'] );

    // Ngăn trình duyệt tự suy đoán kiểu dữ liệu MIME khác với kiểu máy chủ khai báo.
    $headers['X-Content-Type-Options'] = 'nosniff';

    // Kiểm soát thông tin Referrer được gửi khi người dùng điều hướng giữa các website.
    $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

    // Trả về danh sách HTTP Headers sau khi đã bổ sung các thiết lập bảo mật.
    return $headers;
} );


// =====================================================
// 2. KIỂM TRA CHÍNH SÁCH MẬT KHẨU
// =====================================================

/** Validate changes to passwords, never change existing user credentials. */

// Hàm kiểm tra mật khẩu mới hoặc mật khẩu được thay đổi.
// Không tự động thay đổi mật khẩu hiện có của người dùng.
function lk_security_password_error( $password ) {

    // Kiểm tra các điều kiện bắt buộc đối với mật khẩu:
    // - Phải là kiểu dữ liệu chuỗi.
    // - Có ít nhất 12 ký tự.
    // - Có ít nhất một chữ cái viết thường.
    // - Có ít nhất một chữ cái viết hoa.
    // - Có ít nhất một chữ số.
    // - Có ít nhất một ký tự đặc biệt.
    // - Không chứa các chuỗi mật khẩu phổ biến, dễ đoán.
    if ( ! is_string( $password ) || mb_strlen( $password ) < 12 ||
        ! preg_match( '/\p{Ll}/u', $password ) || ! preg_match( '/\p{Lu}/u', $password ) ||
        ! preg_match( '/\p{N}/u', $password ) || ! preg_match( '/[^\p{L}\p{N}\s]/u', $password ) ||
        preg_match( '/password|123456|qwerty|matkhau/i', $password ) ) {

        // Trả về lỗi WP_Error nếu mật khẩu không đáp ứng yêu cầu bảo mật.
        // Mã lỗi: lk_weak_password.
        // HTTP status 400: Yêu cầu không hợp lệ.
        return new WP_Error( 'lk_weak_password', 'Mật khẩu phải có ít nhất 12 ký tự, chữ hoa, chữ thường, chữ số và ký tự đặc biệt; tránh chuỗi phổ biến như password, qwerty hoặc 123456.', array( 'status' => 400 ) );
    }

    // Nếu mật khẩu hợp lệ thì trả về null, nghĩa là không phát hiện lỗi.
    return null;
}


// Hàm kiểm tra mật khẩu được nhập trong các biểu mẫu của WordPress.
function lk_security_validate_password_form( $errors ) {

    // Kiểm tra xem biểu mẫu có trường pass1 hay không.
    // Nếu không có hoặc người dùng chưa nhập mật khẩu thì bỏ qua.
    if ( ! isset( $_POST['pass1'] ) || '' === $_POST['pass1'] ) { return; }

    // Lấy dữ liệu mật khẩu từ POST và bỏ các ký tự escape do WordPress thêm vào.
    // Sau đó chuyển mật khẩu đến hàm kiểm tra chính sách bảo mật.
    $error = lk_security_password_error( wp_unslash( $_POST['pass1'] ) );

    // Nếu mật khẩu không hợp lệ thì thêm thông báo lỗi vào biểu mẫu.
    if ( $error ) { $errors->add( $error->get_error_code(), $error->get_error_message() ); }
}

// Áp dụng kiểm tra mật khẩu khi người dùng cập nhật thông tin hồ sơ.
add_action( 'user_profile_update_errors', 'lk_security_validate_password_form' );

// Áp dụng kiểm tra tương tự khi người dùng thực hiện đặt lại mật khẩu.
add_action( 'validate_password_reset', 'lk_security_validate_password_form' );


// User preparation filters accept objects, not errors. Stop at the REST callback boundary.

// Kiểm tra mật khẩu tại điểm tiếp nhận yêu cầu REST API.
// Thực hiện tại đây vì các bộ lọc chuẩn bị User không phù hợp để trả về đối tượng WP_Error.
add_filter( 'rest_request_before_callbacks', function ( $response, $handler, $request ) {

    // Chỉ kiểm tra những yêu cầu:
    // - Chưa có phản hồi trước đó.
    // - Sử dụng phương thức POST, PUT hoặc PATCH.
    // - Truy cập API quản lý người dùng của WordPress (/wp/v2/users).
    if ( null !== $response || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ||
        ! preg_match( '#^/wp/v2/users(?:/(?:me|[0-9]+))?/?$#', $request->get_route() ) ) { return $response; }

    // Lấy giá trị mật khẩu từ dữ liệu gửi lên REST API.
    $password = $request->get_param( 'password' );

    // Chỉ kiểm tra khi yêu cầu có truyền mật khẩu và giá trị không để trống.
    if ( null !== $password && '' !== $password ) {

        // Kiểm tra mật khẩu theo chính sách đã thiết lập ở trên.
        $error = lk_security_password_error( $password );

        // Nếu mật khẩu không hợp lệ thì trả về lỗi và dừng xử lý yêu cầu.
        if ( $error ) { return $error; }
    }

    // Nếu không phát hiện lỗi thì giữ nguyên phản hồi để WordPress tiếp tục xử lý.
    return $response;

}, 10, 3 ); // Độ ưu tiên 10, callback nhận 3 tham số.


// =====================================================
// 3. TẠO TRANG QUẢN LÝ TRẠNG THÁI BẢO MẬT
// =====================================================

add_action( 'admin_menu', function () {

    // Thêm trang quản lý Lăng Kính Security vào mục Công cụ (Tools) trong WordPress.
    // Chỉ người dùng có quyền manage_options mới được truy cập.
    add_management_page( 'Lăng Kính Security', 'Lăng Kính Security', 'manage_options', 'lk-security', function () {

        // Kiểm tra lại quyền truy cập trước khi hiển thị nội dung trang.
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        // Hiển thị tiêu đề trang và bắt đầu tạo bảng trạng thái bảo mật.
        echo '<div class="wrap"><h1>Lăng Kính Security</h1><table class="widefat striped"><tbody>';

        // Khai báo danh sách các hạng mục bảo mật và trạng thái tương ứng.
        $rows = array(

            // Thông tin phiên bản của plugin.
            'Module' => '1.0.0 — độc lập với theme Astra',

            // Chính sách áp dụng cho mật khẩu mới hoặc mật khẩu được thay đổi.
            'Mật khẩu mới/đổi' => 'Ít nhất 12 ký tự; hoa, thường, số, ký tự đặc biệt; chặn chuỗi phổ biến. Kiểm tra tại hồ sơ, đặt lại mật khẩu và REST.',

            // Mật khẩu của tài khoản hiện có không bị tự động thay đổi.
            'Mật khẩu hiện có' => 'Không thay đổi; không ép reset mật khẩu quản trị.',

            // Trạng thái ẩn thông tin phiên bản WordPress khỏi Generator.
            'Generator HTML/feed' => 'Đã ẩn; giữ nguyên ver CSS/JS để cache busting.',

            // XML-RPC bị plugin từ chối bằng HTTP 403.
            // Có thể cần cấu hình bổ sung ở Nginx và kiểm tra thực tế qua HTTP.
            'XML-RPC' => 'Module từ chối 403; cần thêm rule Nginx và kiểm chứng HTTP.',

            // Hiển thị trạng thái của chức năng chỉnh sửa mã nguồn trong WordPress.
            'Trình sửa code WordPress' => DISALLOW_FILE_EDIT ? 'Đã tắt' : 'Chưa tắt do cấu hình sẵn có',

            // Lấy cấu hình từ plugin Limit Login Attempts Reloaded.
            // Hiển thị số lần đăng nhập sai tối đa, thời gian khóa và khoảng thời gian đếm lỗi.
            // Các giá trị thời gian được đổi từ giây sang phút bằng cách chia cho 60.
            'Giới hạn đăng nhập' => sprintf( '%d lần sai; khóa %d phút; cửa sổ đếm %d phút. Limit Login Attempts Reloaded.', (int) get_option( 'limit_login_allowed_retries', 0 ), (int) get_option( 'limit_login_lockout_duration', 0 ) / 60, (int) get_option( 'limit_login_valid_duration', 0 ) / 60 ),

            // Hướng dẫn khôi phục plugin nếu cần xử lý sự cố.
            // Không đổi tên wp-login.php hoặc wp-admin để tránh gây lỗi truy cập quản trị.
            'Khôi phục' => 'Vô hiệu plugin bằng WP-CLI/file manager. Không đổi tên wp-login.php hoặc wp-admin.',
        );

        // Duyệt từng cặp tên hạng mục và nội dung trạng thái để tạo các hàng trong bảng.
        foreach ( $rows as $name => $value ) {

            // esc_html() giúp mã hóa dữ liệu trước khi hiển thị,
            // hạn chế nguy cơ chèn mã HTML hoặc JavaScript độc hại (XSS).
            echo '<tr><th>' . esc_html( $name ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
        }

        // Kết thúc phần thân bảng và đóng vùng giao diện trang quản trị.
        echo '</tbody></table></div>';
    } );
} ); // Kết thúc đăng ký trang quản lý plugin.