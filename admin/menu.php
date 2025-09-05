<?php
defined('ABSPATH') || exit;

// ✅ AJAX endpoints for monitor-tab auto-refresh
add_action('wp_ajax_hs_dayz_get_debug_log', function () {
    $debug_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($debug_file)) {
        wp_send_json_success(['log' => file_get_contents($debug_file)]);
    }
    wp_send_json_error(['message' => 'debug.log not found']);
});

add_action('wp_ajax_hs_dayz_get_monitor_data', function () {
    global $wpdb;

    $logs = $wpdb->get_results("
        SELECT message, log_type 
        FROM {$wpdb->prefix}hs_dayz_api_log 
        WHERE log_type IN ('raw', 'request', 'response') 
        ORDER BY created_at DESC 
        LIMIT 100
    ");

    $transactions = $wpdb->get_results("
        SELECT * 
        FROM {$wpdb->prefix}hs_dayz_transactions 
        ORDER BY created_at DESC 
        LIMIT 50
    ");

    wp_send_json_success([
        'logs' => $logs,
        'transactions' => $transactions
    ]);
});

// ✅ Admin menu UI
add_action('admin_menu', 'hs_dayz_admin_menu');

function hs_dayz_admin_menu() {
    add_menu_page(
        'HS-DayZ API ',
        'HS-DayZ API',
        'manage_options', // use 'read' only if non-admins need access
        'hs_dayz_dashboard',
        'hs_dayz_admin_dashboard',
        'dashicons-rest-api',
        60
    );
}

function hs_dayz_admin_dashboard() {
    $tab = $_GET['tab'] ?? 'monitor';
    $tabs = [
        
        
        'players-bank'        => 'Players Bank Accounts',
        'api-config'          => 'API Config',
        'discord-settings'    => 'Discord Settings',
        'luis'                => 'LUIS',
        'wit'                 => 'WIT',
        'translate'           => 'Translate',
        'qna'                 => 'QnA',
        'letsencrypt'         => 'LetsEncrypt', 
        'globals'             => 'Globals Settings',
        'banking-settings'    => 'Banking Settings',
        'monitor'             => 'Monitor',
    ];

    echo '<div class="wrap"><h1>Admin Dashboard</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $key => $label) {
        $active = ($tab === $key) ? ' nav-tab-active' : '';
        $url = admin_url("admin.php?page=hs_dayz_dashboard&tab=$key");
        echo "<a href='" . esc_url($url) . "' class='nav-tab$active'>" . esc_html($label) . '</a>';
    }
    echo '</h2>';

    $admin_tab_path = plugin_dir_path(__FILE__) . 'admin-tabs/' . $tab . '-tab.php';
    if (file_exists($admin_tab_path)) {
        include $admin_tab_path;
    } else {
        echo '<p>Coming soon...</p>';
    }
    echo '</div>';
}




?>
