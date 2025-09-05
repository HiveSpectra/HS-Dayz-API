<?php
defined('ABSPATH') || exit;

/**
 * Register /Random/Full endpoint
 * Expects POST body: { "Count": int }
 * Returns: { "Status": "Success", "Numbers": [int, int, ...] }
 */

add_action('rest_api_init', function () {
    register_rest_route('hs-dayz/v1', '/Random/Full', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_random_full_handler',
        'permission_callback' => '__return_true'
    ]);
});

/**
 * Handle POST /Random/Full
 */
function hs_dayz_random_full_handler(WP_REST_Request $request) {
    global $wpdb;

    // ✅ Parse JSON body
    $json = $request->get_json_params();
    if (empty($json)) {
        $json = json_decode(file_get_contents('php://input'), true);
    }

    $count = intval($json['Count'] ?? 1);
    $count = max(1, min($count, 100));

    $numbers = [];
    for ($i = 0; $i < $count; $i++) {
        $numbers[] = random_int(-2147483647, 2147483647);
    }

    // ✅ Attempt to identify server from ServerAuth header
    $auth = sanitize_text_field($_SERVER['CONTENT_TYPE'] ?? '');
    $server = function_exists('hs_dayz_auth_by_serverauth') ? hs_dayz_auth_by_serverauth($auth) : null;
    $server_uid = $server->server_uid ?? '';
    $mod_slug = $json['Mod'] ?? $json['mod'] ?? '';

    // ✅ Log entry
    $entry = [
        'stage'    => 'random_full',
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'request'  => $json,
        'response' => ['Status' => 'Success', 'Numbers' => $numbers],
    ];

    $wpdb->insert("{$wpdb->prefix}hs_dayz_api_log", [
        'server_uid' => $server_uid,
        'mod_slug'   => $mod_slug,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'log_type'   => 'random',
        'message'    => wp_json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'created_at' => current_time('mysql')
    ]);

    return rest_ensure_response([
        'Status'  => 'Success',
        'Numbers' => $numbers
    ]);
}
