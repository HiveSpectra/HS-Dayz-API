<?php
// api-logger.php - Accurate logging for hs-dayz/v1 REST API traffic
defined('ABSPATH') || exit;

if (!get_option('hs_dayz_logger_booted')) {
    error_log('[HS-DayZ LOGGER] ✅ Clean logger loaded');
    update_option('hs_dayz_logger_booted', 1, true);
}



// ✅ One-time raw input capture for initial request payload
if (!isset($GLOBALS['hs_dayz_raw_input'])) {
    $input = file_get_contents('php://input');
    $GLOBALS['hs_dayz_raw_input'] = $input ?: '';
}

// 📁 Writes actual log entry to raw file
function hs_dayz_write_log_file($label, $payload) {
    $upload_dir = wp_upload_dir();
    $log_dir = trailingslashit($upload_dir['basedir']) . 'hs-dayz-logs';
    $log_file = trailingslashit($log_dir) . 'raw-api.log';

    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    unset($headers['cookie'], $headers['authorization']);

    $entry = [
        'timestamp' => current_time('mysql'),
        'label'     => $label,
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method'    => $_SERVER['REQUEST_METHOD'] ?? 'undefined',
        'uri'       => $_SERVER['REQUEST_URI'] ?? 'undefined',
        'headers'   => $headers,
        'payload'   => $payload,
    ];

    file_put_contents($log_file, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n", FILE_APPEND);
}

// 🔍 INIT HOOK: Capture very early traffic and raw input
add_action('init', function () {
    if (empty($_SERVER['REQUEST_URI']) || stripos($_SERVER['REQUEST_URI'], '/wp-json/hs-dayz/v1') === false) return;

    global $wpdb;

    $raw      = $GLOBALS['hs_dayz_raw_input'] ?? '';
    $payload  = json_decode($raw, true);
    $jsonErr  = json_last_error();

    $log_data = ($jsonErr === JSON_ERROR_NONE) ? $payload : ['raw' => $raw, 'error' => json_last_error_msg()];
    hs_dayz_write_log_file('init', $log_data);

    $entry = [
        'stage'    => 'init',
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method'   => $_SERVER['REQUEST_METHOD'] ?? 'undefined',
        'uri'      => $_SERVER['REQUEST_URI'] ?? 'undefined',
        'payload'  => $log_data,
    ];

    $wpdb->insert("{$wpdb->prefix}hs_dayz_api_log", [
        'server_uid' => '',
        'mod_slug'   => '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'log_type'   => 'init',
        'message'    => wp_json_encode($entry, JSON_PRETTY_PRINT),
        'created_at' => current_time('mysql'),
    ]);
}, 0);

// 📡 REST API LOADED HOOK: After route matched, pre-dispatch
add_action('rest_api_loaded', function () {
    if (empty($_SERVER['REQUEST_URI']) || stripos($_SERVER['REQUEST_URI'], '/wp-json/hs-dayz/v1') === false) return;

    global $wpdb;

    $raw        = $GLOBALS['hs_dayz_raw_input'] ?? '';
    $decoded    = json_decode($raw, true);
    $jsonErr    = json_last_error();
    $payload    = ($jsonErr === JSON_ERROR_NONE) ? $decoded : ['raw' => $raw, 'error' => json_last_error_msg()];
    $ServerAuth = $_SERVER['HTTP_AUTH_KEY'] ?? '';

    $server     = function_exists('hs_dayz_auth_by_serverauth') ? hs_dayz_auth_by_serverauth($ServerAuth) : null;
    $server_uid = $server->server_uid ?? '';
    $mod_slug   = $decoded['Mod'] ?? $decoded['mod'] ?? '';

    hs_dayz_write_log_file('request', $payload);

    $entry = [
        'stage'      => 'request',
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method'     => $_SERVER['REQUEST_METHOD'] ?? 'undefined',
        'uri'        => $_SERVER['REQUEST_URI'] ?? 'undefined',
        'auth_key'   => $ServerAuth,
        'server_uid' => $server_uid,
        'mod_slug'   => $mod_slug,
        'payload'    => $payload,
    ];

    $wpdb->insert("{$wpdb->prefix}hs_dayz_api_log", [
        'server_uid' => $server_uid,
        'mod_slug'   => $mod_slug,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'log_type'   => 'request',
        'message'    => wp_json_encode($entry, JSON_PRETTY_PRINT),
        'created_at' => current_time('mysql'),
    ]);
}, 1);

// ✅ POST-DISPATCH: Log actual response data
add_filter('rest_post_dispatch', function ($response, $server, $request) {
    $route = $request->get_route();
    if (stripos($route, '/hs-dayz/v1') === false) return $response;

    global $wpdb;

    $data = $response instanceof WP_REST_Response ? $response->get_data() : (array) $response;
    hs_dayz_write_log_file('response', $data);

    $entry = [
        'stage'    => 'response',
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'route'    => $route,
        'response' => $data,
    ];

    $wpdb->insert("{$wpdb->prefix}hs_dayz_api_log", [
        'server_uid' => '',
        'mod_slug'   => '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'log_type'   => 'response',
        'message'    => wp_json_encode($entry, JSON_PRETTY_PRINT),
        'created_at' => current_time('mysql'),
    ]);

    return $response;
}, 10, 3);

// 🧼 Admin-triggered log clear
add_action('admin_init', function () {
    if (isset($_POST['hs_dayz_clear_log']) && current_user_can('read')) {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}hs_dayz_api_log");
        error_log("[HS-DayZ LOGGER] ✅ Cleared hs_dayz_api_log");
    }
});
