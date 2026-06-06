=== Returns & Complaints for WooCommerce ===
Contributors: sascom
Author: Sascom - Bartosz Sudół
Author URI: https://sascom.pl/
Tags: woocommerce, returns, complaints, rma, refunds
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
WC requires at least: 6.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight WooCommerce plugin that adds a public return, withdrawal and complaint request form, including support for guest customers.

== Description ==

Returns & Complaints for WooCommerce dodaje publiczny formularz zgłoszenia zwrotu / odstąpienia od umowy oraz reklamacji. Formularz działa dla wszystkich klientów WooCommerce – również tych, którzy kupili bez konta.

Najważniejsze cechy:

* Formularz osadzany przez shortcode `[sascom_rc_returns_form]`.
* Weryfikacja zamówienia po numerze zamówienia oraz adresie e-mail (billing_email).
* Brak ujawniania danych zamówienia, jeżeli e-mail nie pasuje.
* Dwa typy zgłoszeń: zwrot / odstąpienie od umowy oraz reklamacja / problem z produktem.
* Wybór konkretnych produktów z zamówienia.
* Techniczny zakres formularza online: ostatnie 30 dni (nie wydłuża ustawowego terminu zwrotu).
* Reklamacje starsze niż 30 dni: dopuszczone z oznaczeniem „weryfikacja ręczna”.
* Zwroty starsze niż 30 dni: komunikat kierujący do kontaktu mailowego (bez brutalnej blokady).
* Custom post type `sascom_return_request` z dedykowanymi statusami.
* E-mail potwierdzający do klienta oraz powiadomienie do administratora.
* Powiązanie zgłoszenia z zamówieniem: prywatna notatka, flaga oraz kolumna na liście zamówień.
* Zgodność z HPOS (High-Performance Order Storage).

Wtyczka NIE wykonuje płatności, automatycznego refundu ani integracji z kurierem.

== Installation ==

1. Skopiuj katalog `returns-complaints-for-woocommerce` do `wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu WordPress.
3. Utwórz podstronę (np. `/zwroty-i-reklamacje/`) i wstaw shortcode `[sascom_rc_returns_form]`.

== Statusy zgłoszeń ==

* new – Nowe
* manual_verification – Weryfikacja ręczna
* waiting_for_customer_shipment – Oczekuje na wysyłkę od klienta
* received_by_store – Odebrane przez sklep
* refund_pending – Zwrot środków w toku
* refund_completed – Zwrot środków zrealizowany
* closed – Zamknięte

== Changelog ==

= 1.0.0 =
* Pierwsza wersja MVP.
