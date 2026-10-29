<?php
/**
 * Secure Coding Helper Functions - TV4
 */

if (!defined('ABSPATH')) {
    exit; // Chặn truy cập trực tiếp
}

// 1. Hàm làm sạch dữ liệu đầu vào (Sanitization)
function tv4_sanitize_input_data($data) {
    if (is_array($data)) {
        return array_map('tv4_sanitize_input_data', $data);
    }
    return sanitize_text_field($data);
}

// 2. Hàm kiểm tra bảo mật Nonce cho các request AJAX/Form
function tv4_verify_security_nonce($nonce_action, $nonce_field = '_wpnonce') {
    if (!isset($_REQUEST[$nonce_field]) || !wp_verify_nonce($_REQUEST[$nonce_field], $nonce_action)) {
        wp_die(__('Yêu cầu không hợp lệ hoặc đã hết hạn bảo mật.', 'astra-child'), __('Lỗi Bảo Mật', 'astra-child'), array('response' => 403));
    }
}

// 3. Hàm kiểm tra quyền người dùng trước khi thao tác
function tv4_check_user_permission($capability = 'upload_files') {
    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Bạn không có quyền thực hiện thao tác này.', 'astra-child')), 403);
    }
}
