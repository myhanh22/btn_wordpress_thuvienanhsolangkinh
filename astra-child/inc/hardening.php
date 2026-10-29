<?php
/**
 * Hardening Security Settings - TV4
 */

if (!defined('ABSPATH')) {
    exit; // Chặn truy cập trực tiếp
}

// 1. Ẩn thông tin phiên bản WordPress
remove_action('wp_head', 'wp_generator');

// 2. Chặn REST API User Enumeration (lộ username)
add_filter('rest_endpoints', function($endpoints) {
    if (isset($endpoints['/wp/v2/users'])) {
        unset($endpoints['/wp/v2/users']);
    }
    if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});

// 3. Tắt XML-RPC để chống tấn công Brute Force / Pingback
add_filter('xmlrpc_enabled', '__return_false');

// 4. Bỏ Query Strings ở tài nguyên tĩnh (CSS/JS) để ẩn version
function tv4_remove_script_version($src) {
    $parts = explode('?ver=', $src);
    return $parts[0];
}
add_filter('script_loader_src', 'tv4_remove_script_version', 15, 1);
add_filter('style_loader_src', 'tv4_remove_script_version', 15, 1);
