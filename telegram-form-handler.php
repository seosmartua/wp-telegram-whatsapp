<?php

if (!defined('ABSPATH')) {
    exit; // Запобігаємо прямому доступу
}

require_once plugin_dir_path(__FILE__) . 'telegram-functions.php';

add_action('init', 'check_new_entries_for_telegram');

if (!function_exists('check_new_entries_for_telegram')) {
    function check_new_entries_for_telegram() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wws_analytics';

        $last_checked_id = get_last_sent_id();
        $query = "SELECT * FROM $table_name WHERE id > $last_checked_id ORDER BY id ASC";
        $new_entries = $wpdb->get_results($query);

        if (!empty($new_entries)) {
            foreach ($new_entries as $entry) {
                $data = [
                    'visitor_ip' => $entry->visitor_ip,
                    'number' => $entry->number,
                    'message' => $entry->message,
                    'referral' => $entry->referral,
                    'device_type' => $entry->device_type,
                    'date' => $entry->date,
                    'timestamp' => $entry->timestamp,
                ];

                $response = wp_telegram_form_sender_send($data);

                if (!is_wp_error($response)) {
                    set_last_sent_id($entry->id);
                    save_last_sent_status('success');
                } else {
                    save_last_sent_status('error');
                }
            }
        } else {
            save_last_sent_status('no new entries');
        }
    }
}
