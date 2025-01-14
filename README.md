# Boiler Counter

**Boiler Counter** to wtyczka dla WordPress umożliwiająca monitorowanie pracy kotła grzewczego. Pozwala ona na śledzenie liczby załączeń i wyłączeń urządzenia w ciągu dnia, przechowuje dane historyczne oraz rejestruje temperaturę zewnętrzną. Dzięki temu można ocenić, czy kocioł pracuje prawidłowo, czy też występuje zjawisko taktowania.

## Funkcjonalności

- **Licznik załączeń i wyłączeń kotła** w danym dniu oraz godzinie.
- **Przechowywanie danych historycznych** w bazie danych.
- **Możliwość pobrania pliku danych historycznych** w bazie danych.
- **Rejestrowanie temperatury zewnętrznej** w momencie pracy urządzenia.
- Możliwość **resetowania okresu działania** i danych historycznych.
- Obsługa autoryzacji za pomocą **tokenu i ID urządzenia**.
- Współpraca z modułami firmy **TechSterowniki**:
  - Sterownik grzejnikowy Wi-Fi 8S
  - Bezprzewodowy czujnik pokojowy C-8r
  - Bezprzewodowy moduł wykonawczy MW-1
  - Bezprzewodowy czujnik temperatury zewnętrznej C-8zr

## Wymagania

- WordPress 5.0 lub nowszy
- Moduły firmy TechSterowniki
- Token oraz ID urządzenia z systemu eModul
- Zewnętrzny CRON skonfigurowany na serwerze

## Instalacja

1. Pobierz plik `boiler-counter.php` oraz `boiler-refresh-cron.php`.
2. Zaloguj się do panelu WordPress jako administrator.
3. Przejdź do sekcji **Wtyczki** → **Dodaj nową**.
4. Kliknij **Prześlij wtyczkę**, wybierz plik i zainstaluj go.
5. Aktywuj wtyczkę.

Dla pełnej funkcjonalności zainstaluj również wtyczkę **boiler-refresh-cron**.

## Konfiguracja

1. Przejdź do sekcji **Boiler Counter** w panelu administracyjnym WordPress.
2. Wprowadź swoje dane logowania:
   - Nazwa użytkownika
   - Hasło
3. Ustaw token oraz ID urządzenia.
4. Skonfiguruj zewnętrzne zadania CRON:
   - Wyzwalacz CRON 1:
     ```bash
     */8 * * * * curl -X POST https://twoja-strona/wp-json/boiler-counter/v1/update_device -H "X-Device-Token: twoj-sekret"
     ```
   - Wyzwalacz CRON 2:
     ```bash
     */8 * * * * curl -s https://twoja-strona/wp-cron.php?doing_wp_cron > /dev/null
     ```

## Shortcodes

- **[boiler_counter_info]**: Wyświetla aktualne dane urządzenia, w tym liczbę załączeń oraz temperaturę.
- **[boiler_counter_history]**: Wyświetla historię załączeń z paginacją i sortowaniem.
- **[my_plugin_download_button]**: Tworzy przycisk i pobiera historię załączeń plik .csv.

## Ważne linki

- [Producent sterowników TechSterowniki](https://www.techsterowniki.pl/)
- [API eModul](https://api-documentation.emodul.eu/)

## Resetowanie danych

W celu zresetowania licznika oraz danych historycznych:

1. Przejdź do **Boiler Counter → Resetuj licznik**.
2. Kliknij przycisk **Resetuj licznik**.

## Uwagi

- Wtyczka została przetestowana z następującymi urządzeniami:
  - Sterownik grzejnikowy Wi-Fi 8S
  - Bezprzewodowy czujnik pokojowy C-8r
  - Bezprzewodowy moduł wykonawczy MW-1
  - Bezprzewodowy czujnik temperatury zewnętrznej C-8zr
- Upewnij się, że Twój serwer obsługuje zewnętrzne wywołania CRON.

## Licencja

Ta wtyczka jest dostępna na licencji GPL-2.0.

## Screeny aplikacji

Poniżej znajduje się screen prezentujący frontend wtyczki:

![Screen aplikacji](https://raw.githubusercontent.com/xcope-dev/Monitorowanie-pracy-kotla-gazowego/blob/ce5edbacbfb9eced0459836d1e144b8c49f2889b/app1.PNG)

![Screen aplikacji](https://raw.githubusercontent.com/xcope-dev/Monitorowanie-pracy-kotla-gazowego/blob/ce5edbacbfb9eced0459836d1e144b8c49f2889b/app2.PNG)


