<?php
/*
Plugin Name: WP Telegram Form Sender
Description: Відправка даних з форми у Telegram
Version: 1.2.3.1
Author: YuriiKosyi
GitHub Plugin URI: seosmartua/wp-telegram-whatsapp
*/

if (!defined('ABSPATH')) {
    exit;
}

// Підключення файлів
require_once plugin_dir_path(__FILE__) . 'telegram-functions.php';
require_once plugin_dir_path(__FILE__) . 'telegram-form-handler.php';
require_once plugin_dir_path(__FILE__) . 'telegram-manual-send.php';

// Функція валідації GA4 даних
function validate_ga4_data($measurement_id, $api_secret) {
    if (empty($measurement_id) || !preg_match('/^G-[A-Z0-9]+$/', $measurement_id)) {
        error_log('Invalid GA4 Measurement ID format');
        return false;
    }

    if (empty($api_secret)) {
        error_log('Empty GA4 API Secret');
        return false;
    }

    return true;
}

// Функція оновлення статусу GA4
function update_ga4_status($status) {
    update_option('wp_telegram_last_ga4_status', $status . ' - ' . current_time('mysql'));
}

// Додавання меню
add_action('admin_menu', 'wp_telegram_form_sender_menu');
function wp_telegram_form_sender_menu() {
    add_options_page(
        'Telegram Form Sender',
        'Telegram Form Sender',
        'manage_options',
        'wp-telegram-form-sender',
        'wp_telegram_form_sender_settings_page'
    );
}

// Реєстрація налаштувань
add_action('admin_init', 'wp_telegram_form_sender_settings_init');
function wp_telegram_form_sender_settings_init() {
    // Налаштування для Telegram
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_form_sender_bot_token');
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_form_sender_chat_id');
    
    // Налаштування для GA4
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_measurement_id');
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_api_secret');

    // Секція Telegram
    add_settings_section(
        'wp_telegram_form_sender_section',
        'Налаштування Telegram',
        null,
        'wp-telegram-form-sender'
    );

    // Поля для Telegram
    add_settings_field(
        'wp_telegram_form_sender_bot_token',
        'Bot Token',
        'wp_telegram_form_sender_bot_token_render',
        'wp-telegram-form-sender',
        'wp_telegram_form_sender_section'
    );

    add_settings_field(
        'wp_telegram_form_sender_chat_id',
        'Chat ID',
        'wp_telegram_form_sender_chat_id_render',
        'wp-telegram-form-sender',
        'wp_telegram_form_sender_section'
    );

    // Секція GA4
    add_settings_section(
        'wp_telegram_ga4_section',
        'Налаштування Google Analytics 4',
        null,
        'wp-telegram-form-sender'
    );

    // Поля для GA4
    add_settings_field(
        'wp_telegram_measurement_id',
        'GA4 Measurement ID',
        'wp_telegram_measurement_id_render',
        'wp-telegram-form-sender',
        'wp_telegram_ga4_section'
    );

    add_settings_field(
        'wp_telegram_api_secret',
        'GA4 API Secret',
        'wp_telegram_api_secret_render',
        'wp-telegram-form-sender',
        'wp_telegram_ga4_section'
    );
}

// Функції рендерингу полів
function wp_telegram_form_sender_bot_token_render() {
    $value = get_option('wp_telegram_form_sender_bot_token');
    echo "<input type='text' name='wp_telegram_form_sender_bot_token' value='" . esc_attr($value) . "' class='regular-text'>";
}

function wp_telegram_form_sender_chat_id_render() {
    $value = get_option('wp_telegram_form_sender_chat_id');
    echo "<input type='text' name='wp_telegram_form_sender_chat_id' value='" . esc_attr($value) . "' class='regular-text'>";
}

function wp_telegram_measurement_id_render() {
    $value = get_option('wp_telegram_measurement_id');
    echo "<input type='text' name='wp_telegram_measurement_id' value='" . esc_attr($value) . "' class='regular-text'>";
    echo "<p class='description'>Введіть ваш GA4 Measurement ID (починається з G-)</p>";
}

function wp_telegram_api_secret_render() {
    $value = get_option('wp_telegram_api_secret');
    echo "<input type='text' name='wp_telegram_api_secret' value='" . esc_attr($value) . "' class='regular-text'>";
    echo "<p class='description'>Введіть ваш GA4 API Secret</p>";
}

// Сторінка налаштувань
function wp_telegram_form_sender_settings_page() {
    ?>
    <div class="wrap">
        <h2>Налаштування WP Telegram Form Sender</h2>
        
        <?php
        // Відображення статусу GA4
        $last_ga4_status = get_option('wp_telegram_last_ga4_status', '');
        if (!empty($last_ga4_status)) {
            echo '<div class="notice notice-info">';
            echo '<p>Останній статус відправки в GA4: ' . esc_html($last_ga4_status) . '</p>';
            echo '</div>';
        }
        ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('wp_telegram_form_sender_options');
            do_settings_sections('wp-telegram-form-sender');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Функція відправки
function wp_telegram_form_sender_send($data) {
    $bot_token = get_option('wp_telegram_form_sender_bot_token');
    $chat_ids = get_option('wp_telegram_form_sender_chat_id');

    if (empty($bot_token) || empty($chat_ids)) {
        return new WP_Error('missing_data', 'Відсутні Bot Token або Chat ID.');
    }

    $chat_ids = explode(',', $chat_ids);
    $success = true;

    $message = "IP: " . $data['visitor_ip'] . "\n";
    $message .= "Number: " . $data['number'] . "\n";
    $message .= "Message: " . $data['message'] . "\n";
    $message .= "Referral: " . $data['referral'] . "\n";
    $message .= "Device: " . $data['device_type'] . "\n";
    $message .= "Date: " . $data['date'] . "\n";
    $message .= "Timestamp: " . $data['timestamp'] . "\n";

    foreach ($chat_ids as $chat_id) {
        $chat_id = trim($chat_id);
        $response = wp_remote_post("https://api.telegram.org/bot$bot_token/sendMessage", [
            'body' => json_encode([
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $success = false;
            error_log('Telegram Error: ' . $response->get_error_message());
        }
    }

    // Відправка події в GA4
    if ($success) {
        $measurement_id = get_option('wp_telegram_measurement_id');
        $api_secret = get_option('wp_telegram_api_secret');

        if (validate_ga4_data($measurement_id, $api_secret)) {
            $client_id = isset($_COOKIE['_ga']) ? $_COOKIE['_ga'] : uniqid('ga4_', true);
            
            $ga4_data = [
                'client_id' => $client_id,
                'events' => [
                    [
                        'name' => 'telegram_message_sent',
                        'params' => [
                            'number' => $data['number'],
                            'message' => $data['message'],
                            'referral' => $data['referral'],
                            'device_type' => $data['device_type'],
                            'debug_mode' => true
                        ]
                    ]
                ]
            ];

            // Логуємо дані перед відправкою
            error_log('GA4 Data to send: ' . print_r($ga4_data, true));

            // Використовуємо тестовий endpoint для дебагу
            $ga4_endpoint = "https://www.google-analytics.com/debug/mp/collect";
            
            $response = wp_remote_post($ga4_endpoint . "?measurement_id=$measurement_id&api_secret=$api_secret", [
                'body' => json_encode($ga4_data),
                'headers' => ['Content-Type' => 'application/json']
            ]);

            if (is_wp_error($response)) {
                error_log('GA4 Error: ' . $response->get_error_message());
                update_ga4_status('Помилка: ' . $response->get_error_message());
            } else {
                error_log('GA4 Response: ' . print_r($response['body'], true));
                update_ga4_status('Успішно відправлено');
            }
        }
    }

    return $success;
}