<?php
// REST API endpoints for dynamic mod player data
defined('ABSPATH') || exit;

if (!function_exists('hs_dayz_check_auth_guid')) {
    require_once plugin_dir_path(__FILE__) . '/../includes/authchecker.php';
}

add_action('rest_api_init', function () {
    $base = 'hs-dayz/v1';


    register_rest_route($base, '/Player/Load/(?P<guid>[a-zA-Z0-9=_-]+)/(?P<mod>[a-zA-Z0-9=_-]+)', [
        'methods'             => 'POST',
        'callback'            => 'hs_dayz_runget',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($base, '/Player/Save/(?P<guid>[a-zA-Z0-9=_-]+)/(?P<mod>[a-zA-Z0-9_-]+)', [
        'methods'             => 'POST',
        'callback'            => 'hs_dayz_runsave',
        'permission_callback' => '__return_true',
    ]);

});
// 📤 Load Player Data (Read-only)
function hs_dayz_runget($request) {
    global $wpdb;

    $guid  = hs_dayz_normalize_guid(sanitize_text_field($request->get_param('guid')));
    $mod   = sanitize_text_field($request->get_param('mod'));
    $auth  = hs_dayz_extract_auth_key();
    $table = $wpdb->prefix . 'hs_dayz_players';

    $raw_json = file_get_contents('php://input');
    $raw_data = json_decode($raw_json, true);

    error_log("🟡 hs_dayz_runget invoked for GUID: $guid | mod: $mod");
    error_log("🔐 Auth provided: $auth");
    error_log("📩 Raw JSON: $raw_json");

    // 🔐 AUTH: Allow if ServerAuth OR if auth matches player for this GUID
    $is_server = hs_dayz_check_server_auth($auth);
    $is_player = false;

    if (!$is_server) {
        $stored = $wpdb->get_var($wpdb->prepare(
            "SELECT auth FROM $table WHERE guid = %s", $guid
        ));
        $is_player = ($stored && $stored === $auth);
    }

    if (!$is_server && !$is_player) {
        error_log("❌ Auth failed for $guid — token does not match server or player.");
        return new WP_REST_Response(['error' => 'Unauthorized'], 401);
    }

    // ✅ Ensure mod column exists
    $columns = $wpdb->get_col("DESC $table", 0);
    if (!in_array($mod, $columns, true)) {
        error_log("❌ Mod column '$mod' does not exist.");
        return new WP_REST_Response(['error' => "Invalid mod: $mod"], 400);
    }

    // 🔍 Get player row — this endpoint should never create it
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE guid = %s", $guid), ARRAY_A);

    if (!$row) {
        error_log("❌ Player record not found for $guid. You must call /GetAuth/{guid} first.");
        return new WP_REST_Response(['error' => 'Player does not exist.'], 404);
    }

    // 🧾 If mod data already exists (not null), return it
    if (!is_null($row[$mod])) {
        error_log("📦 Found existing mod data for $guid ($mod). Returning.");
        return new WP_REST_Response(json_decode($row[$mod], true), 200);
    }

    // ✍️ If mod data is missing and allowed to write, insert it
    $allow_write = $is_server || hs_dayz_allow_client_write();

    if ($allow_write && !empty($raw_data)) {
        $result = $wpdb->update($table, [
            $mod         => $raw_json,
            'updated_at' => current_time('mysql')
        ], ['guid' => $guid]);

        if ($result === false) {
            error_log("❌ DB update failed: {$wpdb->last_error}");
            return new WP_REST_Response(['error' => 'Database error'], 500);
        }

        error_log("✅ First-time mod insert for $guid ($mod) successful.");
        return new WP_REST_Response($raw_data, 203);
    }

    // 🚫 Write not allowed or no data
    error_log("⚠️ Write skipped — not allowed or empty payload.");
    return new WP_REST_Response($raw_data, 203);
}




function hs_dayz_runsave($request) {
    global $wpdb;

    $guid       = hs_dayz_normalize_guid($request->get_param('guid'));
    $mod        = sanitize_text_field($request->get_param('mod'));
    $auth       = hs_dayz_extract_auth_key();
    $raw_body   = file_get_contents('php://input');
    $parsed_body = json_decode($raw_body, true);
    $table      = $wpdb->prefix . 'hs_dayz_players';

    error_log("🟡 [RUNSAVE] Incoming save for $guid | mod: $mod");
    error_log("🔐 [RUNSAVE] Auth provided: $auth");
    error_log("📩 [RUNSAVE] Raw JSON: $raw_body");

    // ✅ Validate mod
    $valid_columns = ['Banking', 'Territories', 'Vehicles'];
    if (!in_array($mod, $valid_columns, true)) {
        error_log("❌ [RUNSAVE] Invalid mod: $mod");
        return new WP_REST_Response(['Status' => 'Error', 'Error' => 'Invalid mod name.'], 400);
    }

    // 🔐 Auth validation
    $is_server = hs_dayz_check_server_auth($auth);
    $is_player = false;

    if (!$is_server) {
        $stored = $wpdb->get_var($wpdb->prepare("SELECT auth FROM $table WHERE guid = %s", $guid));
        $is_player = ($stored && $stored === $auth);
    }

    if (!$is_server && !$is_player) {
        error_log("❌ [RUNSAVE] Auth failed for $guid — not server or player.");
        return new WP_REST_Response($parsed_body, 401);
    }

    // 🚫 Prevent saving if GUID/SteamID are missing
    if (empty($parsed_body['GUID']) || empty($parsed_body['SteamID'])) {
        error_log("⚠️ [RUNSAVE] Missing GUID or SteamID in body. Skipping save.");
        return new WP_REST_Response($parsed_body, 203);
    }

    // 🔍 Confirm player exists
    $row = $wpdb->get_row($wpdb->prepare("SELECT guid FROM $table WHERE guid = %s", $guid));
    if (!$row) {
        error_log("❌ [RUNSAVE] Player $guid not found. You must call /GetAuth/{guid} first.");
        return new WP_REST_Response(['error' => 'Player does not exist.'], 404);
    }

    // ✏️ Update mod data
    $success = $wpdb->update($table, [
        $mod         => $raw_body,
        'updated_at' => current_time('mysql')
    ], ['guid' => $guid]) !== false;

    if ($success) {
        error_log("✅ [RUNSAVE] Updated $mod for $guid successfully.");
        return new WP_REST_Response($parsed_body, 200);
    } else {
        error_log("❌ [RUNSAVE] DB update failed for $guid. Error: {$wpdb->last_error}");
        return new WP_REST_Response(['error' => 'DB update failed.'], 500);
    }
}
