<?php
defined('ABSPATH') || exit;

global $wpdb;
$table = $wpdb->prefix . 'hs_dayz_api_config';

// Handle regenerate
if (isset($_POST['regenerate_serverauth'])) {
    $new_key = bin2hex(random_bytes(16));
    $wpdb->update($table, [
        'config_value' => $new_key
    ], [
        'config_key' => 'ServerAuth'
    ]);
    echo '<div class="notice notice-success"><p>✅ ServerAuth regenerated successfully.</p></div>';
}

// Save changes
if (isset($_POST['save_api_config'])) {
    foreach ($_POST['config'] as $key => $value) {
        if ($value === 'true' || $value === 'false') {
            $value = isset($_POST['enabled'][$key]) ? 'true' : 'false';
        }
        $wpdb->update($table, [
            'config_value' => sanitize_text_field($value),
            'enabled'      => 1
        ], ['config_key' => sanitize_text_field($key)]);
    }
    echo '<div class="notice notice-success"><p>✅ Settings updated.</p></div>';
}

// Load current values
$results = $wpdb->get_results("SELECT * FROM $table ORDER BY config_key ASC");
?>

<div class="wrap">
    <h1>API Configuration</h1>
    <form method="post">
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Toggle</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><code><?php echo esc_html($row->config_key); ?></code></td>
                        <td>
                            <?php if ($row->config_value === 'true' || $row->config_value === 'false'): ?>
                                <input type="hidden" name="config[<?php echo esc_attr($row->config_key); ?>]" value="true">
                            <?php else: ?>
                                <input type="text" name="config[<?php echo esc_attr($row->config_key); ?>]" value="<?php echo esc_attr($row->config_value); ?>" style="width:100%;">
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($row->config_value === 'true' || $row->config_value === 'false'): ?>
                                <label class="switch">
                                    <input type="checkbox" name="enabled[<?php echo esc_attr($row->config_key); ?>]" <?php checked($row->config_value, 'true'); ?> onchange="this.nextElementSibling.nextElementSibling.textContent = this.checked ? 'true' : 'false';">
                                    <span class="slider round"></span>
                                    <span class="toggle-label"><?php echo $row->config_value === 'true' ? 'true' : 'false'; ?></span>
                                </label>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row->config_key === 'ServerAuth'): ?>
                                <button type="submit" name="regenerate_serverauth" class="button button-secondary" onclick="return confirm('Regenerate ServerAuth key?')">Regenerate</button>
                                <button type="button" class="button button-primary" onclick="copyToClipboard('<?php echo esc_js($row->config_value); ?>')">Copy</button>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:20px;">
            <button type="submit" name="save_api_config" class="button button-primary">💾 Save Settings</button>
        </p>
    </form>
</div>

<style>
.switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 24px;
    margin-right: 8px;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}
input:checked + .slider {
    background-color: #0073aa;
}
input:checked + .slider:before {
    transform: translateX(24px);
}
.toggle-label {
    font-size: 11px;
    color: #777;
    display: inline-block;
    min-width: 36px;
    text-align: left;
}
</style>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("✅ ServerAuth copied to clipboard!");
    }).catch(err => {
        alert("❌ Copy failed: " + err);
    });
}
</script>
