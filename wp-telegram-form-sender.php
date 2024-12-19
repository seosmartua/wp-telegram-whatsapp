<?php
/*
Plugin Name: WP Telegram Form Sender
Description: Відправка даних з форми у Telegram
Version: 1.2.0
Author: YuriiKosyi
GitHub Plugin URI: seosmartua/wp-telegram-whatsapp
*/

if (!defined('ABSPATH')) {
    exit; // Запобігаємо прямому доступу
}

// Підключення всіх файлів
require_once plugin_dir_path(__FILE__) . 'telegram-functions.php';
require_once plugin_dir_path(__FILE__) . 'telegram-form-handler.php';
require_once plugin_dir_path(__FILE__) . 'telegram-manual-send.php';

// Додавання сторінки налаштувань
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

// Адмін-сторінка налаштувань
function wp_telegram_form_sender_settings_page() {
    if (isset($_POST['manual_send'])) {
        $num_records = isset($_POST['num_records']) ? intval($_POST['num_records']) : 0;
        manual_send_entries_to_telegram($num_records);
        echo '<div class="updated"><p>Manual data send executed.</p></div>';
    }
    ?>
    <div class="wrap">
        <h2>Налаштування WP Telegram Form Sender</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_telegram_form_sender_options'); // Реєструємо групу налаштувань
            do_settings_sections('wp-telegram-form-sender'); // Відображаємо всі налаштування
            submit_button();
            ?>
        </form>
        <h3>Статус відправки</h3>
        <p>Останній статус: <?php echo esc_html(get_last_sent_status()); ?></p>
        <p>Час останньої відправки: <?php echo esc_html(get_last_sent_time()); ?></p>
        <p>Останній відправлений ID: <?php echo esc_html(get_last_sent_id()); ?></p>
        <form method="post" action="">
            <label for="num_records">Кількість записів для відправки:</label>
            <input type="number" name="num_records" id="num_records" value="0" min="0" step="1" required />
            <p class="description">Введіть кількість записів, які потрібно відправити. Якщо вказати 0, будуть відправлені лише нові записи.</p>
            <input type="hidden" name="manual_send" value="1" />
            <?php submit_button('Відправити дані вручну'); ?>
        </form>
    </div>
    <?php
}

// Реєстрація налаштувань
add_action('admin_init', 'wp_telegram_form_sender_settings_init');
function wp_telegram_form_sender_settings_init() {
    // Реєструємо опції
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_form_sender_bot_token');
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_form_sender_chat_id');
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_measurement_id');
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_api_secret');

    // Секція налаштувань Telegram
    add_settings_section(
        'wp_telegram_form_sender_section',
        'Telegram API Налаштування',
        null,
        'wp-telegram-form-sender'
    );

    add_settings_field(
        'wp_telegram_form_sender_bot_token',
        'Telegram Bot Token',
        'wp_telegram_form_sender_bot_token_render',
        'wp-telegram-form-sender',
        'wp_telegram_form_sender_section'
    );

    add_settings_field(
        'wp_telegram_form_sender_chat_id',
        'Telegram Chat ID',
        'wp_telegram_form_sender_chat_id_render',
        'wp-telegram-form-sender',
        'wp_telegram_form_sender_section'
    );

    // Секція налаштувань GA4
    add_settings_section(
        'wp_telegram_ga4_section',
        'Google Analytics 4 Налаштування',
        null,
        'wp-telegram-form-sender'
    );

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

// Рендер поля Bot Token
function wp_telegram_form_sender_bot_token_render() {
    $bot_token = get_option('wp_telegram_form_sender_bot_token', '');
    echo "<input type='text' name='wp_telegram_form_sender_bot_token' value='" . esc_attr($bot_token) . "' />";
}

// Рендер поля Chat ID
function wp_telegram_form_sender_chat_id_render() {
    $chat_id = get_option('wp_telegram_form_sender_chat_id', '');
    echo "<input type='text' name='wp_telegram_form_sender_chat_id' value='" . esc_attr($chat_id) . "' />";
    echo "<p class='description'>Введіть кілька Chat ID, розділених комами для надсилання даних до декількох отримувачів.</p>";
}

// Рендер поля GA4 Measurement ID
function wp_telegram_measurement_id_render() {
    $measurement_id = get_option('wp_telegram_measurement_id', '');
    echo "<input type='text' name='wp_telegram_measurement_id' value='" . esc_attr($measurement_id) . "' />";
    echo "<p class='description'>Введіть ваш GA4 Measurement ID (приклад: G-XXXXXXXXXX).</p>";
}

// Рендер поля GA4 API Secret
function wp_telegram_api_secret_render() {
    $api_secret = get_option('wp_telegram_api_secret', '');
    echo "<input type='text' name='wp_telegram_api_secret' value='" . esc_attr($api_secret) . "' />";
    echo "<p class='description'>Введіть ваш API Secret для Measurement Protocol.</p>";
}

// Функція для відправки в Telegram
function wp_telegram_form_sender_send($data) {
    $bot_token = get_option('wp_telegram_form_sender_bot_token');
    $chat_ids = get_option('wp_telegram_form_sender_chat_id');

    if (empty($bot_token) || empty($chat_ids)) {
        return new WP_Error('missing_data', 'Відсутні Bot Token або Chat ID.');
    }

    $chat_ids = explode(',', $chat_ids); // Розділяємо chat_ids на масив
    $success = true;

    $message = "IP: " . $data['visitor_ip'] . "\n";
    $message .= "Number: " . $data['number'] . "\n";
    $message .= "Message: " . $data['message'] . "\n";
    $message .= "Referral: " . $data['referral'] . "\n";
    $message .= "Device: " . $data['device_type'] . "\n";
    $message .= "Date: " . $data['date'] . "\n";
    $message .= "Timestamp: " . $data['timestamp'] . "\n";

    foreach ($chat_ids as $chat_id) {
        $chat_id = trim($chat_id); // Видаляємо зайві пробіли

        $response = wp_remote_post("https://api.telegram.org/bot$bot_token/sendMessage", [
            'body' => json_encode([
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            error_log('Помилка відправки в Telegram: ' . $response->get_error_message());
            $success = false; // Змінюємо, якщо є помилка
        }
    }

    // Надішліть подію до GA4 (якщо успішно відправлено до Telegram)
    if ($success) {
        send_event_to_ga4($data);
    }

    return $success;
}

// Функція для Measurement Protocol GA4
function send_event_to_ga4($data) {
    $measurement_id = get_option('wp_telegram_measurement_id', '');
    $api_secret = get_option('wp_telegram_api_secret', '');

    if (empty($measurement_id) || empty($api_secret)) {
        error_log('GA4 не налаштований: Measurement ID або API Secret відсутній.');
        return false;
    }

    $client_id = $_COOKIE['_ga'] ?? '555.unknown';
    $client_id = str_replace('GA1.1.', '', $client_id);

    $ga4_url = "https://www.google-analytics.com/mp/collect?measurement_id=$measurement_id&api_secret=$api_secret";
    $body = [
        'client_id' => $client_id,
        'events' => [
            [
                'name' => 'telegram_message_sent',
                'params' => [
                    'number' => $data['number'],
                    'message' => $data['message'],
                    'referral' => $data['referral'],
                    'device_type' => $data['device_type'],
                ],
            ],
        ],
    ];

    $response = wp_remote_post($ga4_url, [
        'body' => wp_json_encode($body),
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        error_log('Помилка відправки події до GA4: ' . $response->get_error_message());
        return false;
    }

    return true;
}