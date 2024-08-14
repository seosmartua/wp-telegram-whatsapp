<?php
/*
Plugin Name: WP Telegram Form Sender
Description: Відправка даних з форми у Telegram
Version: 1.0.5
Author: YuriiKosyi
GitHub Plugin URI: seosmartua/wp-telegram-whatsapp
*/

if (!defined('ABSPATH')) {
    exit; // Запобігаємо прямому доступу
}

// Додавання сторінки налаштувань
add_action('admin_menu', 'wp_telegram_form_sender_menu');

// Підключення файлів
require_once plugin_dir_path(__FILE__) . 'telegram-functions.php';
require_once plugin_dir_path(__FILE__) . 'telegram-form-handler.php';

function wp_telegram_form_sender_menu() {
    add_options_page(
        'Telegram Form Sender',
        'Telegram Form Sender',
        'manage_options',
        'wp-telegram-form-sender',
        'wp_telegram_form_sender_settings_page'
    );
}

function wp_telegram_form_sender_settings_page() {
    if (isset($_POST['manual_send'])) {
        check_new_entries_for_telegram();
        echo '<div class="updated"><p>Manual data send executed.</p></div>';
    }
    ?>
    <div class="wrap">
        <h2>Налаштування WP Telegram Form Sender</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_telegram_form_sender_options');
            do_settings_sections('wp-telegram-form-sender');
            submit_button();
            ?>
        </form>
        <h3>Статус відправки</h3>
        <p>Останній статус: <?php echo esc_html(get_last_sent_status()); ?></p>
        <p>Час останньої відправки: <?php echo esc_html(get_last_sent_time()); ?></p>
        <p>Останній відправлений ID: <?php echo esc_html(get_last_sent_id()); ?></p>
        <form method="post" action="">
            <input type="hidden" name="manual_send" value="1" />
            <?php submit_button('Відправити дані вручну'); ?>
        </form>
    </div>
    <?php
}

// Реєстрація налаштувань
add_action('admin_init', 'wp_telegram_form_sender_settings_init');

function wp_telegram_form_sender_settings_init() {
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_form_sender_bot_token');
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_form_sender_chat_id');

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
}

function wp_telegram_form_sender_bot_token_render() {
    $bot_token = get_option('wp_telegram_form_sender_bot_token', '');
    echo "<input type='text' name='wp_telegram_form_sender_bot_token' value='" . esc_attr($bot_token) . "' />";
}

function wp_telegram_form_sender_chat_id_render() {
    $chat_id = get_option('wp_telegram_form_sender_chat_id', '');
    echo "<input type='text' name='wp_telegram_form_sender_chat_id' value='" . esc_attr($chat_id) . "' />";
}

// Функція для відправки повідомлень у Telegram
function wp_telegram_form_sender_send($data) {
    $bot_token = get_option('wp_telegram_form_sender_bot_token');
    $chat_id = get_option('wp_telegram_form_sender_chat_id');

    if (empty($bot_token) || empty($chat_id)) {
        return new WP_Error('missing_data', 'Missing bot token or chat ID.');
    }

    $message = "IP: " . $data['visitor_ip'] . "\n";
    $message .= "Number: " . $data['number'] . "\n";
    $message .= "Message: " . $data['message'] . "\n";
    $message .= "Referral: " . $data['referral'] . "\n";
    $message .= "Device: " . $data['device_type'] . "\n";
    $message .= "Date: " . $data['date'] . "\n";
    $message .= "Timestamp: " . $data['timestamp'] . "\n";

    $telegram_url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $args = [
        'body' => json_encode([
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]),
        'headers' => ['Content-Type' => 'application/json'],
        'method' => 'POST',
        'data_format' => 'body',
    ];

    return wp_remote_post($telegram_url, $args);
}
