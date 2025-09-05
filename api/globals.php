<?php
defined('ABSPATH') || exit;

if (!function_exists('hs_dayz_response')) {
    function hs_dayz_response($status = 200, $data = []) {
        return new WP_REST_Response($data, $status);
    }
}

require_once plugin_dir_path(__FILE__) . '/../includes/auth.php';

add_action('rest_api_init', function () {
    $base = 'hs-dayz/v1';

    foreach (['Banking', 'MapLink', 'UniversalApiStatus'] as $mod) {
        
        
        
    register_rest_route($base, "/Globals/Load/$mod", [
        'methods'  => 'POST',
        'callback' => function ($request) use ($mod) {
            return hs_dayz_handle_dynamic_global($request, $mod);
        },
        'permission_callback' => '__return_true',
    ]);
    }

    register_rest_route($base, '/Globals/Save/(?P<mod>[a-zA-Z0-9_-]+)', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_handle_save_global',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/Globals/Transaction/(?P<mod>[a-zA-Z0-9_-]+)', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_handle_transaction_global',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/Globals/Update/(?P<mod>[a-zA-Z0-9_-]+)', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_handle_update_global',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/Globals/TestEcho', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_handle_test_echo',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/Status/(?P<auth>[a-zA-Z0-9]+)', [
        'methods'  => ['GET', 'POST'],
        'callback' => 'hs_dayz_status_endpoint',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/Status', [
        'methods'  => ['GET', 'POST'],
        'callback' => 'hs_dayz_status_header_fallback',
        'permission_callback' => '__return_true',
    ]);
});

// 📥 Dynamic config fetch (auto-create if ConfigVersion is sent)
function hs_dayz_handle_dynamic_global($request, $mod_slug) {
    global $wpdb;

    $table = $wpdb->prefix . 'hs_dayz_globals';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT data FROM $table WHERE mod_slug = %s", $mod_slug
    ), ARRAY_A);

    if (!$row) {
        return new WP_REST_Response(['Status' => 'Error', 'Error' => 'No config found'], 404);
    }

    $data = json_decode($row['data'], true);
    if (!is_array($data)) {
        return new WP_REST_Response(['Status' => 'Error', 'Error' => 'Invalid config format'], 500);
    }

    if (function_exists('hs_dayz_log_api_response')) {
        hs_dayz_log_api_response("GlobalsLoad:$mod_slug", $data);
    }

    return new WP_REST_Response($data, 200, [
        'Content-Type' => 'application/json'
    ]);
}




// 💾 Save globals for mod
function hs_dayz_handle_save_global($request) {
    global $wpdb;

    $mod_slug = sanitize_text_field($request['mod']);
    $json = hs_dayz_parse_raw_json($request);

    if (!is_array($json) || !isset($json['ConfigVersion'])) {
        return hs_dayz_response(400, ['Status' => 'Error', 'Error' => 'Missing or invalid ConfigVersion']);
    }

    // Check for existing config
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_dayz_globals WHERE mod_slug = %s",
        $mod_slug
    ));

    // If config exists and balance is 0 and it's "Multi World Bank", reject it
    if ($existing && 
        isset($json['BankName']) && 
        strtolower($json['BankName']) === 'multi world bank' && 
        isset($json['StartingBalance']) && 
        floatval($json['StartingBalance']) === 0.0
    ) {
        return hs_dayz_response(409, [
            'Status' => 'Error',
            'Error'  => 'Attempted to overwrite existing config with default placeholder'
        ]);
    }

    // Save or replace config
    $wpdb->replace($wpdb->prefix . 'hs_dayz_globals', [
        'mod_slug'   => $mod_slug,
        'data'       => wp_json_encode($json),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);

    return hs_dayz_response(200, ['Status' => 'Success', 'Mod' => $mod_slug]);
}


// 📦 Log transactions + apply global numeric update
function hs_dayz_handle_transaction_global($request) {
    global $wpdb;

    $mod_slug = sanitize_text_field($request['mod']);
    $params = hs_dayz_get_fallback_json($request);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $server = hs_dayz_auth_server(
        sanitize_text_field($params['ServerID'] ?? ''),
        sanitize_text_field($params['ServerAuth'] ?? '')
    );

    if (!$server) {
        return hs_dayz_response(401, ['Status' => 'Error', 'Error' => 'Unauthorized']);
    }

    $element = sanitize_text_field($params['Element'] ?? '');
    $value   = floatval($params['Value'] ?? 0);

    if (!$element || !is_numeric($value)) {
        return hs_dayz_response(400, ['Status' => 'Error', 'Error' => 'Missing or invalid Element/Value']);
    }

    $table = $wpdb->prefix . 'hs_dayz_globals';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE mod_slug = %s", $mod_slug));
    $data = $row ? json_decode($row->data, true) : [];

    $current = isset($data[$element]) ? floatval($data[$element]) : 0;
    $new_value = $current + $value;
    $data[$element] = $new_value;

    $wpdb->update($table, [
        'data' => wp_json_encode($data),
        'updated_at' => current_time('mysql')
    ], ['mod_slug' => $mod_slug]);

    $wpdb->insert($wpdb->prefix . 'hs_dayz_transactions', [
        'mod_slug'   => $mod_slug,
        'server_uid' => $server->server_uid,
        'ip_address' => $ip,
        'payload'    => wp_json_encode($params),
        'created_at' => current_time('mysql'),
    ]);

    return hs_dayz_response(200, [
        'Status'    => 'Success',
        'Mod'       => $mod_slug,
        'Element'   => $element,
        'NewValue'  => $new_value,
        'ServerUID' => $server->server_uid,
        'Timestamp' => current_time('mysql')
    ]);
}

// 🛰️ Log passive data (no auth)
function hs_dayz_handle_update_global($request) {
    $mod_slug = sanitize_text_field($request['mod']);
    $payload = hs_dayz_get_fallback_json($request);

    if (function_exists('hs_dayz_log_api_response')) {
        hs_dayz_log_api_response("Update:$mod_slug", $payload);
    }

    return hs_dayz_response(200, ['Status' => 'Received', 'Mod' => $mod_slug]);
}

// 🧪 Echo back raw POST data and headers (for testing)
function hs_dayz_handle_test_echo($request) {
    $raw = $request->get_body();
    $json = json_decode($raw, true);

    return hs_dayz_response(200, [
        'Timestamp' => current_time('mysql'),
        'IP'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'Headers'   => $request->get_headers(),
        'Raw'       => $raw,
        'JSON'      => $json,
        'Error'     => is_array($json) ? null : 'Failed to decode JSON'
    ]);
}

// 🛰️ /Status/{auth} endpoint for mod heartbeat
function hs_dayz_status_endpoint($request) {
    global $wpdb;

    $auth = sanitize_text_field($request['auth']);

    if (!hs_dayz_check_server_auth($auth)) {
        return hs_dayz_response(401, ['Status' => 'Error', 'Error' => 'Unauthorized']);
    }

    $mod_slug = 'UniversalApiStatus';
    $table = $wpdb->prefix . 'hs_dayz_globals';

    $data = [
        'Status' => 'Online',
        'Time'   => current_time('mysql')
    ];

    $wpdb->replace($table, [
        'mod_slug'   => $mod_slug,
        'data'       => wp_json_encode($data),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);

    return hs_dayz_response(200, [
        'Status'  => 'Success',
        'Message' => 'Status updated'
    ]);
}

// 🛰️ /Status fallback: mod sends auth-key via header not path
function hs_dayz_status_header_fallback($request) {
    $auth = sanitize_text_field($_SERVER['HTTP_AUTH_KEY'] ?? '');

    if (!$auth) {
        return hs_dayz_response(400, ['Status' => 'Error', 'Error' => 'Missing auth-key header']);
    }

    $request['auth'] = $auth;
    return hs_dayz_status_endpoint($request);
}

// 🛠️ Admin UI: Save globals via AJAX
add_action('wp_ajax_hs_dayz_update_global_mod', function () {
    if (!current_user_can('manage_options')) {
        return hs_dayz_response(403, ['Status' => 'Error', 'Error' => 'Unauthorized']);
    }

    $mod_slug = sanitize_text_field($_POST['mod'] ?? '');
    $json_raw = wp_unslash($_POST['data'] ?? '');
    $decoded = json_decode($json_raw, true);

    if (!is_array($decoded)) {
        return hs_dayz_response(400, ['Status' => 'Error', 'Error' => 'Invalid JSON']);
    }

    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'hs_dayz_globals',
        ['data' => wp_json_encode($decoded)],
        ['mod_slug' => $mod_slug]
    );

    return $result !== false
        ? hs_dayz_response(200, ['Status' => 'Success'])
        : hs_dayz_response(500, ['Status' => 'Error', 'Error' => 'Database update failed']);
});
