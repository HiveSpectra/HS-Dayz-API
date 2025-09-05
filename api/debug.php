<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    register_rest_route('hs-dayz/v1', '/debug/fingerprint', [
        'methods'             => 'GET',
        'callback'            => 'hs_dayz_debug_fingerprint',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('hs-dayz/v1', '/debug/headers', [
        'methods'             => 'GET',
        'callback'            => 'hs_dayz_debug_headers',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('hs-dayz/v1', '/debug/echo', [
        'methods'             => 'POST',
        'callback'            => 'hs_dayz_debug_echo',
        'permission_callback' => '__return_true',
    ]);
});

function hs_dayz_debug_fingerprint() {
    return new WP_REST_Response([
        'handled'   => '✅ WP plugin was reached',
        'hostname'  => gethostname(),
        'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        'client_ip' => hs_dayz_get_real_ip(),
        'time'      => current_time('mysql'),
    ], 200);
}

function hs_dayz_debug_headers($request) {
    return new WP_REST_Response([
        'headers'   => $request->get_headers(),
        'hostname'  => gethostname(),
        'client_ip' => hs_dayz_get_real_ip(),
        'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        'time'      => current_time('mysql'),
    ], 200);
}

function hs_dayz_debug_echo($request) {
    return new WP_REST_Response([
        'method'    => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'headers'   => $request->get_headers(),
        'body_raw'  => $request->get_body(),
        'json'      => hs_dayz_get_fallback_json($request),
        'client_ip' => hs_dayz_get_real_ip(),
        'hostname'  => gethostname(),
        'time'      => current_time('mysql'),
    ], 200);
}

// 🔄 Shared fallback parser
if (!function_exists('hs_dayz_get_fallback_json')) {
    function hs_dayz_get_fallback_json($request) {
        $json = $request->get_json_params();
        if (empty($json)) {
            $json = json_decode(file_get_contents('php://input'), true);
        }
        return is_array($json) ? $json : [];
    }
}

// 🌐 Get client IP
if (!function_exists('hs_dayz_get_real_ip')) {
    function hs_dayz_get_real_ip() {
        return $_SERVER['HTTP_CF_CONNECTING_IP'] ??
               $_SERVER['HTTP_X_FORWARDED_FOR'] ??
               $_SERVER['REMOTE_ADDR'] ??
               'unknown';
    }
}
