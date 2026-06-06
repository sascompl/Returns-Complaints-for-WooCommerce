<?php
/**
 * Rate limiting publicznych endpointów AJAX, oparte o transienty.
 *
 * Klucze transientów nie zawierają surowego IP, e-maila ani numeru zamówienia –
 * używamy wyłącznie wp_hash() (HMAC na sekretach WordPressa).
 *
 * @package Sascom_RC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa rate-limitera (metody statyczne).
 */
class Sascom_RC_Rate_Limiter {

	const PREFIX = 'sascom_rc_rl_';

	const LOOKUP_MAX    = 5;
	const SUBMIT_MAX    = 3;

	/**
	 * Okna czasowe inicjalizowane leniwie (stałe nie mogą używać MINUTE_IN_SECONDS
	 * w starszych PHP w deklaracji const wyrażeniowej zależnej od WP).
	 */
	const LOOKUP_WINDOW = 600;  // 10 minut.
	const SUBMIT_WINDOW = 1800; // 30 minut.

	/**
	 * IP klienta – z REMOTE_ADDR po sanityzacji i walidacji.
	 *
	 * Domyślnie nie ufamy nagłówkom proxy (X-Forwarded-For są spoofowalne).
	 * Sklepy za proxy/CDN mogą nadpisać wartość filtrem 'sascom_rc_client_ip'.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$valid = filter_var( $ip, FILTER_VALIDATE_IP );
		$ip    = $valid ? $valid : 'invalid-ip';

		/**
		 * Pozwala nadpisać IP klienta (np. dla sklepów za proxy/CDN).
		 *
		 * @param string $ip Zwalidowany REMOTE_ADDR lub 'invalid-ip'.
		 */
		$ip = apply_filters( 'sascom_rc_client_ip', $ip );

		return ( is_string( $ip ) && '' !== $ip ) ? $ip : 'invalid-ip';
	}

	/**
	 * Klucz transientu – wyłącznie zahashowany (brak PII w plain text).
	 *
	 * @param string $bucket Nazwa koszyka (np. lookup_ip).
	 * @param string $value  Surowa wartość wejściowa (IP/e-mail/numer).
	 * @return string
	 */
	private static function transient_key( $bucket, $value ) {
		return self::PREFIX . $bucket . '_' . wp_hash( $bucket . '|' . $value );
	}

	/**
	 * Rejestruje próbę w danym koszyku i zwraca aktualny licznik.
	 *
	 * Okno stałe: inkrement nie przedłuża okna (zachowujemy znacznik 'start').
	 *
	 * @param string $bucket Koszyk.
	 * @param string $value  Wartość identyfikująca.
	 * @param int    $window Długość okna w sekundach.
	 * @return int Liczba prób w bieżącym oknie.
	 */
	private static function hit( $bucket, $value, $window ) {
		$key   = self::transient_key( $bucket, $value );
		$now   = time();
		$entry = get_transient( $key );

		if ( ! is_array( $entry ) || ! isset( $entry['start'], $entry['count'] ) || ( $now - (int) $entry['start'] ) >= $window ) {
			$entry = array(
				'count' => 0,
				'start' => $now,
			);
		}

		$entry['count']++;

		$remaining = $window - ( $now - (int) $entry['start'] );
		if ( $remaining < 1 ) {
			$remaining = 1;
		}

		set_transient( $key, $entry, $remaining );

		return (int) $entry['count'];
	}

	/**
	 * Czy akcja lookup jest zablokowana dla tych danych?
	 *
	 * IP liczone zawsze; e-mail tylko jeśli poprawny; numer tylko jeśli niepusty.
	 * Inkrementujemy wszystkie wymiary, a blokujemy, gdy którykolwiek przekroczy limit.
	 *
	 * @param string $ip           IP klienta.
	 * @param string $email        E-mail (może być pusty/niepoprawny).
	 * @param string $order_number Numer zamówienia (może być pusty).
	 * @return bool
	 */
	public static function is_lookup_blocked( $ip, $email, $order_number ) {
		$blocked = ( self::hit( 'lookup_ip', $ip, self::LOOKUP_WINDOW ) > self::LOOKUP_MAX );

		if ( is_email( $email ) ) {
			$blocked = ( self::hit( 'lookup_email', $email, self::LOOKUP_WINDOW ) > self::LOOKUP_MAX ) || $blocked;
		}

		if ( '' !== (string) $order_number ) {
			$blocked = ( self::hit( 'lookup_order', (string) $order_number, self::LOOKUP_WINDOW ) > self::LOOKUP_MAX ) || $blocked;
		}

		return $blocked;
	}

	/**
	 * Czy akcja submit jest zablokowana dla tych danych?
	 *
	 * IP liczone zawsze; e-mail tylko jeśli poprawny.
	 *
	 * @param string $ip    IP klienta.
	 * @param string $email E-mail (może być pusty/niepoprawny).
	 * @return bool
	 */
	public static function is_submit_blocked( $ip, $email ) {
		$blocked = ( self::hit( 'submit_ip', $ip, self::SUBMIT_WINDOW ) > self::SUBMIT_MAX );

		if ( is_email( $email ) ) {
			$blocked = ( self::hit( 'submit_email', $email, self::SUBMIT_WINDOW ) > self::SUBMIT_MAX ) || $blocked;
		}

		return $blocked;
	}
}
