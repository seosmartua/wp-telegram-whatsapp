<?php

if (!defined('ABSPATH')) {
    exit; // Запобігаємо прямому доступу
}

/**
 * Зберігає останній статус відправки повідомлень у Telegram
 *
 * @param string $status Статус (success, error, no new entries тощо)
 */
function save_last_sent_status($status) {
    update_option('telegram_last_send_status', $status);
    update_option('telegram_last_send_time', current_time('mysql'));
}

/**
 * Отримує останній статус відправки повідомлень
 *
 * @return string Статус відправки
 */
function get_last_sent_status() {
    return get_option('telegram_last_send_status', 'No data sent yet.');
}

/**
 * Отримує дату і час останньої успішної відправки
 *
 * @return string Час останньої відправки
 */
function get_last_sent_time() {
    return get_option('telegram_last_send_time', 'Never sent.');
}

/**
 * Отримує ID останнього відправленого запису
 *
 * @return int ID запису
 */
function get_last_sent_id() {
    return get_option('telegram_last_checked_id', 0);
}

/**
 * Зберігає ID останнього відправленого запису
 *
 * @param int $id ID запису
 */
function set_last_sent_id($id) {
    update_option('telegram_last_checked_id', absint($id));
}

/**
 * Відправляє подію в Google Analytics 4 через Measurement Protocol
 *
 * @param string $client_id GA4 Client ID (отриманий із cookie _ga)
 * @param string $event_name Назва події для GA4
 * @param array $event_params Параметри події (асоціативний масив)
 * @return bool True якщо відправка успішна, False у разі помилки
 */
function send_event_to_ga4($client_id, $event_name, $event_params) {
    // Отримання Measurement ID та API Secret з налаштувань плагіну
    $api_secret = get_option('wp_telegram_api_secret', '');
    $measurement_id = get_option('wp_telegram_measurement_id', '');

    // Перевірка, чи налаштовані параметри GA4
    if (empty($api_secret) || empty($measurement_id)) {
        error_log('GA4 не налаштовано: Measurement ID або API Secret не задані.');
        return false;
    }

    // Формуємо URL для Measurement Protocol
    $url = "https://www.google-analytics.com/mp/collect?measurement_id=$measurement_id&api_secret=$api_secret";

    // Формуємо тіло запиту
    $body = [
        'client_id' => $client_id,
        'events' => [
            [
                'name' => $event_name,
                'params' => $event_params,
            ]
        ]
    ];

    // Виконуємо POST-запит
    $response = wp_remote_post($url, [
        'body' => wp_json_encode($body),
        'headers' => ['Content-Type' => 'application/json'],
        'method' => 'POST',
    ]);

    // Перевіряємо відповідь
    if (is_wp_error($response)) {
        error_log('Помилка відправки події до GA4: ' . $response->get_error_message());
        return false;
    }

    return true;
}

/**
 * Отримує client_id із cookie _ga або створює фейковий для GA4
 *
 * @return string Client ID формату X.Y
 */
function get_ga4_client_id() {
    if (!empty($_COOKIE['_ga'])) {
        $ga_cookie = sanitize_text_field($_COOKIE['_ga']);
        return str_replace('GA1.1.', '', $ga_cookie);
    }

    // Автогенерація Client ID як резервний варіант
    return '555.unknown';
}