<?php
defined('ABSPATH') || exit;

// 📦 Minimal embedded JWT library (Firebase-like)
// if (!class_exists('JWT')) {
//     class JWT {
//         public static function encode($payload, $key, $alg = 'HS256') {
//             $header = ['typ' => 'JWT', 'alg' => $alg];
//             $segments = [
//                 self::urlsafeB64Encode(json_encode($header)),
//                 self::urlsafeB64Encode(json_encode($payload))
//             ];
//             $signing_input = implode('.', $segments);
//             $signature = self::sign($signing_input, $key, $alg);
//             $segments[] = self::urlsafeB64Encode($signature);
//             return implode('.', $segments);
//         }

//         public static function decode($jwt, $key, $alg = 'HS256') {
//             $parts = explode('.', $jwt);
//             if (count($parts) !== 3) {
//                 throw new Exception('Invalid token format');
//             }
//             [$header64, $payload64, $sig64] = $parts;

//             $sig_input    = "$header64.$payload64";
//             $expected_sig = self::sign($sig_input, $key, $alg);
//             $provided_sig = self::urlsafeB64Decode($sig64);

//             if (!hash_equals($expected_sig, $provided_sig)) {
//                 throw new Exception('Invalid signature');
//             }

//             $payload = json_decode(self::urlsafeB64Decode($payload64), true);
//             if (isset($payload['exp']) && time() > $payload['exp']) {
//                 throw new Exception('Token expired');
//             }

//             return $payload;
//         }

//         private static function urlsafeB64Encode($data) {
//             return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
//         }

//         private static function urlsafeB64Decode($data) {
//             return base64_decode(strtr($data, '-_', '+/'));
//         }

//         private static function sign($input, $key, $alg) {
//             if ($alg === 'HS256') {
//                 return hash_hmac('sha256', $input, $key, true);
//             }
//             throw new Exception('Unsupported algorithm');
//         }
//     }
// }

// 🔐 AUTHENTICATION HELPERS

/**
 * Extract ServerAuth key from headers (mod incorrectly uses Content-Type)
 */
function hs_dayz_extract_auth_key() {
    return sanitize_text_field(
        $_SERVER['HTTP_AUTH_KEY'] ?? ($_SERVER['CONTENT_TYPE'] ?? '')
    );
}

/**
 * JWT signing key used for player tokens
 */
// function hs_dayz_get_jwt_key() {
//     $key = get_option('hs_dayz_jwt_secret');
//     if (!$key) {
//         $key = bin2hex(random_bytes(32));
//         update_option('hs_dayz_jwt_secret', $key);
//     }
//     return $key;
// }

/**
 * Static shared server-server auth (fallback only)
 */
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

/**
 * Validate token without GUID restriction
 */
// function hs_dayz_check_auth($token) {
//     try {
//         JWT::decode($token, hs_dayz_get_jwt_key());
//         return true;
//     } catch (Exception $e) {
//         return false;
//     }
// }

/**
 * Validate token with GUID requirement
 */
// function hs_dayz_check_auth_guid($token, $guid) {
//     try {
//         $decoded = JWT::decode($token, hs_dayz_get_jwt_key());
//         return isset($decoded['GUID']) && $decoded['GUID'] === $guid;
//     } catch (Exception $e) {
//         return false;
//     }
// }

/**
 * Extract GUID from JWT
 */
// function hs_dayz_extract_guid($token) {
//     try {
//         $decoded = JWT::decode($token, hs_dayz_get_jwt_key());
//         return $decoded['GUID'] ?? null;
//     } catch (Exception $e) {
//         return null;
//     }
// }

/**
 * Generate a signed token from GUID
 */
// function hs_dayz_make_token($guid, $days_valid = 10) {
//     $payload = [
//         'GUID' => $guid,
//         'exp'  => time() + ($days_valid * 86400)
//     ];
//     return JWT::encode($payload, hs_dayz_get_jwt_key());
// }

/**
 * 🔐 Primary auth check — token-only (ServerAuth)
 */
function hs_dayz_auth_server(string $server_auth): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_dayz_servers
         WHERE ServerAuth = %s AND enabled = 1",
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
        "SELECT * FROM {$wpdb->prefix}hs_dayz_servers WHERE ServerAuth = %s AND enabled = 1",
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

    // Try to decode JWT and confirm GUID matches
    // try {
    //     $payload = JWT::decode($auth, hs_dayz_get_jwt_key());
    //     if (!isset($payload['GUID']) || $payload['GUID'] !== $guid) {
    //         return false;
    //     }
    // } catch (Exception $e) {
    //     return false;
    // }

    // Compare raw stored auth token (NOT hashed anymore)
    $stored = $wpdb->get_var($wpdb->prepare(
        "SELECT auth FROM {$table} WHERE guid = %s", $guid
    ));

    return $stored && $stored === $auth;
}

//End theory testing








// function hs_dayz_validate_player_auth($guid, $auth) {
//     global $wpdb;
//     $table = $wpdb->prefix . 'hs_dayz_players';

//     $stored_auth = $wpdb->get_var($wpdb->prepare(
//         "SELECT auth FROM {$table} WHERE guid = %s", $guid
//     ));

//     return $stored_auth && $stored_auth === $auth;
// }
function hs_dayz_allow_client_write() {
    global $wpdb;

    $value = $wpdb->get_var("SELECT AllowClientWrite FROM {$wpdb->prefix}hs_dayz_api_config LIMIT 1");

    return $value === 'true' || $value === '1' || $value === 1;
}
