<?php
defined('ABSPATH') || exit;

// ✅ Register REST API routes for DayZ
add_action('rest_api_init', function () {
    if (!function_exists('hs_dayz_auth_server')) {
        require_once plugin_dir_path(__FILE__) . '/../includes/authchecker.php';
    }

    $base = 'hs-dayz/v1';

    // 📡 Register /status endpoint
    register_rest_route($base, '/status', [
        'methods'             => 'POST',
        'callback'            => 'hs_dayz_receive_status',
        'permission_callback' => '__return_true',
    ]);
}, 0);

// 🔐 Auth helper (revised to use only Content-Type header)
function hs_dayz_get_authenticated_server(array $params): ?object {
    $server_auth = hs_dayz_extract_auth_key(); // pulled from Content-Type
    return hs_dayz_auth_server($server_auth);  // single-arg only
}

// 📡 Status Handler
function hs_dayz_receive_status($request) {
    global $wpdb;

    // 🔑 Extract auth key from mod headers (mod uses Content-Type)
    $auth_key = hs_dayz_extract_auth_key();
    $server   = hs_dayz_auth_server($auth_key);

    // 🧠 Determine if auth passed
    $error = $server ? 'noerror' : 'noauth';

    // 📝 Write UniversalApiStatus config object for test purposes (always runs)
    $wpdb->replace($wpdb->prefix . 'hs_dayz_globals', [
        'mod_slug'   => 'UniversalApiStatus',
        'data'       => wp_json_encode([
            'Description' => 'This Object Exists as a test whenever the status URL is called to make sure the database is writeable',
            'TestVar'     => mt_rand() / mt_getrandmax()
        ]),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);

    return new WP_REST_Response([
        'Status'    => 'Success',
        'Error'     => 'noerror',
        'Version'   => '1.3.2',
        'Discord'   => !empty($features['Discord']) ? 'Enabled' : '',
        'Translate' => !empty($features['Translate']) ? 'Enabled' : '',
        'Wit'       => [],
        'QnA'       => [],
        'LUIS'      => [],
    ], 200, ['Content-Type' => 'application/json']);
}


// ✅ Used by Status to convert enabled array into string
function get_feature_string($enabled) {
    return !empty($enabled) ? 'Enabled' : '';
}

