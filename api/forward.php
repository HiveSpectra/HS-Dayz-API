<?php
// forward-endpoint.php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    register_rest_route('hs-dayz/v1', '/forward', [
        'methods'             => 'POST',
        'callback'            => 'hs_dayz_forward_request_handler',
        'permission_callback' => '__return_true',
    ]);
});

function hs_dayz_get_real_ip() {
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ??
           $_SERVER['HTTP_X_FORWARDED_FOR'] ??
           $_SERVER['REMOTE_ADDR'] ??
           'unknown';
}

function hs_dayz_forward_request_handler($request) {
    $params = hs_dayz_get_fallback_json($request);

    $url         = esc_url_raw($params['URL'] ?? '');
    $method      = strtoupper($params['Method'] ?? 'GET');
    $headers_raw = $params['Headers'] ?? [];
    $body        = $params['Body'] ?? '';
    $return_key  = $params['ReturnValue'] ?? '';
    $array_index = isset($params['ReturnValueArrayIndex']) ? intval($params['ReturnValueArrayIndex']) : null;

    // Build headers
    $headers = [];
    foreach ($headers_raw as $header) {
        $key   = sanitize_text_field($header['Key']);
        $value = sanitize_text_field($header['Value']);
        if ($key !== '') {
            $headers[$key] = $value;
        }
    }

    // Log IP and request details
    $real_ip = hs_dayz_get_real_ip();
    $log_line = "[" . current_time('mysql') . "] IP: $real_ip | Method: $method | URL: $url\n";
    file_put_contents(WP_CONTENT_DIR . '/uploads/hs-dayz-logs/ip-capture.log', $log_line, FILE_APPEND | LOCK_EX);

    // Send request to external URL
    $response = wp_remote_request($url, [
        'method'  => $method,
        'headers' => $headers,
        'body'    => $body,
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        if (function_exists('hs_dayz_log_api_response')) {
            hs_dayz_log_api_response('forward', ['error' => $error_message]);
        }
        return new WP_REST_Response(['error' => 'Request failed', 'message' => $error_message], 500);
    }

    $json = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($json)) {
        $msg = 'Invalid JSON response';
        if (function_exists('hs_dayz_log_api_response')) {
            hs_dayz_log_api_response('forward', ['error' => $msg]);
        }
        return new WP_REST_Response(['error' => $msg], 500);
    }

    // Extract return value
    $result = $json;
    if ($return_key !== '') {
        $result = $json[$return_key] ?? $json;
        if (is_array($result) && $array_index !== null && isset($result[$array_index])) {
            $result = $result[$array_index];
        }
    } elseif ($array_index !== null && isset($json[$array_index])) {
        $result = $json[$array_index];
    }

    if (function_exists('hs_dayz_log_api_response')) {
        hs_dayz_log_api_response('forward', ['url' => $url, 'response' => $result]);
    }

    return new WP_REST_Response($result, 200);
}
