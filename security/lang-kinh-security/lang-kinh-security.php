<?php
/**
 * Plugin Name: Lăng Kính Security
 * Description: Theme-independent hardening and server-side password policy for the Group 1 gallery.
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) { define( 'DISALLOW_FILE_EDIT', true ); }

add_action( 'init', function () {
    // LocalWP recreates this helper with the desktop user's umask on Start.
    $local_debug = ABSPATH . 'local-xdebuginfo.php';
    if ( 'local' === wp_get_environment_type() && is_file( $local_debug ) && ! is_link( $local_debug ) &&
        is_writable( $local_debug ) && 0644 !== ( fileperms( $local_debug ) & 0777 ) ) {
        chmod( $local_debug, 0644 );
    }
    remove_action( 'wp_head', 'wp_generator' );
    if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
        status_header( 403 );
        header( 'Content-Type: text/plain; charset=UTF-8' );
        exit( 'XML-RPC is disabled.' );
    }
} );
add_filter( 'the_generator', '__return_empty_string' );
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_filter( 'wp_headers', function ( $headers ) {
    unset( $headers['X-Pingback'] );
    $headers['X-Content-Type-Options'] = 'nosniff';
    $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
    return $headers;
} );

/** Validate changes to passwords, never change existing user credentials. */
function lk_security_password_error( $password ) {
    if ( ! is_string( $password ) || mb_strlen( $password ) < 12 ||
        ! preg_match( '/\p{Ll}/u', $password ) || ! preg_match( '/\p{Lu}/u', $password ) ||
        ! preg_match( '/\p{N}/u', $password ) || ! preg_match( '/[^\p{L}\p{N}\s]/u', $password ) ||
        preg_match( '/password|123456|qwerty|matkhau/i', $password ) ) {
        return new WP_Error( 'lk_weak_password', 'Mật khẩu phải có ít nhất 12 ký tự, chữ hoa, chữ thường, chữ số và ký tự đặc biệt; tránh chuỗi phổ biến như password, qwerty hoặc 123456.', array( 'status' => 400 ) );
    }
    return null;
}
function lk_security_validate_password_form( $errors ) {
    if ( ! isset( $_POST['pass1'] ) || '' === $_POST['pass1'] ) { return; }
    $error = lk_security_password_error( wp_unslash( $_POST['pass1'] ) );
    if ( $error ) { $errors->add( $error->get_error_code(), $error->get_error_message() ); }
}
add_action( 'user_profile_update_errors', 'lk_security_validate_password_form' );
add_action( 'validate_password_reset', 'lk_security_validate_password_form' );
// User preparation filters accept objects, not errors. Stop at the REST callback boundary.
add_filter( 'rest_request_before_callbacks', function ( $response, $handler, $request ) {
    if ( null !== $response || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ||
        ! preg_match( '#^/wp/v2/users(?:/(?:me|[0-9]+))?/?$#', $request->get_route() ) ) { return $response; }
    $password = $request->get_param( 'password' );
    if ( null !== $password && '' !== $password ) {
        $error = lk_security_password_error( $password );
        if ( $error ) { return $error; }
    }
    return $response;
}, 10, 3 );

add_action( 'admin_menu', function () {
    add_management_page( 'Lăng Kính Security', 'Lăng Kính Security', 'manage_options', 'lk-security', function () {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        echo '<div class="wrap"><h1>Lăng Kính Security</h1><table class="widefat striped"><tbody>';
        $rows = array(
            'Module' => '1.0.0 — độc lập với theme Astra',
            'Mật khẩu mới/đổi' => 'Ít nhất 12 ký tự; hoa, thường, số, ký tự đặc biệt; chặn chuỗi phổ biến. Kiểm tra tại hồ sơ, đặt lại mật khẩu và REST.',
            'Mật khẩu hiện có' => 'Không thay đổi; không ép reset mật khẩu quản trị.',
            'Generator HTML/feed' => 'Đã ẩn; giữ nguyên ver CSS/JS để cache busting.',
            'XML-RPC' => 'Module từ chối 403; cần thêm rule Nginx và kiểm chứng HTTP.',
            'Trình sửa code WordPress' => DISALLOW_FILE_EDIT ? 'Đã tắt' : 'Chưa tắt do cấu hình sẵn có',
            'Giới hạn đăng nhập' => sprintf( '%d lần sai; khóa %d phút; cửa sổ đếm %d phút. Limit Login Attempts Reloaded.', (int) get_option( 'limit_login_allowed_retries', 0 ), (int) get_option( 'limit_login_lockout_duration', 0 ) / 60, (int) get_option( 'limit_login_valid_duration', 0 ) / 60 ),
            'Khôi phục' => 'Vô hiệu plugin bằng WP-CLI/file manager. Không đổi tên wp-login.php hoặc wp-admin.',
        );
        foreach ( $rows as $name => $value ) {
            echo '<tr><th>' . esc_html( $name ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    } );
} );
