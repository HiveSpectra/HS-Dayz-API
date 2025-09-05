<?php
defined('ABSPATH') || exit;

// Handle log clearing
global $wpdb;
if (isset($_POST['hs_dayz_clear_log'])) {
    $wpdb->query("DELETE FROM {$wpdb->prefix}hs_dayz_api_log");
}
if (isset($_POST['hs_dayz_clear_debug_log'])) {
    @file_put_contents(WP_CONTENT_DIR . '/debug.log', '');
}
if (isset($_POST['hs_dayz_clear_raw_log'])) {
    $raw = WP_CONTENT_DIR . '/uploads/hs-dayz-logs/raw-api.log';
    if (file_exists($raw)) file_put_contents($raw, '');
}

add_action('wp_ajax_hs_dayz_get_raw_log', function () {
    $raw_file = WP_CONTENT_DIR . '/uploads/hs-dayz-logs/raw-api.log';

    if (!file_exists($raw_file)) {
        @mkdir(dirname($raw_file), 0755, true);
        @file_put_contents($raw_file, '');
    }

    if (file_exists($raw_file)) {
        wp_send_json_success(['log' => file_get_contents($raw_file)]);
    } else {
        wp_send_json_error(['message' => 'raw-api.log not found']);
    }
});





?>

<div class="wrap">
    <h1>📡 HS-DayZ API Monitor</h1>

    <form method="post">
        <input type="hidden" name="hs_dayz_clear_log" value="1">
        <button type="submit" class="button button-danger">🗑️ Clear Plugin Logs</button>
    </form>

    <h2>📦 Plugin Logs</h2>
    <label for="logFilter">Filter:</label>
    <select id="logFilter">
        <option value="">All</option>
        <option value="init">Init</option>
        <option value="raw">Raw</option>
        <option value="request">Request</option>
        <option value="response">Response</option>
    </select>
    <pre id="plugin-log" class="logbox">Loading...</pre>
    <button onclick="copyToClipboard('plugin-log')" class="button copy-btn">📋 Copy</button>

    <hr>

    <h2>🐞 debug.log</h2>
    <form method="post">
        <input type="hidden" name="hs_dayz_clear_debug_log" value="1">
        <button type="submit" class="button button-danger">🧹 Clear debug.log</button>
    </form>
    <pre id="debug-log" class="logbox">Loading...</pre>
    <button onclick="copyToClipboard('debug-log')" class="button copy-btn">📋 Copy</button>

    <hr>

    <h2>🟢 raw-api.log</h2>
    <form method="post">
        <input type="hidden" name="hs_dayz_clear_raw_log" value="1">
        <button type="submit" class="button button-danger">🧼 Clear raw-api.log</button>
    </form>
    <pre id="raw-log" class="logbox">Loading...</pre>
    <button onclick="copyToClipboard('raw-log')" class="button copy-btn">📋 Copy</button>

    <hr>

    <h2>🔁 Recent Transactions</h2>
    <div style="max-height:400px;overflow-y:auto;">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Mod</th>
                    <th>Server UID</th>
                    <th>IP</th>
                    <th>Time</th>
                    <th>Payload</th>
                </tr>
            </thead>
            <tbody id="transaction-body"><tr><td colspan="5"><em>Loading...</em></td></tr></tbody>
        </table>
    </div>
</div>

<style>
.logbox {
    padding: 15px;
    background: #111;
    color: #0ff;
    border: 1px solid #444;
    white-space: pre-wrap;
    font-size: 12px;
    overflow-y: scroll;
    height: 300px;
}
.copy-btn {
    margin-top: 6px;
    background: #007cba;
    color: #fff;
    border: none;
    padding: 5px 12px;
    border-radius: 4px;
}
.copy-btn:hover { background: #005fa3; }
</style>

<script>
function fetchLog(id, action) {
    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=' + action)
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById(id);
            box.textContent = data.success ? data.data.log : '❌ ' + (data.data?.message || 'Failed to load log');
        });
}

function copyToClipboard(id) {
    const el = document.getElementById(id);
    const range = document.createRange();
    range.selectNode(el);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    try {
        document.execCommand('copy');
        alert('✅ Copied!');
    } catch {
        alert('❌ Copy failed.');
    }
    window.getSelection().removeAllRanges();
}

function refreshPluginLogs() {
    const filter = document.getElementById('logFilter').value;
    fetch('<?php echo admin_url('admin-ajax.php?action=hs_dayz_get_monitor_data'); ?>')
        .then(res => res.json())
        .then(data => {
            const el = document.getElementById('plugin-log');
            el.textContent = '';
            data.data.logs.forEach(log => {
                if (!filter || log.log_type === filter) {
                    el.textContent += `[${log.log_type.toUpperCase()}] ${log.message}\n\n`;
                }
            });

            const tx = document.getElementById('transaction-body');
            tx.innerHTML = '';
            data.data.transactions.forEach(row => {
                tx.innerHTML += `
                    <tr>
                        <td>${row.mod_slug}</td>
                        <td>${row.server_uid}</td>
                        <td>${row.ip_address}</td>
                        <td>${row.created_at}</td>
                        <td><pre>${row.payload}</pre></td>
                    </tr>`;
            });
        });
}

document.getElementById('logFilter').addEventListener('change', refreshPluginLogs);

document.addEventListener('DOMContentLoaded', () => {
    refreshPluginLogs();
    fetchLog('debug-log', 'hs_dayz_get_debug_log');
    fetchLog('raw-log', 'hs_dayz_get_raw_log');
    setInterval(() => {
        refreshPluginLogs();
        fetchLog('debug-log', 'hs_dayz_get_debug_log');
        fetchLog('raw-log', 'hs_dayz_get_raw_log');
    }, 3000);
});
</script>
