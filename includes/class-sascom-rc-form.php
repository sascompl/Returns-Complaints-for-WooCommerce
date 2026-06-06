<?php
/**
 * Publiczny formularz zgłoszeniowy: shortcode, AJAX, walidacja i zapis.
 *
 * @package Sascom_RC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa obsługująca formularz po stronie frontu.
 */
class Sascom_RC_Form {

	/**
	 * Techniczny zakres formularza online (w dniach).
	 *
	 * To NIE jest ustawowy termin zwrotu, tylko zakres zamówień
	 * obsługiwanych przez formularz online.
	 */
	const DAYS_LIMIT = 30;

	/**
	 * Nazwa akcji nonce.
	 */
	const NONCE_ACTION = 'sascom_rc_nonce';

	/**
	 * Rejestracja hooków.
	 */
	public function hooks() {
		add_shortcode( 'sascom_rc_returns_form', array( $this, 'render_shortcode' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// AJAX: krok 1 – weryfikacja zamówienia (zalogowani i goście).
		add_action( 'wp_ajax_sascom_rc_lookup_order', array( $this, 'ajax_lookup_order' ) );
		add_action( 'wp_ajax_nopriv_sascom_rc_lookup_order', array( $this, 'ajax_lookup_order' ) );

		// AJAX: krok 2 – zapis zgłoszenia.
		add_action( 'wp_ajax_sascom_rc_submit_return', array( $this, 'ajax_submit_return' ) );
		add_action( 'wp_ajax_nopriv_sascom_rc_submit_return', array( $this, 'ajax_submit_return' ) );
	}

	/**
	 * Rejestracja zasobów (CSS/JS).
	 */
	public function register_assets() {
		wp_register_style(
			'sascom-rc',
			SASCOM_RC_URL . 'assets/css/sascom-rc.css',
			array(),
			SASCOM_RC_VERSION
		);

		wp_register_script(
			'sascom-rc',
			SASCOM_RC_URL . 'assets/js/sascom-rc.js',
			array(),
			SASCOM_RC_VERSION,
			true
		);

		wp_localize_script(
			'sascom-rc',
			'SascomRC',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
				'typeReturn' => Sascom_RC_CPT::TYPE_RETURN,
				'i18n'       => array(
					'genericError'  => __( 'Wystąpił błąd. Spróbuj ponownie.', 'returns-complaints-for-woocommerce' ),
					'selectProduct' => __( 'Wybierz przynajmniej jeden produkt.', 'returns-complaints-for-woocommerce' ),
					'selectType'    => __( 'Wybierz typ zgłoszenia.', 'returns-complaints-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Renderowanie shortcode'u [sascom_rc_returns_form].
	 *
	 * @return string
	 */
	public function render_shortcode() {
		wp_enqueue_style( 'sascom-rc' );
		wp_enqueue_script( 'sascom-rc' );

		ob_start();
		?>
		<div class="sascom-rc">
			<form id="sascom-rc-form" class="sascom-rc-form" autocomplete="off">

				<?php // Krok 1 – identyfikacja zamówienia. ?>
				<div class="sascom-rc-step sascom-rc-step-1" data-step="1">
					<h3><?php esc_html_e( 'Krok 1: Znajdź swoje zamówienie', 'returns-complaints-for-woocommerce' ); ?></h3>

					<p class="sascom-rc-field">
						<label for="sascom-rc-order-number"><?php esc_html_e( 'Numer zamówienia', 'returns-complaints-for-woocommerce' ); ?> <span class="sascom-rc-required">*</span></label>
						<input type="text" id="sascom-rc-order-number" name="order_number" required>
					</p>

					<p class="sascom-rc-field">
						<label for="sascom-rc-email"><?php esc_html_e( 'Adres e-mail użyty przy zakupie', 'returns-complaints-for-woocommerce' ); ?> <span class="sascom-rc-required">*</span></label>
						<input type="email" id="sascom-rc-email" name="email" required>
					</p>

					<p class="sascom-rc-actions">
						<button type="button" class="sascom-rc-button" id="sascom-rc-lookup-btn">
							<?php esc_html_e( 'Znajdź zamówienie', 'returns-complaints-for-woocommerce' ); ?>
						</button>
					</p>

					<div class="sascom-rc-message sascom-rc-lookup-message" role="alert" hidden></div>
				</div>

				<?php // Krok 2 – szczegóły zgłoszenia (wypełniany dynamicznie). ?>
				<div class="sascom-rc-step sascom-rc-step-2" data-step="2" hidden>
					<h3><?php esc_html_e( 'Krok 2: Szczegóły zgłoszenia', 'returns-complaints-for-woocommerce' ); ?></h3>

					<div class="sascom-rc-order-summary"></div>

					<div class="sascom-rc-field">
						<span class="sascom-rc-label"><?php esc_html_e( 'Typ zgłoszenia', 'returns-complaints-for-woocommerce' ); ?> <span class="sascom-rc-required">*</span></span>
						<?php foreach ( Sascom_RC_CPT::get_types() as $slug => $label ) : ?>
							<label class="sascom-rc-radio">
								<input type="radio" name="type" value="<?php echo esc_attr( $slug ); ?>">
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="sascom-rc-message sascom-rc-type-notice" hidden></div>

					<div class="sascom-rc-field sascom-rc-products-wrap">
						<span class="sascom-rc-label"><?php esc_html_e( 'Wybierz produkty, których dotyczy zgłoszenie', 'returns-complaints-for-woocommerce' ); ?> <span class="sascom-rc-required">*</span></span>
						<div class="sascom-rc-products"></div>
					</div>

					<p class="sascom-rc-field">
						<label for="sascom-rc-reason"><?php esc_html_e( 'Powód zgłoszenia', 'returns-complaints-for-woocommerce' ); ?></label>
						<input type="text" id="sascom-rc-reason" name="reason" maxlength="200">
					</p>

					<p class="sascom-rc-field">
						<label for="sascom-rc-customer-message"><?php esc_html_e( 'Dodatkowa wiadomość / opis problemu', 'returns-complaints-for-woocommerce' ); ?></label>
						<textarea id="sascom-rc-customer-message" name="customer_message" rows="4" maxlength="2000"></textarea>
					</p>

					<p class="sascom-rc-field sascom-rc-bank-field" hidden>
						<label for="sascom-rc-bank-account"><?php esc_html_e( 'Numer konta bankowego do zwrotu środków (opcjonalnie)', 'returns-complaints-for-woocommerce' ); ?></label>
						<input type="text" id="sascom-rc-bank-account" name="bank_account" maxlength="50">
					</p>

					<div class="sascom-rc-message sascom-rc-submit-message" role="alert" hidden></div>

					<p class="sascom-rc-actions">
						<button type="button" class="sascom-rc-button sascom-rc-button-secondary" id="sascom-rc-back-btn">
							<?php esc_html_e( 'Wstecz', 'returns-complaints-for-woocommerce' ); ?>
						</button>
						<button type="submit" class="sascom-rc-button" id="sascom-rc-submit-btn">
							<?php esc_html_e( 'Wyślij zgłoszenie', 'returns-complaints-for-woocommerce' ); ?>
						</button>
					</p>
				</div>

				<?php // Ekran potwierdzenia. ?>
				<div class="sascom-rc-step sascom-rc-step-done" data-step="done" hidden>
					<div class="sascom-rc-message sascom-rc-success"></div>
				</div>

			</form>
			<noscript>
				<p><?php esc_html_e( 'Do działania formularza wymagana jest obsługa JavaScript.', 'returns-complaints-for-woocommerce' ); ?></p>
			</noscript>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Weryfikacja zamówienia po numerze i e-mailu.
	 *
	 * Zwraca obiekt zamówienia tylko, gdy e-mail zgadza się z billing_email.
	 * W każdym innym przypadku zwraca null (brak ujawniania danych).
	 *
	 * @param string $order_number Numer zamówienia wpisany przez klienta.
	 * @param string $email        Adres e-mail.
	 * @return WC_Order|null
	 */
	protected function get_verified_order( $order_number, $email ) {
		$order_number = trim( $order_number );
		$email        = sanitize_email( $email );

		if ( '' === $order_number || ! is_email( $email ) ) {
			return null;
		}

		// Próba dopasowania po ID (w większości sklepów numer = ID).
		$order_id = absint( preg_replace( '/[^0-9]/', '', $order_number ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			return null;
		}

		// Numer wyświetlany może różnić się od ID – sprawdzamy zgodność.
		if ( (string) $order->get_order_number() !== (string) $order_number
			&& (string) $order->get_id() !== (string) $order_number ) {
			return null;
		}

		// Kluczowa kontrola: e-mail musi zgadzać się z billing_email.
		if ( strtolower( trim( $order->get_billing_email() ) ) !== strtolower( $email ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Czy zamówienie mieści się w technicznym oknie formularza (30 dni)?
	 *
	 * @param WC_Order $order Zamówienie.
	 * @return bool
	 */
	protected function is_within_limit( WC_Order $order ) {
		$date_created = $order->get_date_created();
		if ( ! $date_created ) {
			return false;
		}

		$created_ts = $date_created->getTimestamp();
		$limit_ts   = time() - ( self::DAYS_LIMIT * DAY_IN_SECONDS );

		return $created_ts >= $limit_ts;
	}

	/**
	 * Bezpieczne przycięcie długości tekstu (wielobajtowe).
	 *
	 * @param string $text Tekst wejściowy.
	 * @param int    $max  Maksymalna liczba znaków.
	 * @return string
	 */
	protected function limit_length( $text, $max ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $max );
		}
		return substr( $text, 0, $max );
	}

	/**
	 * Lista pozycji zamówienia do prezentacji / walidacji.
	 *
	 * @param WC_Order $order Zamówienie.
	 * @return array Tablica: item_id => array( name, qty ).
	 */
	protected function get_order_items( WC_Order $order ) {
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$items[ $item_id ] = array(
				'name' => $item->get_name(),
				'qty'  => (int) $item->get_quantity(),
			);
		}
		return $items;
	}

	/**
	 * AJAX – krok 1: weryfikacja zamówienia i zwrot listy produktów.
	 */
	public function ajax_lookup_order() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		$order = $this->get_verified_order( $order_number, $email );

		if ( ! $order ) {
			// Komunikat celowo ogólny – brak ujawniania, czy zamówienie istnieje.
			wp_send_json_error(
				array(
					'message' => __( 'Nie znaleziono zamówienia dla podanych danych. Sprawdź numer zamówienia oraz adres e-mail.', 'returns-complaints-for-woocommerce' ),
				)
			);
		}

		$products = array();
		foreach ( $this->get_order_items( $order ) as $item_id => $item ) {
			$products[] = array(
				'item_id' => $item_id,
				'name'    => $item['name'],
				'qty'     => $item['qty'],
			);
		}

		wp_send_json_success(
			array(
				'order_number'  => $order->get_order_number(),
				'customer_name' => trim( $order->get_formatted_billing_full_name() ),
				'order_date'    => wc_format_datetime( $order->get_date_created() ),
				'within_limit'  => $this->is_within_limit( $order ),
				'products'      => $products,
				'notices'       => array(
					'return_over_limit'    => __( 'Formularz online obejmuje zamówienia z ostatnich 30 dni. Jeżeli uważasz, że Twoje zgłoszenie nadal jest zasadne, skontaktuj się z nami mailowo.', 'returns-complaints-for-woocommerce' ),
					'complaint_over_limit' => __( 'To zamówienie jest starsze niż 30 dni. Możesz wysłać reklamację, ale sprawa zostanie zweryfikowana ręcznie.', 'returns-complaints-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * AJAX – krok 2: zapis zgłoszenia.
	 */
	public function ajax_submit_return() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		// --- Sanitizacja inputów ---
		$order_number     = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
		$email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$type             = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$reason           = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$customer_message = isset( $_POST['customer_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['customer_message'] ) ) : '';
		$bank_account     = isset( $_POST['bank_account'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_account'] ) ) : '';

		// Pozycje: klucze (item_id) i wartości (ilości) sanityzowane od razu do dodatnich int.
		$raw_items = array();
		if ( isset( $_POST['items'] ) && is_array( $_POST['items'] ) ) {
			foreach ( wp_unslash( $_POST['items'] ) as $item_key => $item_qty ) {
				$raw_items[ absint( $item_key ) ] = absint( $item_qty );
			}
		}

		// Serwerowe limity długości (defense in depth – maxlength w HTML można obejść).
		$reason           = $this->limit_length( $reason, 200 );
		$customer_message = $this->limit_length( $customer_message, 2000 );
		$bank_account     = $this->limit_length( $bank_account, 50 );

		// --- Walidacja zamówienia + e-mail (ponowna, po stronie serwera) ---
		$order = $this->get_verified_order( $order_number, $email );
		if ( ! $order ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nie znaleziono zamówienia dla podanych danych.', 'returns-complaints-for-woocommerce' ),
				)
			);
		}

		// --- Walidacja typu zgłoszenia ---
		$types = Sascom_RC_CPT::get_types();
		if ( ! isset( $types[ $type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Wybierz prawidłowy typ zgłoszenia.', 'returns-complaints-for-woocommerce' ) ) );
		}

		$within_limit = $this->is_within_limit( $order );

		// --- Reguła 30 dni dla zwrotu ---
		if ( Sascom_RC_CPT::TYPE_RETURN === $type && ! $within_limit ) {
			wp_send_json_error(
				array(
					'message' => __( 'Formularz online obejmuje zamówienia z ostatnich 30 dni. Jeżeli uważasz, że Twoje zgłoszenie nadal jest zasadne, skontaktuj się z nami mailowo.', 'returns-complaints-for-woocommerce' ),
				)
			);
		}

		// --- Walidacja wybranych produktów względem pozycji zamówienia ---
		$order_items     = $this->get_order_items( $order );
		$chosen_products = array();

		foreach ( $raw_items as $item_id => $qty ) {
			$item_id = absint( $item_id );
			$qty     = absint( $qty );

			if ( ! $item_id || $qty < 1 || ! isset( $order_items[ $item_id ] ) ) {
				continue;
			}

			$max_qty = $order_items[ $item_id ]['qty'];
			if ( $qty > $max_qty ) {
				$qty = $max_qty;
			}

			$chosen_products[] = array(
				'item_id' => $item_id,
				'name'    => $order_items[ $item_id ]['name'],
				'qty'     => $qty,
			);
		}

		if ( empty( $chosen_products ) ) {
			wp_send_json_error( array( 'message' => __( 'Wybierz przynajmniej jeden produkt.', 'returns-complaints-for-woocommerce' ) ) );
		}

		// --- Status zgłoszenia ---
		$status = 'new';
		if ( Sascom_RC_CPT::TYPE_COMPLAINT === $type && ! $within_limit ) {
			$status = 'manual_verification';
		}

		$customer_name = trim( $order->get_formatted_billing_full_name() );

		// --- Utworzenie wpisu CPT ---
		$post_id = wp_insert_post(
			array(
				'post_type'   => Sascom_RC_CPT::POST_TYPE,
				'post_status' => 'private',
				/* translators: 1: typ zgłoszenia, 2: numer zamówienia */
				'post_title'  => sprintf(
					__( '%1$s – zamówienie #%2$s', 'returns-complaints-for-woocommerce' ),
					Sascom_RC_CPT::get_type_label( $type ),
					$order->get_order_number()
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Nie udało się zapisać zgłoszenia. Spróbuj ponownie.', 'returns-complaints-for-woocommerce' ) ) );
		}

		// --- Zapis meta zgłoszenia ---
		update_post_meta( $post_id, Sascom_RC_CPT::META_ORDER_ID, $order->get_id() );
		update_post_meta( $post_id, Sascom_RC_CPT::META_ORDER_NUMBER, $order->get_order_number() );
		update_post_meta( $post_id, Sascom_RC_CPT::META_EMAIL, $email );
		update_post_meta( $post_id, Sascom_RC_CPT::META_CUSTOMER_NAME, $customer_name );
		update_post_meta( $post_id, Sascom_RC_CPT::META_PRODUCTS, $chosen_products );
		update_post_meta( $post_id, Sascom_RC_CPT::META_TYPE, $type );
		update_post_meta( $post_id, Sascom_RC_CPT::META_REASON, $reason );
		update_post_meta( $post_id, Sascom_RC_CPT::META_MESSAGE, $customer_message );
		update_post_meta( $post_id, Sascom_RC_CPT::META_BANK_ACCOUNT, $bank_account );
		update_post_meta( $post_id, Sascom_RC_CPT::META_STATUS, $status );

		// --- Powiązanie ze zamówieniem WooCommerce ---
		$this->link_to_order( $order, $post_id, $type );

		// --- E-maile ---
		$emails = new Sascom_RC_Emails();
		$emails->send_customer_confirmation( $post_id );
		$emails->send_admin_notification( $post_id );

		/**
		 * Akcja po utworzeniu zgłoszenia.
		 *
		 * @param int      $post_id ID zgłoszenia.
		 * @param WC_Order $order   Powiązane zamówienie.
		 */
		do_action( 'sascom_rc_request_created', $post_id, $order );

		$success_message = __( 'Dziękujemy! Twoje zgłoszenie zostało przyjęte. Potwierdzenie wysłaliśmy na podany adres e-mail.', 'returns-complaints-for-woocommerce' );
		if ( 'manual_verification' === $status ) {
			$success_message .= ' ' . __( 'Ponieważ zamówienie jest starsze niż 30 dni, sprawa zostanie zweryfikowana ręcznie.', 'returns-complaints-for-woocommerce' );
		}

		wp_send_json_success( array( 'message' => $success_message ) );
	}

	/**
	 * Powiązanie zgłoszenia ze zamówieniem WooCommerce.
	 *
	 * - dodaje prywatną notatkę do zamówienia,
	 * - ustawia flagę _sascom_rc_has_request = yes,
	 * - dopisuje ID zgłoszenia do _sascom_rc_request_ids,
	 * - NIE zmienia statusu zamówienia i NIE wykonuje refundu.
	 *
	 * @param WC_Order $order   Zamówienie.
	 * @param int      $post_id ID zgłoszenia.
	 * @param string   $type    Typ zgłoszenia.
	 */
	protected function link_to_order( WC_Order $order, $post_id, $type ) {
		$edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		// Prywatna notatka do zamówienia (widoczna tylko dla administracji).
		$order->add_order_note(
			sprintf(
				/* translators: 1: typ zgłoszenia, 2: ID zgłoszenia, 3: adres URL zgłoszenia */
				__( 'Returns & Complaints: utworzono zgłoszenie „%1$s” (#%2$d). Podgląd: %3$s', 'returns-complaints-for-woocommerce' ),
				Sascom_RC_CPT::get_type_label( $type ),
				$post_id,
				$edit_link
			)
		);

		// Flaga obecności zgłoszenia.
		$order->update_meta_data( Sascom_RC_CPT::ORDER_META_HAS_REQUEST, 'yes' );

		// Lista ID zgłoszeń powiązanych z zamówieniem.
		$ids = $order->get_meta( Sascom_RC_CPT::ORDER_META_REQUEST_IDS );
		$ids = is_array( $ids ) ? $ids : array();
		if ( ! in_array( $post_id, $ids, true ) ) {
			$ids[] = $post_id;
		}
		$order->update_meta_data( Sascom_RC_CPT::ORDER_META_REQUEST_IDS, $ids );

		$order->save();
	}
}
