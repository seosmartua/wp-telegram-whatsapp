<?php

if (!defined('ABSPATH')) {
    exit; 
}

function save_last_sent_status($status) {
    update_option('telegram_last_send_status', $status);
    update_option('telegram_last_send_time', current_time('mysql'));
}

function get_last_sent_status() {
    return get_option('telegram_last_send_status', 'No data sent yet.');
}

function get_last_sent_time() {
    return get_option('telegram_last_send_time', 'Never sent.');
}

function get_last_sent_id() {
    return get_option('telegram_last_checked_id', 0);
}

function set_last_sent_id($id) {
    update_option('telegram_last_checked_id', $id);
}
