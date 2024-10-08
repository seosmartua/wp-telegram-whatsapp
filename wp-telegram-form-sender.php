<?php
/*
Plugin Name: WP Telegram Form Sender
Description: Відправка даних з форми у Telegram
Version: 1.0.9
Author: YuriiKosyi
GitHub Plugin URI: seosmartua/wp-telegram-whatsapp
*/

if (!defined('ABSPATH')) {
    exit; // Запобігаємо прямому доступу
}

// Підключення всіх файлів, включаючи той, де визначена функція відправки
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
            <label for="num_records">Кількість записів для відправки:</label>
            <input type="number" name="num_records" id="num_records" value="0" min="0" step="1" />
            <p class="description">Введіть кількість записів, які потрібно відправити. Якщо вказати 0, відправляться лише нові записи.</p>
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
    echo "<p class='description'>Введіть декілька Chat ID, розділених комами, щоб відправити повідомлення декільком користувачам.</p>";
}

// Функція для відправки повідомлень у Telegram
function wp_telegram_form_sender_send($data) {
    $bot_token = get_option('wp_telegram_form_sender_bot_token');
    $chat_ids = get_option('wp_telegram_form_sender_chat_id');

    if (empty($bot_token) || empty($chat_ids)) {
        return new WP_Error('missing_data', 'Missing bot token or chat ID.');
    }

    $chat_ids = explode(',', $chat_ids); // Розділяємо chat_id по комі та створюємо масив

    $message = "IP: " . $data['visitor_ip'] . "\n";
    $message .= "Number: " . $data['number'] . "\n";
    $message .= "Message: " . $data['message'] . "\n";
    $message .= "Referral: " . $data['referral'] . "\n";
    $message .= "Device: " . $data['device_type'] . "\n";
    $message .= "Date: " . $data['date'] . "\n";
    $message .= "Timestamp: " . $data['timestamp'] . "\n";

    foreach ($chat_ids as $chat_id) {
        $chat_id = trim($chat_id); // Видаляємо зайві пробіли

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

        $response = wp_remote_post($telegram_url, $args);

        if (is_wp_error($response)) {
            return $response; // Повертаємо помилку, якщо щось пішло не так
        }
    }

    return true; // Повертаємо успіх, якщо всі повідомлення були відправлені
}

