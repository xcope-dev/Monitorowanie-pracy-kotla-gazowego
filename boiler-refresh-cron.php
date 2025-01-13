<?php
/*
Plugin Name: Boiler Refresh Cron
Description: Automatyczne odświeżanie strony https://twoja-domena/ co 8 minut.
Version: 1.0
Author: Xcope
*/

// Rejestracja interwału co 8 minut
function boiler_add_cron_interval($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 480, // 480 sekund = 8 minut
        'display'  => __('Co 8 minut')
    );
    return $schedules;
}
add_filter('cron_schedules', 'boiler_add_cron_interval');

// Rejestracja zdarzenia cronowego
function boiler_activate_cron() {
    if (!wp_next_scheduled('boiler_refresh_page_cron')) {
        wp_schedule_event(time(), 'every_five_minutes', 'boiler_refresh_page_cron');
    } else {
        wp_clear_scheduled_hook('boiler_refresh_page_cron');
        wp_schedule_event(time(), 'every_five_minutes', 'boiler_refresh_page_cron');
    }
}

register_activation_hook(__FILE__, 'boiler_activate_cron');

// Usuwanie zdarzenia cronowego przy dezaktywacji wtyczki
function boiler_deactivate_cron() {
    $timestamp = wp_next_scheduled('boiler_refresh_page_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'boiler_refresh_page_cron');
    }
}
register_deactivation_hook(__FILE__, 'boiler_deactivate_cron');

// Funkcja odświeżająca stronę
function boiler_refresh_page() {
    $url = 'https://podstrona-na-ktorej-umieszczony-jest-shortcode';
    $response = wp_remote_get($url);
    
    // Logowanie czasu dla debuggowania
    error_log('Boiler refresh cron started at: ' . current_time('mysql'));
    
    if (is_wp_error($response)) {
        error_log('Błąd podczas odświeżania strony: ' . $response->get_error_message());
    } else {
        // Logowanie statusu odpowiedzi
        $status_code = wp_remote_retrieve_response_code($response);
        error_log('Strona odświeżona pomyślnie, status odpowiedzi: ' . $status_code);
    
}

    // Logowanie wyników w razie potrzeby
    if (is_wp_error($response)) {
        error_log('Błąd podczas odświeżania strony: ' . $response->get_error_message());
    } else {
        error_log('Strona odświeżona pomyślnie: ' . current_time('mysql'));
    }
}
add_action('boiler_refresh_page_cron', 'boiler_refresh_page');
