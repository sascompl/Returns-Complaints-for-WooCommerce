<?php
/**
 * Powiadomienia e-mail: do klienta oraz do administratora sklepu.
 *
 * @package Sascom_RC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa obsługująca wysyłkę wiadomości e-mail.
 */
class Sascom_RC_Emails {

	/**
	 * Zbiera dane zgłoszenia z meta.
	 *
	 * @param int $post_id ID zgłoszenia.
	 * @return array
	 */
	protected function get_request_data( $post_id ) {
		return array(
			'order_number'  => (string) get_post_meta( $post_id, Sascom_RC_CPT::META_ORDER_NUMBER, true ),
			'email'         => (string) get_post_meta( $post_id, Sascom_RC_CPT::META_EMAIL, true ),
			'customer_name' => (string) get_post_meta( $post_id, Sascom_RC_CPT::META_CUSTOMER_NAME, true ),
			'type'          => (string) get_post_meta( $post_id, Sascom_RC_CPT::META_TYPE, true ),
			'reason'        => (string) get_post_meta( $post_id, Sascom_RC_CPT::META_REASON, true ),
			'message'       => (string) get_post_meta( $post_id, Sascom_RC_CPT::META_MESSAGE, true ),
			'status'        => (string) get_post_meta( $post_id, Sascom_RC_CPT::META_STATUS, true ),
			'products'      => (array) get_post_meta( $post_id, Sascom_RC_CPT::META_PRODUCTS, true ),
		);
	}

	/**
	 * Buduje czytelną listę produktów (tekst).
	 *
	 * @param array $products Wybrane produkty.
	 * @return string
	 */
	protected function format_products( array $products ) {
		$lines = array();
		foreach ( $products as $product ) {
			if ( empty( $product['name'] ) ) {
				continue;
			}
			$qty     = isset( $product['qty'] ) ? (int) $product['qty'] : 1;
			$lines[] = '- ' . $product['name'] . ' x' . $qty;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Nagłówki wiadomości (HTML wyłączony – wysyłamy plain text).
	 *
	 * @return array
	 */
	protected function headers() {
		return array( 'Content-Type: text/plain; charset=UTF-8' );
	}

	/**
	 * E-mail z potwierdzeniem do klienta.
	 *
	 * @param int $post_id ID zgłoszenia.
	 * @return bool
	 */
	public function send_customer_confirmation( $post_id ) {
		$data = $this->get_request_data( $post_id );

		if ( ! is_email( $data['email'] ) ) {
			return false;
		}

		$blog_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$type_label = Sascom_RC_CPT::get_type_label( $data['type'] );

		/* translators: %s: numer zamówienia */
		$subject = sprintf( __( 'Potwierdzenie zgłoszenia – zamówienie #%s', 'returns-complaints-for-woocommerce' ), $data['order_number'] );

		$body  = sprintf(
			/* translators: %s: imię i nazwisko klienta */
			__( 'Witaj %s,', 'returns-complaints-for-woocommerce' ),
			$data['customer_name']
		) . "\n\n";
		$body .= __( 'Dziękujemy za przesłanie zgłoszenia. Poniżej podsumowanie:', 'returns-complaints-for-woocommerce' ) . "\n\n";
		$body .= __( 'Numer zamówienia:', 'returns-complaints-for-woocommerce' ) . ' ' . $data['order_number'] . "\n";
		$body .= __( 'Typ zgłoszenia:', 'returns-complaints-for-woocommerce' ) . ' ' . $type_label . "\n";

		if ( '' !== $data['reason'] ) {
			$body .= __( 'Powód:', 'returns-complaints-for-woocommerce' ) . ' ' . $data['reason'] . "\n";
		}

		$products_text = $this->format_products( $data['products'] );
		if ( '' !== $products_text ) {
			$body .= "\n" . __( 'Zgłoszone produkty:', 'returns-complaints-for-woocommerce' ) . "\n" . $products_text . "\n";
		}

		if ( 'manual_verification' === $data['status'] ) {
			$body .= "\n" . __( 'Uwaga: ponieważ zamówienie jest starsze niż 30 dni, sprawa zostanie zweryfikowana ręcznie przez nasz zespół.', 'returns-complaints-for-woocommerce' ) . "\n";
		}

		$body .= "\n" . __( 'Skontaktujemy się z Tobą w sprawie dalszych kroków.', 'returns-complaints-for-woocommerce' ) . "\n\n";
		$body .= sprintf(
			/* translators: %s: nazwa sklepu */
			__( 'Pozdrawiamy,%s', 'returns-complaints-for-woocommerce' ),
			"\n" . $blog_name
		);

		return wp_mail( $data['email'], $subject, $body, $this->headers() );
	}

	/**
	 * E-mail z powiadomieniem do administratora sklepu.
	 *
	 * @param int $post_id ID zgłoszenia.
	 * @return bool
	 */
	public function send_admin_notification( $post_id ) {
		$data = $this->get_request_data( $post_id );

		/**
		 * Adres e-mail odbiorcy powiadomień administracyjnych.
		 *
		 * @param string $recipient Domyślnie admin_email.
		 * @param int    $post_id   ID zgłoszenia.
		 */
		$recipient = apply_filters( 'sascom_rc_admin_email', get_option( 'admin_email' ), $post_id );

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$type_label = Sascom_RC_CPT::get_type_label( $data['type'] );
		$edit_link  = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		/* translators: 1: typ zgłoszenia, 2: numer zamówienia */
		$subject = sprintf(
			__( 'Nowe zgłoszenie: %1$s – zamówienie #%2$s', 'returns-complaints-for-woocommerce' ),
			$type_label,
			$data['order_number']
		);

		$body  = __( 'Wpłynęło nowe zgłoszenie zwrotu/reklamacji.', 'returns-complaints-for-woocommerce' ) . "\n\n";
		$body .= __( 'Numer zamówienia:', 'returns-complaints-for-woocommerce' ) . ' ' . $data['order_number'] . "\n";
		$body .= __( 'Klient:', 'returns-complaints-for-woocommerce' ) . ' ' . $data['customer_name'] . "\n";
		$body .= __( 'E-mail:', 'returns-complaints-for-woocommerce' ) . ' ' . $data['email'] . "\n";
		$body .= __( 'Typ zgłoszenia:', 'returns-complaints-for-woocommerce' ) . ' ' . $type_label . "\n";
		$body .= __( 'Status:', 'returns-complaints-for-woocommerce' ) . ' ' . Sascom_RC_CPT::get_status_label( $data['status'] ) . "\n";

		if ( '' !== $data['reason'] ) {
			$body .= __( 'Powód:', 'returns-complaints-for-woocommerce' ) . ' ' . $data['reason'] . "\n";
		}
		if ( '' !== $data['message'] ) {
			$body .= __( 'Wiadomość klienta:', 'returns-complaints-for-woocommerce' ) . ' ' . $data['message'] . "\n";
		}

		$products_text = $this->format_products( $data['products'] );
		if ( '' !== $products_text ) {
			$body .= "\n" . __( 'Zgłoszone produkty:', 'returns-complaints-for-woocommerce' ) . "\n" . $products_text . "\n";
		}

		$body .= "\n" . __( 'Podgląd zgłoszenia:', 'returns-complaints-for-woocommerce' ) . ' ' . $edit_link . "\n";

		return wp_mail( $recipient, $subject, $body, $this->headers() );
	}
}
