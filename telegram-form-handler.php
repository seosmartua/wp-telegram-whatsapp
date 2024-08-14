<?php

if (!defined('ABSPATH')) {
    exit; // Запобігаємо прямому доступу
}

if (!wp_next_scheduled('telegram_check_new_entries')) {
    wp_schedule_event(time(), 'hourly', 'telegram_check_new_entries');
}

add_action('telegram_check_new_entries', 'check_new_entries_for_telegram');

function check_new_entries_for_telegram() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wws_analytics';

    // Отримуємо останній переглянутий ID запису
    $last_checked_id = get_option('telegram_last_checked_id', 0);

    // Запит до бази даних для отримання нових записів
    $query = "SELECT * FROM $table_name WHERE id > $last_checked_id";
    $new_entries = $wpdb->get_results($query);

    if (!empty($new_entries)) {
        foreach ($new_entries as $entry) {
            // Форматуємо дані для відправки у Telegram
            $data = [
                'visitor_ip' => $entry->visitor_ip,
                'number' => $entry->number,
                'message' => $entry->message,
                'referral' => $entry->referral,
                'device_type' => $entry->device_type,
                'date' => $entry->date,
                'timestamp' => $entry->timestamp,
            ];

            // Відправляємо дані у Telegram
            wp_telegram_form_sender_send($data);

            // Оновлюємо останній переглянутий ID запису
            update_option('telegram_last_checked_id', $entry->id);
        }
    }
}
