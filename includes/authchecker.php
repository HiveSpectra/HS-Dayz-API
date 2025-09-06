<?php
defined('ABSPATH') || exit;


function hs_dayz_extract_auth_key() {
    return sanitize_text_field(
        $_SERVER['HTTP_AUTH_KEY'] ?? ($_SERVER['CONTENT_TYPE'] ?? '')
    );
}


function hs_dayz_get_auth_key() {
    global $wpdb;
    return $wpdb->get_var("
        SELECT config_value FROM {$wpdb->prefix}hs_dayz_api_config
        WHERE config_key = 'ServerAuth' AND enabled = 1
        LIMIT 1
    ");
}


/**
 * Direct check against shared static auth key
 */
function hs_dayz_check_server_auth($auth_header) {
    return hash_equals(hs_dayz_get_auth_key(), $auth_header);
}



function hs_dayz_auth_server(string $server_auth): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT *
           FROM {$wpdb->prefix}hs_dayz_api_config
          WHERE config_key = 'ServerAuth'
            AND config_value = %s
            AND enabled = 1
          LIMIT 1",
        $server_auth
    ));
}



/**
 * 🔐 Alt usage: just authenticate by ServerAuth
 */
function hs_dayz_auth_by_serverauth($ServerAuth) {
    global $wpdb;

    if (empty($ServerAuth)) {
        return false;
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT *
         FROM {$wpdb->prefix}hs_dayz_api_config
         WHERE config_key = 'ServerAuth'
           AND config_value = %s
           AND enabled = 1
         LIMIT 1",
        $ServerAuth
    ));
}

// testing ChatGPT JWT theory here
function hs_dayz_validate_player_auth($guid, $auth) {
    global $wpdb;
    $table = $wpdb->prefix . 'hs_dayz_players';

    // Do not allow server auth to be used as player token
    if (hs_dayz_check_server_auth($auth)) {
        return false;
    }

    
    // Compare raw stored auth token (NOT hashed anymore)
    $stored = $wpdb->get_var($wpdb->prepare(
        "SELECT auth FROM {$table} WHERE guid = %s", $guid
    ));

    return $stored && $stored === $auth;
}

//End theory testing

function hs_dayz_allow_client_write() {
    global $wpdb;
    $value = $wpdb->get_var("
        SELECT config_value
          FROM {$wpdb->prefix}hs_dayz_api_config
         WHERE config_key = 'AllowClientWrite'
           AND enabled = 1
         LIMIT 1
    ");
    return $value === 'true' || $value === '1' || $value === 1;
}

