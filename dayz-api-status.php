<?php
/**
 * Plugin Name: HS-DayZ API
 * Description: Manage and monitor DayZ servers from WordPress.
 * Version: 1.1.9
 * Author: Aw4k3n82
 */

defined('ABSPATH') || exit;

// 🛰️ Log all incoming REST API requests early
add_action('rest_api_loaded', function () {
    $upload_dir = wp_upload_dir();
    $log_dir    = trailingslashit($upload_dir['basedir']) . 'hs-dayz-logs';
    $log_file   = trailingslashit($log_dir) . 'raw-api.log';

    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $timestamp   = current_time('mysql');
    $ip          = $_SERVER['REMOTE_ADDR'] ?? 'undefined';
    $method      = $_SERVER['REQUEST_METHOD'] ?? 'undefined';
    $request_uri = $_SERVER['REQUEST_URI'] ?? 'undefined';
    $headers     = function_exists('getallheaders') ? getallheaders() : [];
    $raw_input   = file_get_contents('php://input');

    $log = "[$timestamp] $ip $method $request_uri\n";
    $log .= "Headers:\n" . print_r($headers, true);
    $log .= "Body:\n" . $raw_input . "\n";
    $log .= str_repeat('-', 60) . "\n\n";

    file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);
});

// ✅ Load Admin UI Files
foreach (['admin/dashboard-widget.php', 'admin/menu.php'] as $admin_file) {
    $path = plugin_dir_path(__FILE__) . $admin_file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ✅ Load includes FIRST (core dependencies)
$includes_files = [
    'includes/helpers.php', // must define hs_dayz_response()
    'includes/auth.php',    // must define hs_dayz_auth_server()
    'includes/authchecker.php',
];

foreach ($includes_files as $file) {
    $path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("[HS-DayZ] Missing include file: $file");
    }
}

// ✅ Load API endpoint handlers AFTER includes
$api_files = [
    'api/status.php',
    'api/api-logger.php',
    'api/globals.php',
    'api/players.php',
    'api/forward.php',
    'api/debug.php',
    'api/objects.php',
    'api/random.php',
    'api/query.php',
    'api/transactions.php',
    'api/logger.php',
    'api/discord.php',
];

foreach ($api_files as $file) {
    $path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("[HS-DayZ] Missing API file: $file");
    }
}

// ✅ Activation hook for initial DB setup
register_activation_hook(__FILE__, function () {
    hs_dayz_install_db();
    error_log("🧱 hs_dayz_install_db() ran via plugin activation.");
});

// ✅ Safety fallback in case DB tables are missing
// add_action('plugins_loaded', function () {
//     global $wpdb;
//     $check_table = $wpdb->prefix . 'hs_dayz_servers';
//     if ($wpdb->get_var("SHOW TABLES LIKE '$check_table'") !== $check_table) {
//         hs_dayz_install_db();
//         error_log("🔥 hs_dayz_install_db() auto-ran on plugins_loaded.");
//     }
// });

function hs_dayz_initialize_global_config() {
    global $wpdb;

    $table = $wpdb->prefix . 'hs_dayz_api_config';

    // Auto-generate ServerAuth if not exists
    $existing = $wpdb->get_var($wpdb->prepare("SELECT config_value FROM $table WHERE config_key = %s", 'ServerAuth'));
    if (!$existing) {
        $key = bin2hex(random_bytes(16)); // 32-char base64-ish key
        $wpdb->insert($table, [
            'config_key'   => 'ServerAuth',
            'config_value' => $key,
            'enabled'      => 1
        ]);
    }

    // Auto-fill DBServer and DB if not present
    $defaults = [
        'DBServer' => DB_HOST,
        'DB'       => DB_NAME,
        'IP'       => '0.0.0.0',
        'Port'     => 443,
        'LogToFile'=> 'false',
        'CheckForNewVersion' => 'true',
        'CreateIndexes'      => 'false',
        'AutoUpdate'         => 'false',
        'AllowClientWrite' => 'true',
    ];

    foreach ($defaults as $key => $value) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT config_value FROM $table WHERE config_key = %s", $key));
        if (!$exists) {
            $wpdb->insert($table, [
                'config_key'   => $key,
                'config_value' => $value,
                'enabled'      => 1
            ]);
        }
    }
}
add_action('plugins_loaded', 'hs_dayz_initialize_global_config');


// ✅ DB Schema Installer
function hs_dayz_install_db() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    $tables = [
        "CREATE TABLE {$prefix}hs_dayz_api_config (
            config_key   VARCHAR(100) NOT NULL,
            config_value TEXT NOT NULL,
            enabled      TINYINT(1) DEFAULT 1,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_key)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_discord_config (
            config_key   VARCHAR(191) NOT NULL,
            config_value TEXT DEFAULT NULL,
            enabled      TINYINT(1) DEFAULT 0,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_key)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_letsencrypt_config (
            config_key   VARCHAR(191) NOT NULL,
            config_value TEXT DEFAULT NULL,
            enabled      TINYINT(1) DEFAULT 0,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_key)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_translate_config (
            config_key   VARCHAR(191) NOT NULL,
            config_value TEXT DEFAULT NULL,
            enabled      TINYINT(1) DEFAULT 0,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_key)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_wit_config (
            config_key   VARCHAR(191) NOT NULL,
            config_value TEXT DEFAULT NULL,
            enabled      TINYINT(1) DEFAULT 0,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_key)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_luis_config (
            config_key   VARCHAR(191) NOT NULL,
            config_value TEXT DEFAULT NULL,
            enabled      TINYINT(1) DEFAULT 0,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_key)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_qna_config (
            config_key   VARCHAR(191) NOT NULL,
            config_value TEXT DEFAULT NULL,
            enabled      TINYINT(1) DEFAULT 0,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_key)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_banking_globals (
            config_key   VARCHAR(191) NOT NULL,
            config_value TEXT DEFAULT NULL,
            enabled      TINYINT(1) DEFAULT 0,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_key)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_status (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            server_uid VARCHAR(64) NOT NULL,
            ServerID VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            query_port INT,
            name VARCHAR(255),
            server_version VARCHAR(32),
            players INT,
            queue_players INT,
            max_players INT,
            game_time DATETIME,
            game_map VARCHAR(64),
            has_password TINYINT(1),
            is_first_person TINYINT(1),
            raw_json LONGTEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_territories (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            server_uid VARCHAR(64) NOT NULL,
            ServerID VARCHAR(255) NOT NULL,
            guid VARCHAR(128) NOT NULL,
            flag_x FLOAT,
            flag_y FLOAT,
            flag_z FLOAT,
            territory_name VARCHAR(255) NOT NULL,
            territory_owner VARCHAR(255) NOT NULL,
            members LONGTEXT NOT NULL,
            territory_data LONGTEXT NOT NULL,
            raw_json LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;",



        "CREATE TABLE {$prefix}hs_dayz_api_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            server_uid VARCHAR(64),
            mod_slug VARCHAR(64),
            ip_address VARCHAR(45),
            log_type VARCHAR(32) NOT NULL,
            message LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_players (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            guid VARCHAR(128) NOT NULL,
            auth VARCHAR(128),
            Banking LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_guid_mod (guid)
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_globals (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mod_slug VARCHAR(64) NOT NULL UNIQUE,
            data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_transactions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mod_slug VARCHAR(64) NOT NULL,
            server_uid VARCHAR(64),
            ip_address VARCHAR(45),
            payload LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;",

        "CREATE TABLE {$prefix}hs_dayz_objects (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            object_id VARCHAR(128) NOT NULL,
            mod_slug VARCHAR(64) NOT NULL,
            data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_object_mod (object_id, mod_slug)
        ) $charset_collate;"
    ];

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    error_log("🧱 hs_dayz_install_db() ran via plugin activation.");
}
