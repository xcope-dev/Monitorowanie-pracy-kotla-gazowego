<?php
/*
Plugin Name: Boiler Counter
Description: Wtyczka do wyświetlania informacji o urządzeniu.
Version: 1.4
Author: Xcope
*/

// Rejestracja endpointa do odbioru danych z urządzenia
function boiler_counter_register_api_endpoints() {
    register_rest_route('boiler_counter/v1', '/update_device', [
        'methods' => 'POST',
        'callback' => 'boiler_counter_handle_device_update',
        'permission_callback' => 'boiler_counter_permission_check', // Dodajemy funkcję do sprawdzania uprawnień
    ]);
}
add_action('rest_api_init', 'boiler_counter_register_api_endpoints');

// Funkcja sprawdzająca uprawnienia (weryfikacja tokenu)
function boiler_counter_permission_check(WP_REST_Request $request) {
    $token = $request->get_header('X-Device-Token');
    if ($token !== 'twoj-sekret') {
        return new WP_REST_Response('Nieautoryzowane żądanie', 403);
    }
    return true; // Jeżeli token jest poprawny
}

// Funkcja obsługująca przychodzące dane z urządzenia
function boiler_counter_handle_device_update(WP_REST_Request $request) {
    $data = $request->get_json_params();
    
    // Walidacja danych (np. token, czy wszystko jest ok)
    if (isset($data['module_id']) && $data['module_id'] == 'TU WPISZ ID MODULU') {
        // Aktualizuj dane w bazie danych
        update_option('boiler_counter_relay_on_count', $data['relay_on_count']);
        update_option('boiler_counter_history', $data['history']);
        return new WP_REST_Response('Dane zapisane pomyślnie', 200);
    }
    return new WP_REST_Response('Niepoprawne dane', 400);
}

// Dodanie akcji dla Crona
add_action('boiler_counter_cron_event', 'boiler_counter_update_device_info');

// Wyłączanie harmonogramu Cron przy dezaktywacji wtyczki
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('boiler_counter_cron_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'boiler_counter_cron_event');
    }
});

// Funkcja do aktualizacji danych urządzenia
function boiler_counter_update_device_info() {
    $username = get_option('boiler_counter_username');
    $password = get_option('boiler_counter_password');

    if (!$username || !$password) {
        echo "<p>Proszę skonfigurować dane logowania w ustawieniach wtyczki.</p>";
        return;
    }
// Jeżeli nie pamiętasz swoich danych dotyczących modułów skorzystaj z dok. API i Postman

    $module_id = 'TU WPISZ ID MODULU7';
    $url = 'https://emodul.eu/api/v1/users/WPISZ ID/modules/' . $module_id . '/tiles';
    $token = 'TWOJ TOKEN';

    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        echo "<p>Wystąpił problem z połączeniem z API: " . esc_html($response->get_error_message()) . "</p>";
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['elements']) && is_array($data['elements'])) {
        $relayOnCount = get_option('boiler_counter_relay_on_count', 0);
        $previousStatus = get_option('boiler_counter_previous_status', 'Wyłączone');

        $history = get_option('boiler_counter_history', []);
        $lastResetDate = get_option('boiler_counter_last_reset_date', '');
        $currentDate = current_time('Y-m-d'); // Obecna data w formacie RRRR-MM-DD

        // Sprawdź, czy dzień się zmienił
        if ($lastResetDate !== $currentDate) {
            $relayOnCount = 0; // Reset licznika
            update_option('boiler_counter_relay_on_count', $relayOnCount);
            update_option('boiler_counter_last_reset_date', $currentDate);
        }

        foreach ($data['elements'] as $element) {
            $description = isset($element['params']['description']) ? $element['params']['description'] : 'Brak opisu';
            $workingStatus = isset($element['params']['workingStatus']) ? $element['params']['workingStatus'] : false;

            // Informacje o czujniku temperatury
            if ($description == 'Temperature sensor') {
                echo "<div style='margin-bottom: 20px;'>"; 
                echo "<p style='display: none;><strong>Urządzenie:</strong> " . esc_html($description) . "</p>";
                echo "<p style='display: none;><strong>Status pracy:</strong> " . ($workingStatus ? 'Włączone' : 'Wyłączone') . "</p>";

                if (isset($element['params']['value'])) {
                    $temperature = floatval($element['params']['value']); // Konwersja na liczbę zmiennoprzecinkową
                    
                    // Przeskalowanie wartości (dzielenie przez 10)
                    $temperature = $temperature / 10;
                
                    // Formatowanie liczby z dokładnością do 1 miejsca po przecinku
                    $formattedTemperature = number_format($temperature, 1, '.', '');
                
                    // Debugowanie wartości
                    error_log('Otrzymana wartość temperatury (po przeskalowaniu): ' . $temperature);
                    error_log('Sformatowana wartość temperatury: ' . $formattedTemperature);
                
                    // Wyświetlenie temperatury
                    echo "<p><strong>Temperatura zewnętrzna:</strong> " . esc_html($formattedTemperature) . "°C</p>";
                }
                
                
                    
                
                echo "</div>";
            }

            if ($description == 'Relay') {
                $currentStatus = $workingStatus ? 'Włączone' : 'Wyłączone';
            
                // Pobierz temperaturę w momencie włączenia
                $currentTemperature = null;
                foreach ($data['elements'] as $element) {
                    if (isset($element['params']['description']) && $element['params']['description'] == 'Temperature sensor') {
                        $currentTemperature = isset($element['params']['value']) ? floatval($element['params']['value']) / 10 : null;
                        break;
                    }
                }
            
                // Rejestruj włączenie urządzenia
                if ($previousStatus === 'Wyłączone' && $currentStatus === 'Włączone') {
                    $relayOnCount++;
                    update_option('boiler_counter_relay_on_count', $relayOnCount);
            
                    // Dodaj nowy wpis do historii
                    $history[] = [
                        'start_date' => current_time('mysql'),
                        'end_date' => null,
                        'count' => $relayOnCount,
                        'temperature' => $currentTemperature !== null ? number_format($currentTemperature, 1, '.', '') : 'Brak danych'
                    ];
                    update_option('boiler_counter_history', $history);
                }
            
                // Rejestruj wyłączenie urządzenia
                if ($previousStatus === 'Włączone' && $currentStatus === 'Wyłączone') {
                    $lastEntryIndex = count($history) - 1;
                    if (isset($history[$lastEntryIndex]) && $history[$lastEntryIndex]['end_date'] === null) {
                        $history[$lastEntryIndex]['end_date'] = current_time('mysql');
                        update_option('boiler_counter_history', $history);
                    }
                }
            
                update_option('boiler_counter_previous_status', $currentStatus);
            
            
            
                echo "<div style='margin-bottom: 20px;'>"; 
                echo "<p><strong>Urządzenie kocioł:</strong> " . esc_html($description) . "</p>";
                echo "<p><strong>Status pracy - grzanie:</strong> " . esc_html($currentStatus) . "</p>";
                echo "<p><strong>Liczba włączeń systemu:</strong> " . esc_html($relayOnCount) . "</p>";
                echo "</div>";
            }
            
        }
    } else {
        echo "<p>Brak urządzeń w odpowiedzi API.</p>";
    }
}


// Funkcja do wyświetlania danych urządzenia (shortcode)
function boiler_counter_shortcode() {
    ob_start();
    boiler_counter_update_device_info();
    return ob_get_clean();
}
add_shortcode('boiler_counter_info', 'boiler_counter_shortcode');

// Funkcja do wyświetlania historii włączeń z paginacją i sortowaniem
function boiler_counter_history_shortcode($atts) {
    $atts = shortcode_atts(
        ['paged' => 1], // Domyślna strona
        $atts,
        'boiler_counter_history'
    );

    $history = get_option('boiler_counter_history', []);

    if (empty($history)) {
        return "<p>Brak historii włączeń.</p>";
    }

    // Sortowanie od najnowszych do najstarszych
    usort($history, function ($a, $b) {
        return strtotime($b['start_date']) - strtotime($a['start_date']);
    });

    // Paginacja
    $items_per_page = 20; // Liczba rekordów na stronę
    $total_items = count($history);
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, intval($atts['paged']));

    $offset = ($current_page - 1) * $items_per_page;
    $paged_history = array_slice($history, $offset, $items_per_page);

    // Stylizacja tabeli
    $output = "<style>
.boiler-counter-history-table {
    border: 2px solid #353466;
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed; /* Wymusza równą szerokość kolumn */
}
.boiler-counter-history-table th, .boiler-counter-history-table td {
    border: 1px solid #353466;
    padding: 5px;
    text-align: left;
    word-wrap: break-word; /* Zapewnia, że tekst w komórkach nie wyjdzie poza kolumnę */
}

/* Ustawienie szerokości dla pierwszych dwóch kolumn */
.boiler-counter-history-table th:nth-child(1),
.boiler-counter-history-table td:nth-child(1) {
    width: 30%; /* Szerokość dla pierwszej kolumny */
}

.boiler-counter-history-table th:nth-child(2),
.boiler-counter-history-table td:nth-child(2) {
    width: 30%; /* Szerokość dla drugiej kolumny */
}

/* Ustawienie szerokości dla pozostałych kolumn */
.boiler-counter-history-table th:nth-child(n+3),
.boiler-counter-history-table td:nth-child(n+3) {
    width: 15%; /* Szerokość dla trzeciej i kolejnych kolumn */
}

.boiler-counter-history-table th {
    background-color: #161535 !important;
    color: white;
}
.boiler-pagination {
    margin-top: 15px;
    text-align: center;
}
.boiler-pagination a {
    text-decoration: none;
    margin: 0 5px;
    padding: 5px 10px;
    border: 1px solid #353466;
    color: #353466;
}
.boiler-pagination a.current {
    background-color: #353466;
    color: white;
}
</style>";


    // Generowanie tabeli
    $output .= "<table class='boiler-counter-history-table'>
        <tr>
            <th>Data On</th>
            <th>Data Off</th>
            <th>L/On</th>
            <th>(°C)</th>
        </tr>";
    foreach ($paged_history as $entry) {
        $output .= "<tr>
            <td>" . esc_html($entry['start_date']) . "</td>
            <td>" . esc_html($entry['end_date'] ?? 'Trwa') . "</td>
            <td>" . esc_html($entry['count']) . "</td>
            <td>" . esc_html($entry['temperature']) . "</td>
        </tr>";
    }
    $output .= "</table>";

    // Dodanie paginacji
    if ($total_pages > 1) {
        $output .= "<div class='boiler-pagination'>";
        for ($i = 1; $i <= $total_pages; $i++) {
            $class = $i === $current_page ? 'current' : '';
            $output .= "<a href='?paged=$i' class='$class'>$i</a>";
        }
        $output .= "</div>";
    }

    return $output;
}
add_shortcode('boiler_counter_history', 'boiler_counter_history_shortcode');


// Funkcja do resetowania licznika
function boiler_counter_reset() {
    delete_option('boiler_counter_relay_on_count');
    delete_option('boiler_counter_previous_status');
    delete_option('boiler_counter_history');
    update_option('boiler_counter_relay_on_count', 0);
    update_option('boiler_counter_previous_status', 'Wyłączone');
    wp_redirect(admin_url('admin.php?page=boiler_counter_reset&reset=true'));
    exit;
}

// Funkcja do obsługi resetu licznika
function boiler_counter_reset_handler() {
    if (isset($_POST['boiler_counter_reset']) && $_POST['boiler_counter_reset'] == '1' && check_admin_referer('boiler_counter_reset_action', 'boiler_counter_nonce')) {
        boiler_counter_reset();
    }
}

// Funkcja do dodania opcji w menu admina
function boiler_counter_menu() {
    add_menu_page('Boiler Counter', 'Boiler Counter', 'manage_options', 'boiler_counter', 'boiler_counter_settings_page');
    add_submenu_page('boiler_counter', 'Ustawienia', 'Ustawienia', 'manage_options', 'boiler_counter', 'boiler_counter_settings_page');
    add_submenu_page('boiler_counter', 'Resetuj licznik', 'Resetuj licznik', 'manage_options', 'boiler_counter_reset', 'boiler_counter_reset_page');
}

add_action('admin_menu', 'boiler_counter_menu');
add_action('admin_init', 'boiler_counter_reset_handler');

// Strona resetowania licznika
function boiler_counter_reset_page() {
    ?>
    <div class="wrap">
        <h2>Resetowanie licznika</h2>
        <?php if (isset($_GET['reset']) && $_GET['reset'] === 'true') : ?>
            <div class="updated"><p>Stan licznika i statusu został zresetowany!</p></div>
        <?php endif; ?>
        <form method="post" action="">
            <?php 
            wp_nonce_field('boiler_counter_reset_action', 'boiler_counter_nonce');
            ?>
            <input type="hidden" name="boiler_counter_reset" value="1" />
            <p>Kliknij poniżej, aby zresetować licznik i status urządzenia.</p>
            <input type="submit" class="button-primary" value="Resetuj licznik" />
        </form>
    </div>
    <?php
}

// Strona ustawień wtyczki
function boiler_counter_settings_page() {
    ?>
    <div class="wrap">
        <h2>Ustawienia wtyczki Boiler Counter</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('boiler_counter_options_group');
            do_settings_sections('boiler_counter');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Nazwa użytkownika</th>
                    <td><input type="text" name="boiler_counter_username" value="<?php echo esc_attr(get_option('boiler_counter_username')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Hasło</th>
                    <td><input type="password" name="boiler_counter_password" value="<?php echo esc_attr(get_option('boiler_counter_password')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Rejestracja ustawień wtyczki
function boiler_counter_register_settings() {
    register_setting('boiler_counter_options_group', 'boiler_counter_username');
    register_setting('boiler_counter_options_group', 'boiler_counter_password');
}

add_action('admin_init', 'boiler_counter_register_settings');

// Rejestracja akcji AJAX dla zalogowanych i niezalogowanych użytkowników
add_action('wp_ajax_boiler_counter_update', 'boiler_counter_ajax_update');
add_action('wp_ajax_nopriv_boiler_counter_update', 'boiler_counter_ajax_update');

// Rejestracja jQuery w wtyczce
function boiler_counter_enqueue_scripts() {
    wp_enqueue_script('jquery', 'https://code.jquery.com/jquery-3.6.0.min.js', false, null, true);
}
add_action('wp_enqueue_scripts', 'boiler_counter_enqueue_scripts');


// Funkcja do obsługi AJAX
function boiler_counter_ajax_update() {
    // Sprawdzamy nonce dla bezpieczeństwa
    if ( !isset($_POST['boiler_counter_nonce']) || !wp_verify_nonce($_POST['boiler_counter_nonce'], 'boiler_counter_nonce_action') ) {
        wp_send_json_error(['message' => 'Nieautoryzowane żądanie.']);
        exit;
    }

    ob_start();
    boiler_counter_update_device_info();  // Funkcja aktualizująca dane urządzenia
    $device_info = ob_get_clean(); // Generowanie danych urządzenia

    // Generowanie historii
    $history = boiler_counter_history_shortcode();
    
    // Zwracamy zarówno dane urządzenia, jak i historię
    wp_send_json_success([
        'info' => $device_info,
        'history' => $history
    ]);
}

// Skrypt JavaScript do automatycznego odświeżania
function boiler_counter_ajax_script() {
    // Generujemy nonce do użycia w AJAX
    $nonce = wp_create_nonce('boiler_counter_nonce_action');
    ?>
    <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                setInterval(function() {
                    $.ajax({
                        url: '<?php echo admin_url("admin-ajax.php"); ?>',  // Dynamicznie generujemy URL do admin-ajax.php
                        method: 'POST',
                        data: {
                            action: 'boiler_counter_update', // Akcja do wywołania w AJAX
                            boiler_counter_nonce: '<?php echo $nonce; ?>' // Dodajemy nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Aktualizujemy dane urządzenia
                                $('#boiler-counter-info').html(response.data.info);

                                // Aktualizujemy historię
                                $('#boiler-counter-history').html(response.data.history);
                            } else {
                                console.error('Błąd pobierania danych');
                            }
                        }
                    });
                }, 480000); // Co 8 minut
            });
        })(jQuery);
    </script>
    <?php
}
add_action('wp_footer', 'boiler_counter_ajax_script');
