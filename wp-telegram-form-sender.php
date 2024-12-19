<?php
/*
Plugin Name: WP Telegram Form Sender
Description: Відправка даних з форми у Telegram
Version: 1.2.5
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
    
    // Налаштування для GitHub
    register_setting('wp_telegram_form_sender_options', 'wp_telegram_github_token');

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

    // Секція GitHub
    add_settings_section(
        'wp_telegram_github_section',
        'Налаштування GitHub',
        null,
        'wp-telegram-form-sender'
    );

    // Поле для GitHub токену
    add_settings_field(
        'wp_telegram_github_token',
        'GitHub Token',
        'wp_telegram_github_token_render',
        'wp-telegram-form-sender',
        'wp_telegram_github_section'
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

function wp_telegram_github_token_render() {
    $value = get_option('wp_telegram_github_token');
    echo "<input type='password' name='wp_telegram_github_token' value='" . esc_attr($value) . "' class='regular-text'>";
    echo "<p class='description'>Введіть ваш GitHub Personal Access Token для автоматичних оновлень</p>";
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

            error_log('GA4 Data to send: ' . print_r($ga4_data, true));

            $ga4_endpoint = "https://www.google-analytics.com/mp/collect";
            
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

// Функції для GitHub автооновлення
add_filter('pre_set_site_transient_update_plugins', 'wp_telegram_check_update');
function wp_telegram_check_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $github_token = get_option('wp_telegram_github_token');
    if (empty($github_token)) {
        return $transient;
    }

    $raw_response = wp_remote_get('https://api.github.com/repos/seosmartua/wp-telegram-whatsapp/releases/latest', [
        'headers' => [
            'Authorization' => 'token ' . $github_token,
            'Accept' => 'application/vnd.github.v3+json'
        ]
    ]);

    if (is_wp_error($raw_response)) {
        return $transient;
    }

    $response = json_decode(wp_remote_retrieve_body($raw_response));
    if (empty($response->tag_name)) {
        return $transient;
    }

    $plugin_data = get_plugin_data(__FILE__);
    $current_version = $plugin_data['Version'];

    if (version_compare($current_version, $response->tag_name, '<')) {
        $plugin_slug = plugin_basename(__FILE__);
        $transient->response[$plugin_slug] = (object) [
            'slug' => $plugin_slug,
            'new_version' => $response->tag_name,
            'url' => $response->html_url,
            'package' => $response->zipball_url . '?access_token=' . $github_token
        ];
    }

    return $transient;
}

add_filter('plugins_api', 'wp_telegram_plugin_info', 20, 3);
function wp_telegram_plugin_info($res, $action, $args) {
    if ($action !== 'plugin_information') {
        return $res;
    }

    if ('wp-telegram-form-sender/wp-telegram-form-sender.php' !== $args->slug) {
        return $res;
    }

    $github_token = get_option('wp_telegram_github_token');
    if (empty($github_token)) {
        return $res;
    }

    $raw_response = wp_remote_get('https://api.github.com/repos/seosmartua/wp-telegram-whatsapp/releases/latest', [
        'headers' => [
            'Authorization' => 'token ' . $github_token,
            'Accept' => 'application/vnd.github.v3+json'
        ]
    ]);

    if (is_wp_error($raw_response)) {
        return $res;
    }

    $response = json_decode(wp_remote_retrieve_body($raw_response));
    if (empty($response->tag_name)) {
        return $res;
    }

    $res = new stdClass();
    $res->name = 'WP Telegram Form Sender';
    $res->slug = 'wp-telegram-form-sender';
    $res->version = $response->tag_name;
    $res->tested = '6.4.2';
    $res->requires = '5.0';
    $res->author = 'YuriiKosyi';
    $res->author_profile = 'https://github.com/seosmartua';
    $res->download_link = $response->zipball_url . '?access_token=' . $github_token;
    $res->trunk = $response->zipball_url . '?access_token=' . $github_token;
    $res->requires_php = '7.0';
    $res->last_updated = $response->published_at;
    $res->sections = [
        'description' => 'Відправка даних з форми у Telegram',
        'changelog' => $response->body
    ];

    return $res;
}