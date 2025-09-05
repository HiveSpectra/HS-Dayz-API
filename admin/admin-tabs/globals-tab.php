<?php
defined('ABSPATH') || exit;
global $wpdb;

// ✅ AJAX save handler for global configs
add_action('wp_ajax_hs_dayz_update_global_mod', function () {
    global $wpdb;

    $mod  = sanitize_text_field($_POST['mod'] ?? '');
    $data = wp_unslash($_POST['data'] ?? '');

    if (!$mod || !$data) {
        wp_send_json_error(['message' => 'Missing mod or data']);
    }

    $decoded = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Invalid JSON: ' . json_last_error_msg()]);
    }

    $table = "{$wpdb->prefix}hs_dayz_globals";
    $json  = wp_json_encode($decoded);

    // 🐞 Debug logging
    error_log("[HS-DayZ] Update requested for mod: $mod");
    error_log("[HS-DayZ] JSON data: $json");

    $updated = $wpdb->update(
        $table,
        ['data' => $json],
        ['mod_slug' => $mod],
        ['%s'],
        ['%s']
    );

    if ($updated === false) {
        wp_send_json_error(['message' => 'Database update failed']);
    }

    // If no row was updated, insert it if it doesn't exist
    if ($updated === 0) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE mod_slug = %s", $mod));
        if (!$exists) {
            $inserted = $wpdb->insert($table, [
                'mod_slug' => $mod,
                'data'     => $json,
            ], ['%s', '%s']);

            if ($inserted === false) {
                wp_send_json_error(['message' => 'Row did not exist and insert failed']);
            }
        }
    }

    // ✅ Unified "Saved" response
    wp_send_json_success(['message' => '✅ Saved']);
});

// 🔄 Load and render global configs
$results = $wpdb->get_results("SELECT mod_slug, data FROM {$wpdb->prefix}hs_dayz_globals ORDER BY mod_slug ASC");

echo '<h2>🛠️ Edit Global Configs</h2>';
echo '<p>You can edit each configuration block. Click edit, update JSON, and save.</p>';

foreach ($results as $row):
    $mod = sanitize_text_field($row->mod_slug);
    $data = json_decode($row->data, true);
    $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $safe_id = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $mod));
?>

<details style="margin-bottom:20px;">
  <summary><strong><?= esc_html($mod) ?></strong></summary>

  <div id="<?= $safe_id ?>_view">
    <pre><?= esc_html($json) ?></pre>
    <button class="button edit-btn" data-mod="<?= esc_attr($mod) ?>" data-id="<?= esc_attr($safe_id) ?>">✏️ Edit</button>
  </div>

  <div id="<?= $safe_id ?>_edit" style="display:none;">
    <textarea id="<?= $safe_id ?>_json" rows="15" class="widefat"><?= esc_textarea($json) ?></textarea>
    <button class="button button-primary save-btn" data-mod="<?= esc_attr($mod) ?>" data-id="<?= esc_attr($safe_id) ?>">💾 Save</button>
    <button class="button cancel-btn" data-id="<?= esc_attr($safe_id) ?>">❌ Cancel</button>
  </div>
</details>

<?php endforeach; ?>

<!-- AJAX Support -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    jQuery('.edit-btn').on('click', function () {
        const id = jQuery(this).data('id');
        jQuery(`#${id}_view`).hide();
        jQuery(`#${id}_edit`).show();
    });

    jQuery('.cancel-btn').on('click', function () {
        const id = jQuery(this).data('id');
        jQuery(`#${id}_edit`).hide();
        jQuery(`#${id}_view`).show();
    });

    jQuery('.save-btn').on('click', function () {
        const mod = jQuery(this).data('mod');
        const id = jQuery(this).data('id');
        const json = jQuery(`#${id}_json`).val();

        try {
            JSON.parse(json);
        } catch (e) {
            alert("❌ Invalid JSON: " + e.message);
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'hs_dayz_update_global_mod',
            mod: mod,
            data: json
        }, function (response) {
            if (response.success) {
                alert(response.data.message || '✅ Saved');
                jQuery(`#${id}_edit`).hide();
                jQuery(`#${id}_view`).show();
                jQuery(`#${id}_view pre`).text(json); // update view block live
            } else {
                alert('❌ Failed to save: ' + (response.data?.message || 'Unknown error'));
            }
        });
    });
});
</script>
<script>
jQuery(document).ready(function ($) {
  $('#save-banking-config').on('click', function (e) {
    e.preventDefault();

    const configData = {
      ConfigVersion: "2",
      BankName: $('#bank_name').val(),
      StartingBalance: parseInt($('#starting_balance').val(), 10),
      StartingLimit: parseInt($('#starting_limit').val(), 10),
      CanDepositRuinedBills: $('#can_deposit_ruined').is(':checked') ? 1 : 0,
      MenuThemeColour: [
        parseInt($('#color_r').val(), 10),
        parseInt($('#color_g').val(), 10),
        parseInt($('#color_b').val(), 10)
      ],
      MoneyValues: [
        {
          Item: $('#money_item_name').val(),
          Value: parseFloat($('#money_item_value').val())
        }
      ]
    };

    $.post(ajaxurl, {
      action: 'hs_dayz_update_global_mod',
      mod: 'Banking',
      data: JSON.stringify(configData)
    }, function (response) {
      if (response?.success) {
        alert('✅ Banking config saved.');
      } else {
        alert('❌ Error: ' + (response?.data?.message || 'Unknown'));
      }
    });
  });
});

</script>
