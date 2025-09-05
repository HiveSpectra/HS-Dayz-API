<?php
global $wpdb;
$table = $wpdb->prefix . 'hs_dayz_letsencrypt_config'; // fixed correct prefix

// Ensure default row exists
$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE config_key = %s", '__enabled'));
if (!$exists) {
    $wpdb->insert($table, [
        'config_key'   => '__enabled',
        'config_value' => '',
        'enabled'      => 0,
        'updated_at'   => current_time('mysql')
    ]);
}

// Load current state
$row = $wpdb->get_row("SELECT enabled FROM $table WHERE config_key = '__enabled' LIMIT 1", ARRAY_A);
$enabled = isset($row['enabled']) ? (bool)$row['enabled'] : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['letsencrypt_config_nonce']) && wp_verify_nonce($_POST['letsencrypt_config_nonce'], 'save_letsencrypt_config')) {
    $wpdb->replace($table, [
        'config_key'   => '__enabled',
        'config_value' => '',
        'enabled'      => isset($_POST['letsencrypt_enabled']) ? 1 : 0,
        'updated_at'   => current_time('mysql')
    ]);
    echo '<div class="updated"><p>LetsEncrypt config saved.</p></div>';
    $enabled = isset($_POST['letsencrypt_enabled']);
}
?>

<div class="wrap">
    <h2>LetsEncrypt Settings</h2>
    <form method="post">
        <?php wp_nonce_field('save_letsencrypt_config', 'letsencrypt_config_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row">Enable LetsEncrypt</th>
                <td>
                    <label class="switch">
                        <input type="checkbox" name="letsencrypt_enabled" <?php echo $enabled ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                    <span style="margin-left: 8px; color: gray;"><?php echo $enabled ? 'true' : 'false'; ?></span>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="Save Changes" />
        </p>
    </form>
</div>

<!-- Slider toggle styles -->
<style>
.switch {
  position: relative; display: inline-block; width: 50px; height: 24px;
}
.switch input {
  opacity: 0; width: 0; height: 0;
}
.slider {
  position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
  background-color: #ccc; transition: .4s; border-radius: 24px;
}
.slider:before {
  position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
  background-color: white; transition: .4s; border-radius: 50%;
}
input:checked + .slider {
  background-color: #2196F3;
}
input:checked + .slider:before {
  transform: translateX(26px);
}
</style>
