<?php
// helpers.php — Core utilities for HS-DayZ plugin

defined('ABSPATH') || exit;

/**
 * 🔄 Send a standardized REST API response.
 *
 * @param int   $status HTTP status code (default 200)
 * @param array $data   Response payload
 * @return WP_REST_Response
 */
if (!function_exists('hs_dayz_response')) {
    function hs_dayz_response($status = 200, $data = []) {
        return new WP_REST_Response($data, $status);
    }
}

/**
 * 📄 Send a raw JSON array response.
 *
 * Useful when returning a clean list: e.g., [] or [ {..}, {..} ]
 *
 * @param array $items  The array payload
 * @param int   $status HTTP status code (default 200)
 * @return WP_REST_Response
 */
if (!function_exists('hs_dayz_json_response_array')) {
    function hs_dayz_json_response_array(array $items = [], int $status = 200) {
        return new WP_REST_Response($items, $status);
    }
}

/**
 * 📝 Log API events or errors to the hs_dayz_api_log table.
 *
 * @param string $label Log label (e.g., 'status-auth-fail')
 * @param mixed  $data  Data to store (array, object, or string)
 * @param string $server_uid (optional) UID of the server involved
 * @param string $mod_slug   (optional) Mod slug if relevant
 */
if (!function_exists('hs_dayz_log_api_response')) {
    function hs_dayz_log_api_response($label, $data, $server_uid = '', $mod_slug = '') {
        global $wpdb;

        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $timestamp = current_time('mysql');

        $message = is_array($data) || is_object($data)
            ? wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : (string) $data;

        $wpdb->insert("{$wpdb->prefix}hs_dayz_api_log", [
            'server_uid' => $server_uid,
            'mod_slug'   => $mod_slug,
            'ip_address' => $ip,
            'log_type'   => sanitize_text_field($label),
            'message'    => $message,
            'created_at' => $timestamp,
        ]);
    }
}

/**
 * 📦 Safely parse raw incoming JSON payload.
 *
 * Useful for clients that fail to send Content-Type: application/json.
 *
 * @param WP_REST_Request $request
 * @return array
 */
if (!function_exists('hs_dayz_parse_raw_json')) {
    function hs_dayz_parse_raw_json($request) {
        $json = $request->get_json_params();

        if (empty($json)) {
            $raw  = file_get_contents('php://input');
            $json = json_decode($raw, true);
        }

        return is_array($json) ? $json : [];
    }
}

/**
 * 🔐 Extract ServerAuth from the Content-Type header.
 *
 * The DayZ mod passes the ServerAuth token as the Content-Type value.
 *
 * @return string Sanitized ServerAuth token
 */
if (!function_exists('hs_dayz_extract_server_auth')) {
    function hs_dayz_extract_server_auth() {
        return sanitize_text_field($_SERVER['CONTENT_TYPE'] ?? '');
    }
}
function hs_dayz_normalize_guid($guid) {
    // Keep = in base64 GUIDs
    return trim(preg_replace('/[^a-zA-Z0-9=_-]/', '', $guid));

}