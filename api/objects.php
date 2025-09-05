<?php
// objects-endpoints.php - REST API for object data (mod-specific)

defined('ABSPATH') || exit;

if (!function_exists('hs_dayz_auth_by_serverauth')) {
    require_once plugin_dir_path(__FILE__) . '/../includes/authchecker.php';
}

if (!function_exists('hs_dayz_response')) {
    function hs_dayz_response($status = 200, $data = []) {
        return new WP_REST_Response($data, $status);
    }
}

add_action('rest_api_init', function () {
    $base = 'hs-dayz/v1';

    register_rest_route($base, '/object/load/(?P<object_id>[^/]+)/(?P<mod_slug>[^/]+)', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_object_load',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/object/save/(?P<object_id>[^/]+)/(?P<mod_slug>[^/]+)', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_object_save',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/object/update/(?P<object_id>[^/]+)/(?P<mod_slug>[^/]+)', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_object_update',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/object/delete/(?P<object_id>[^/]+)/(?P<mod_slug>[^/]+)', [
        'methods'  => 'POST',
        'callback' => 'hs_dayz_object_delete',
        'permission_callback' => '__return_true',
    ]);
});

// 🔍 Load object data or create default
function hs_dayz_object_load($request) {
    global $wpdb;

    $object_id = sanitize_text_field($request['object_id']);
    $mod_slug  = sanitize_text_field($request['mod_slug']);
    $auth      = $request->get_header('auth-key');
    $incoming  = hs_dayz_get_fallback_json($request);

    $server = hs_dayz_auth_by_serverauth($auth);
    if (!$server) {
        return hs_dayz_response(401, ['Status' => 'Error', 'Error' => 'Invalid Auth']);
    }

    $table = $wpdb->prefix . 'hs_dayz_objects';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT data FROM $table WHERE object_id = %s AND mod_slug = %s",
        $object_id, $mod_slug
    ));

    if ($row) {
        $decoded = json_decode($row->data, true);
        $decoded['m_DataReceived'] = 1;
        if (function_exists('hs_dayz_log_api_response')) {
            hs_dayz_log_api_response("ObjectLoad", [
                'server_uid' => $server->server_uid,
                'mod_slug'   => $mod_slug,
                'object_id'  => $object_id,
                'data'       => $decoded
            ]);
        }
        return hs_dayz_response(200, $decoded ?? []);
    } else {
        $incoming['m_DataReceived'] = 1;
        $wpdb->insert($table, [
            'object_id'  => $object_id,
            'mod_slug'   => $mod_slug,
            'data'       => wp_json_encode($incoming),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        if (function_exists('hs_dayz_log_api_response')) {
            hs_dayz_log_api_response("ObjectCreate", [
                'server_uid' => $server->server_uid,
                'mod_slug'   => $mod_slug,
                'object_id'  => $object_id,
                'data'       => $incoming
            ]);
        }
        return hs_dayz_response(201, $incoming);
    }
}

// 💾 Save (upsert) object
function hs_dayz_object_save($request) {
    global $wpdb;

    $object_id = sanitize_text_field($request['object_id']);
    $mod_slug  = sanitize_text_field($request['mod_slug']);
    $auth      = $request->get_header('auth-key');
    $data      = hs_dayz_get_fallback_json($request);

    $server = hs_dayz_auth_by_serverauth($auth);
    if (!$server) {
        return hs_dayz_response(401, ['Status' => 'Error', 'Error' => 'Invalid Auth']);
    }

    $data['m_DataReceived'] = 1;

    $wpdb->replace($wpdb->prefix . 'hs_dayz_objects', [
        'object_id'  => $object_id,
        'mod_slug'   => $mod_slug,
        'data'       => wp_json_encode($data),
        'updated_at' => current_time('mysql')
    ]);

    if (function_exists('hs_dayz_log_api_response')) {
        hs_dayz_log_api_response("ObjectSave", [
            'server_uid' => $server->server_uid,
            'mod_slug'   => $mod_slug,
            'object_id'  => $object_id,
            'data'       => $data
        ]);
    }

    return hs_dayz_response(200, ['Status' => 'Success', 'ObjectID' => $object_id, 'm_DataReceived' => 1]);
}

// 🔄 Update object data (element-specific)
function hs_dayz_object_update($request) {
    global $wpdb;

    $object_id = sanitize_text_field($request['object_id']);
    $mod_slug  = sanitize_text_field($request['mod_slug']);
    $auth      = $request->get_header('auth-key');
    $params    = hs_dayz_get_fallback_json($request);

    $server = hs_dayz_auth_by_serverauth($auth);
    if (!$server) {
        return hs_dayz_response(401, ['Status' => 'Error', 'Error' => 'Invalid Auth']);
    }

    $element   = sanitize_text_field($params['Element'] ?? '');
    $value     = $params['Value'] ?? null;
    $operation = strtolower($params['Operation'] ?? 'set');

    if (empty($element)) {
        return hs_dayz_response(400, ['Status' => 'Error', 'Error' => 'Missing element name']);
    }

    $table = $wpdb->prefix . 'hs_dayz_objects';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT data FROM $table WHERE object_id = %s AND mod_slug = %s",
        $object_id, $mod_slug
    ));

    $data = $row ? json_decode($row->data, true) : [];

    switch ($operation) {
        case 'push':
            if (!isset($data[$element]) || !is_array($data[$element])) $data[$element] = [];
            $data[$element][] = $value;
            break;

        case 'pull':
            if (isset($data[$element]) && is_array($data[$element])) {
                $data[$element] = array_values(array_diff($data[$element], [$value]));
            }
            break;

        case 'unset':
            unset($data[$element]);
            break;

        case 'set':
        default:
            $data[$element] = $value;
            break;
    }

    $wpdb->replace($table, [
        'object_id'  => $object_id,
        'mod_slug'   => $mod_slug,
        'data'       => wp_json_encode($data),
        'updated_at' => current_time('mysql')
    ]);

    if (function_exists('hs_dayz_log_api_response')) {
        hs_dayz_log_api_response("ObjectUpdate", [
            'server_uid' => $server->server_uid,
            'mod_slug'   => $mod_slug,
            'object_id'  => $object_id,
            'data'       => $data,
            'element'    => $element,
            'operation'  => $operation
        ]);
    }

    return hs_dayz_response(200, [
        'Status'   => 'Success',
        'Mod'      => $mod_slug,
        'ObjectID' => $object_id,
        'Element'  => $element,
        'Op'       => $operation
    ]);
}

// 🗑️ Delete object by ID
function hs_dayz_object_delete($request) {
    global $wpdb;

    $object_id = sanitize_text_field($request['object_id']);
    $mod_slug  = sanitize_text_field($request['mod_slug']);
    $auth      = $request->get_header('auth-key');

    $server = hs_dayz_auth_by_serverauth($auth);
    if (!$server) {
        return hs_dayz_response(401, ['Status' => 'Error', 'Error' => 'Invalid Auth']);
    }

    $deleted = $wpdb->delete($wpdb->prefix . 'hs_dayz_objects', [
        'object_id' => $object_id,
        'mod_slug'  => $mod_slug
    ]);

    if (function_exists('hs_dayz_log_api_response')) {
        hs_dayz_log_api_response("ObjectDelete", [
            'server_uid' => $server->server_uid,
            'mod_slug'   => $mod_slug,
            'object_id'  => $object_id,
            'Deleted'    => $deleted
        ]);
    }

    return hs_dayz_response(200, [
        'Status'   => $deleted ? 'Success' : 'NotFound',
        'ObjectID' => $object_id
    ]);
}
