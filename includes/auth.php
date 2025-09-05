<?php

// REST API endpoints for dynamic mod player data
defined('ABSPATH') || exit;


add_action('rest_api_init', function () {
    $base = 'hs-dayz/v1';

    register_rest_route($base, '/GetAuth/(?P<guid>[a-zA-Z0-9=_-]+)', [
        'methods'  => ['GET', 'POST'],
        'callback' => 'hs_dayz_runget_auth',
        'permission_callback' => '__return_true',
    ]);

    

});

function hs_dayz_runget_auth($request) {
    global $wpdb;

    $guid  = hs_dayz_normalize_guid(sanitize_text_field($request->get_param('guid')));
    $table = $wpdb->prefix . 'hs_dayz_players';

    error_log("🟡 [RUNGET_AUTH] Incoming request for GUID: $guid");

    $row = $wpdb->get_row($wpdb->prepare("SELECT auth FROM {$table} WHERE guid = %s", $guid));

    // If player exists, return existing token
    if ($row) {
        error_log("🔄 [RUNGET_AUTH] Player already exists — returning existing token");
        return new WP_REST_Response([
            'GUID' => $guid,
            'AUTH' => $row->auth
        ], 200);
    }

    // Create new token and insert new row
    $raw_token = base64_encode(random_bytes(32));
    $now = current_time('mysql');

    $result = $wpdb->insert($table, [
        'guid'       => $guid,
        'auth'       => $raw_token,
        'Banking'    => null,
        'created_at' => $now,
        'updated_at' => $now
    ]);

    if ($result === false) {
        error_log("❌ [RUNGET_AUTH] INSERT failed for $guid | Error: {$wpdb->last_error}");
        return new WP_REST_Response(['error' => 'Database insert failed'], 500);
    }

    error_log("✅ [RUNGET_AUTH] Inserted new player with token for $guid");

    return new WP_REST_Response([
        'GUID' => $guid,
        'AUTH' => $raw_token
    ], 200);
}


