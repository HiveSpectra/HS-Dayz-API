












































<!-- // ✅ Benefits:
You can build a full admin UI tab (banking-settings-tab.php) with text fields, sliders, and repeaters.

Admins can update bank config live through WordPress.

You can keep using WordPress-native methods for DB access and config updates.

It's versionable (you can store "ConfigVersion": "2" explicitly).

🧠 Example reconstruction in PHP: -->


<!-- $results = $wpdb->get_results("SELECT config_key, config_value FROM wp_hs_dayz_banking_globals");
$response = [];
foreach ($results as $row) {
    $key = $row->config_key;
    $val = json_decode($row->config_value, true);
    $response[$key] = $val === null ? $row->config_value : $val;
}
$response['m_DataReceived'] = 0;
return new WP_REST_Response($response, 200); -->
