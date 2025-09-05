
<?php
global $wpdb;
$table = $wpdb->prefix . 'hs_dayz_discord_config';

// 🔧 Ensure default config rows exist
$default_fields = [
    'Client_Id' => '',
    'Client_Secret' => '',
    'Bot_Token' => '',
    'Guild_Id' => '',
    'Required_Role' => '',
    'BlackList_Role' => '',
    'Restrict_Sign_Up' => '',
    'Restrict_Sign_Up_Countries' => '',
    'AllowToReRegister' => '',
    '__enabled' => ''
];

foreach ($default_fields as $key => $default_value) {
    $count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE config_key = %s", $key)));
        if ($count === 0) {

        $wpdb->insert($table, [
            'config_key'   => $key,
            'config_value' => maybe_serialize($default_value),
            'enabled'      => 0,
            'updated_at'   => current_time('mysql')
        ]);
    }
}


// Load existing config into $config_map
$config_map = [];
$results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
foreach ($results as $row) {
    $config_map[$row['config_key']] = $row;
}

// Helper
function get_discord_value($key, $default = '') {
    global $config_map;
    return $config_map[$key]['config_value'] ?? $default;
}
function get_discord_enabled($key) {
    global $config_map;
    return !empty($config_map[$key]['enabled']);
}

// Save handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discord_config_nonce']) && wp_verify_nonce($_POST['discord_config_nonce'], 'save_discord_config')) {
    $fields = [
        'Client_Id', 'Client_Secret', 'Bot_Token', 'Guild_Id', 'Required_Role',
        'BlackList_Role', 'Restrict_Sign_Up', 'Restrict_Sign_Up_Countries', 'AllowToReRegister'
    ];

    foreach ($fields as $key) {
        $value = sanitize_text_field($_POST[$key] ?? '');
        $enabled = in_array($key, ['Restrict_Sign_Up', 'AllowToReRegister']) ? (isset($_POST[$key]) ? 1 : 0) : 0;

        $wpdb->replace($table, [
            'config_key'   => $key,
            'config_value' => maybe_serialize($value),
            'enabled'      => $enabled,
            'updated_at'   => current_time('mysql')
        ]);
    }

    // Top-level enable slider
    $wpdb->replace($table, [
        'config_key'   => '__enabled',
        'config_value' => '',
        'enabled'      => isset($_POST['discord_enabled']) ? 1 : 0,
        'updated_at'   => current_time('mysql')
    ]);
    echo '<div class="updated"><p>Discord config saved.</p></div>';
}

$enabled = get_discord_enabled('__enabled');
?>

<div class="wrap">
    <h2>Discord Settings</h2>

    <form method="post">
        <?php wp_nonce_field('save_discord_config', 'discord_config_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">Enable Discord Integration</th>
                <td>
                    <label class="switch">
                        <input type="checkbox" name="discord_enabled" <?php checked($enabled); ?>>
                        <span class="slider round"></span>
                    </label>
                    <span style="margin-left: 8px; color: gray;"><?php echo $enabled ? 'true' : 'false'; ?></span>
                </td>
            </tr>

            <?php
            $text_fields = [
                'Client_Id', 'Client_Secret', 'Bot_Token', 'Guild_Id', 'Required_Role', 'BlackList_Role'
            ];
            foreach ($text_fields as $key) {
                echo "<tr><th scope='row'>$key</th><td><input type='text' name='$key' value='" . esc_attr(get_discord_value($key)) . "' class='regular-text' /></td></tr>";
            }

            // Checkboxes for booleans
            $checkboxes = ['Restrict_Sign_Up', 'AllowToReRegister'];
            foreach ($checkboxes as $key) {
                $checked = get_discord_enabled($key);
                echo "<tr><th scope='row'>$key</th><td><input type='checkbox' name='$key' value='1' " . checked($checked, true, false) . " /></td></tr>";
            }

            // Countries (comma-separated)
            $countries = maybe_unserialize(get_discord_value('Restrict_Sign_Up_Countries', ''));
            $countries_str = is_array($countries) ? implode(',', $countries) : $countries;
            ?>
            <tr>
                <th scope="row">Restrict_Sign_Up_Countries</th>
                <td>
                    <input type="text" name="Restrict_Sign_Up_Countries" value="<?php echo esc_attr($countries_str); ?>" class="regular-text" />
                    <p class="description">Comma-separated country codes (e.g., US,CA,GB)</p>
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
