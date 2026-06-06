<?php
/**
 * Obsługa panelu administracyjnego: lista zgłoszeń, metaboxy,
 * edycja statusu oraz integracja po stronie zamówienia WooCommerce.
 *
 * @package Sascom_RC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa obsługująca widoki w panelu wp-admin.
 */
class Sascom_RC_Admin {

	/**
	 * Nazwa akcji nonce dla metaboxa statusu.
	 */
	const STATUS_NONCE = 'sascom_rc_save_status';

	/**
	 * Rejestracja hooków.
	 */
	public function hooks() {
		// Metaboxy widoku zgłoszenia.
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . Sascom_RC_CPT::POST_TYPE, array( $this, 'save_status' ), 10, 2 );

		// Kolumny listy zgłoszeń.
		add_filter( 'manage_' . Sascom_RC_CPT::POST_TYPE . '_posts_columns', array( $this, 'request_columns' ) );
		add_action( 'manage_' . Sascom_RC_CPT::POST_TYPE . '_posts_custom_column', array( $this, 'render_request_column' ), 10, 2 );

		// Integracja po stronie zamówienia WooCommerce (panel zamówienia).
		add_action( 'add_meta_boxes', array( $this, 'register_order_meta_box' ) );

		// Kolumna „Zwrot/Reklamacja” na liście zamówień (HPOS + klasyczne).
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'order_list_columns' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'render_order_list_column' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_list_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_list_column_legacy' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ *
	 * Widok zgłoszenia (CPT)
	 * ------------------------------------------------------------------ */

	/**
	 * Rejestracja metaboxów na ekranie zgłoszenia.
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'sascom_rc_details',
			__( 'Szczegóły zgłoszenia', 'returns-complaints-for-woocommerce' ),
			array( $this, 'render_details_box' ),
			Sascom_RC_CPT::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'sascom_rc_status',
			__( 'Status zgłoszenia', 'returns-complaints-for-woocommerce' ),
			array( $this, 'render_status_box' ),
			Sascom_RC_CPT::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Pomocniczy wiersz tabeli „etykieta : wartość”.
	 *
	 * @param string $label Etykieta.
	 * @param string $value Wartość (zostanie escapowana).
	 */
	protected function row( $label, $value ) {
		echo '<tr><th style="text-align:left;width:200px;vertical-align:top;padding:6px 10px 6px 0;">';
		echo esc_html( $label );
		echo '</th><td style="padding:6px 0;">';
		echo esc_html( $value );
		echo '</td></tr>';
	}

	/**
	 * Renderowanie metaboxa ze szczegółami zgłoszenia.
	 *
	 * @param WP_Post $post Wpis zgłoszenia.
	 */
	public function render_details_box( $post ) {
		$order_id      = (int) get_post_meta( $post->ID, Sascom_RC_CPT::META_ORDER_ID, true );
		$order_number  = (string) get_post_meta( $post->ID, Sascom_RC_CPT::META_ORDER_NUMBER, true );
		$email         = (string) get_post_meta( $post->ID, Sascom_RC_CPT::META_EMAIL, true );
		$customer_name = (string) get_post_meta( $post->ID, Sascom_RC_CPT::META_CUSTOMER_NAME, true );
		$type          = (string) get_post_meta( $post->ID, Sascom_RC_CPT::META_TYPE, true );
		$reason        = (string) get_post_meta( $post->ID, Sascom_RC_CPT::META_REASON, true );
		$message       = (string) get_post_meta( $post->ID, Sascom_RC_CPT::META_MESSAGE, true );
		$bank_account  = (string) get_post_meta( $post->ID, Sascom_RC_CPT::META_BANK_ACCOUNT, true );
		$products      = (array) get_post_meta( $post->ID, Sascom_RC_CPT::META_PRODUCTS, true );

		echo '<table class="widefat" style="border:0;box-shadow:none;">';

		$this->row( __( 'Data zgłoszenia', 'returns-complaints-for-woocommerce' ), get_the_date( 'Y-m-d H:i', $post ) );
		$this->row( __( 'Typ zgłoszenia', 'returns-complaints-for-woocommerce' ), Sascom_RC_CPT::get_type_label( $type ) );
		$this->row( __( 'Numer zamówienia', 'returns-complaints-for-woocommerce' ), $order_number );

		// Link do zamówienia WooCommerce.
		echo '<tr><th style="text-align:left;padding:6px 10px 6px 0;">';
		echo esc_html__( 'Zamówienie WooCommerce', 'returns-complaints-for-woocommerce' );
		echo '</th><td style="padding:6px 0;">';
		if ( $order_id ) {
			$order_link = $this->get_order_edit_link( $order_id );
			printf(
				'<a href="%s">%s</a>',
				esc_url( $order_link ),
				/* translators: %d: ID zamówienia */
				esc_html( sprintf( __( 'Otwórz zamówienie #%d', 'returns-complaints-for-woocommerce' ), $order_id ) )
			);
		} else {
			echo esc_html__( 'Brak powiązanego zamówienia', 'returns-complaints-for-woocommerce' );
		}
		echo '</td></tr>';

		$this->row( __( 'Klient', 'returns-complaints-for-woocommerce' ), $customer_name );
		$this->row( __( 'E-mail', 'returns-complaints-for-woocommerce' ), $email );

		if ( '' !== $reason ) {
			$this->row( __( 'Powód', 'returns-complaints-for-woocommerce' ), $reason );
		}
		if ( '' !== $bank_account ) {
			$this->row( __( 'Numer konta bankowego', 'returns-complaints-for-woocommerce' ), $bank_account );
		}

		echo '</table>';

		// Lista zgłoszonych produktów.
		echo '<h4>' . esc_html__( 'Zgłoszone produkty', 'returns-complaints-for-woocommerce' ) . '</h4>';
		if ( ! empty( $products ) ) {
			echo '<ul style="margin-left:18px;list-style:disc;">';
			foreach ( $products as $product ) {
				$name = isset( $product['name'] ) ? $product['name'] : '';
				$qty  = isset( $product['qty'] ) ? (int) $product['qty'] : 1;
				echo '<li>' . esc_html( $name ) . ' &times; ' . esc_html( $qty ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'Brak wskazanych produktów.', 'returns-complaints-for-woocommerce' ) . '</p>';
		}

		// Wiadomość klienta.
		if ( '' !== $message ) {
			echo '<h4>' . esc_html__( 'Wiadomość klienta', 'returns-complaints-for-woocommerce' ) . '</h4>';
			echo '<p style="white-space:pre-wrap;">' . esc_html( $message ) . '</p>';
		}
	}

	/**
	 * Renderowanie metaboxa zmiany statusu.
	 *
	 * @param WP_Post $post Wpis zgłoszenia.
	 */
	public function render_status_box( $post ) {
		$current = (string) get_post_meta( $post->ID, Sascom_RC_CPT::META_STATUS, true );
		if ( '' === $current ) {
			$current = 'new';
		}

		wp_nonce_field( self::STATUS_NONCE, 'sascom_rc_status_nonce' );

		echo '<p><label for="sascom_rc_status_field"><strong>';
		echo esc_html__( 'Status', 'returns-complaints-for-woocommerce' );
		echo '</strong></label></p>';

		echo '<select id="sascom_rc_status_field" name="sascom_rc_status_field" style="width:100%;">';
		foreach ( Sascom_RC_CPT::get_statuses() as $slug => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $slug ),
				selected( $current, $slug, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		echo '<p class="description">' . esc_html__( 'Zapisz zgłoszenie (przycisk „Aktualizuj”), aby utrwalić status.', 'returns-complaints-for-woocommerce' ) . '</p>';
	}

	/**
	 * Zapis statusu zgłoszenia.
	 *
	 * @param int     $post_id ID wpisu.
	 * @param WP_Post $post    Wpis.
	 */
	public function save_status( $post_id, $post ) {
		// Weryfikacja nonce.
		if ( ! isset( $_POST['sascom_rc_status_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sascom_rc_status_nonce'] ) ), self::STATUS_NONCE ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['sascom_rc_status_field'] ) ) {
			return;
		}

		$new_status = sanitize_key( wp_unslash( $_POST['sascom_rc_status_field'] ) );
		$statuses   = Sascom_RC_CPT::get_statuses();

		if ( isset( $statuses[ $new_status ] ) ) {
			update_post_meta( $post_id, Sascom_RC_CPT::META_STATUS, $new_status );
		}
	}

	/**
	 * Definicja kolumn listy zgłoszeń.
	 *
	 * @param array $columns Istniejące kolumny.
	 * @return array
	 */
	public function request_columns( $columns ) {
		$new = array();
		$new['cb'] = isset( $columns['cb'] ) ? $columns['cb'] : '';
		$new['title']                  = __( 'Zgłoszenie', 'returns-complaints-for-woocommerce' );
		$new['sascom_rc_order']        = __( 'Zamówienie', 'returns-complaints-for-woocommerce' );
		$new['sascom_rc_type']         = __( 'Typ', 'returns-complaints-for-woocommerce' );
		$new['sascom_rc_status']       = __( 'Status', 'returns-complaints-for-woocommerce' );
		$new['sascom_rc_email']        = __( 'E-mail', 'returns-complaints-for-woocommerce' );
		$new['date']                   = isset( $columns['date'] ) ? $columns['date'] : __( 'Data', 'returns-complaints-for-woocommerce' );

		return $new;
	}

	/**
	 * Renderowanie zawartości kolumn listy zgłoszeń.
	 *
	 * @param string $column  Klucz kolumny.
	 * @param int    $post_id ID wpisu.
	 */
	public function render_request_column( $column, $post_id ) {
		switch ( $column ) {
			case 'sascom_rc_order':
				$order_id     = (int) get_post_meta( $post_id, Sascom_RC_CPT::META_ORDER_ID, true );
				$order_number = (string) get_post_meta( $post_id, Sascom_RC_CPT::META_ORDER_NUMBER, true );
				if ( $order_id ) {
					printf(
						'<a href="%s">#%s</a>',
						esc_url( $this->get_order_edit_link( $order_id ) ),
						esc_html( $order_number )
					);
				} else {
					echo esc_html( $order_number );
				}
				break;

			case 'sascom_rc_type':
				$type = (string) get_post_meta( $post_id, Sascom_RC_CPT::META_TYPE, true );
				echo esc_html( Sascom_RC_CPT::get_type_label( $type ) );
				break;

			case 'sascom_rc_status':
				$status = (string) get_post_meta( $post_id, Sascom_RC_CPT::META_STATUS, true );
				echo esc_html( Sascom_RC_CPT::get_status_label( $status ) );
				break;

			case 'sascom_rc_email':
				echo esc_html( (string) get_post_meta( $post_id, Sascom_RC_CPT::META_EMAIL, true ) );
				break;
		}
	}

	/* ------------------------------------------------------------------ *
	 * Integracja po stronie zamówienia WooCommerce
	 * ------------------------------------------------------------------ */

	/**
	 * Rejestracja metaboxa na ekranie zamówienia (HPOS + klasyczne).
	 */
	public function register_order_meta_box() {
		$hpos_enabled = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$screen = $hpos_enabled ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box(
			'sascom_rc_order_box',
			__( 'Zwroty i reklamacje', 'returns-complaints-for-woocommerce' ),
			array( $this, 'render_order_meta_box' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Renderowanie metaboxa zgłoszeń na ekranie zamówienia.
	 *
	 * @param WP_Post|WC_Order $post_or_order Obiekt zależny od trybu (HPOS / klasyczny).
	 */
	public function render_order_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}

		$ids = $order->get_meta( Sascom_RC_CPT::ORDER_META_REQUEST_IDS );
		$ids = is_array( $ids ) ? array_filter( array_map( 'absint', $ids ) ) : array();

		if ( empty( $ids ) ) {
			echo '<p>' . esc_html__( 'Brak zgłoszeń dla tego zamówienia.', 'returns-complaints-for-woocommerce' ) . '</p>';
			return;
		}

		echo '<p><strong>' . esc_html__( 'To zamówienie ma zgłoszenie zwrotu/reklamacji:', 'returns-complaints-for-woocommerce' ) . '</strong></p>';
		echo '<ul style="margin-left:16px;list-style:disc;">';
		foreach ( $ids as $request_id ) {
			if ( Sascom_RC_CPT::POST_TYPE !== get_post_type( $request_id ) ) {
				continue;
			}
			$type   = (string) get_post_meta( $request_id, Sascom_RC_CPT::META_TYPE, true );
			$status = (string) get_post_meta( $request_id, Sascom_RC_CPT::META_STATUS, true );
			$link   = admin_url( 'post.php?post=' . $request_id . '&action=edit' );

			printf(
				'<li><a href="%1$s">%2$s</a> – %3$s</li>',
				esc_url( $link ),
				esc_html( Sascom_RC_CPT::get_type_label( $type ) ),
				esc_html( Sascom_RC_CPT::get_status_label( $status ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Dodanie kolumny „Zwrot/Reklamacja” do listy zamówień.
	 *
	 * @param array $columns Kolumny.
	 * @return array
	 */
	public function order_list_columns( $columns ) {
		$columns['sascom_rc_flag'] = __( 'Zwrot/Reklamacja', 'returns-complaints-for-woocommerce' );
		return $columns;
	}

	/**
	 * Renderowanie kolumny na liście zamówień (HPOS).
	 *
	 * @param string   $column Klucz kolumny.
	 * @param WC_Order $order  Zamówienie.
	 */
	public function render_order_list_column( $column, $order ) {
		if ( 'sascom_rc_flag' !== $column || ! $order instanceof WC_Order ) {
			return;
		}
		$this->order_flag_markup( $order );
	}

	/**
	 * Renderowanie kolumny na liście zamówień (klasyczne CPT shop_order).
	 *
	 * @param string $column  Klucz kolumny.
	 * @param int    $post_id ID zamówienia.
	 */
	public function render_order_list_column_legacy( $column, $post_id ) {
		if ( 'sascom_rc_flag' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			$this->order_flag_markup( $order );
		}
	}

	/**
	 * Oznaczenie zamówienia – wyłącznie informacja administracyjna.
	 *
	 * @param WC_Order $order Zamówienie.
	 */
	protected function order_flag_markup( WC_Order $order ) {
		if ( 'yes' === $order->get_meta( Sascom_RC_CPT::ORDER_META_HAS_REQUEST ) ) {
			echo '<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:#fcf0e1;color:#8a4b00;font-size:12px;">';
			echo esc_html__( 'Tak', 'returns-complaints-for-woocommerce' );
			echo '</span>';
		} else {
			echo '<span style="color:#aaa;">&ndash;</span>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Pomocnicze
	 * ------------------------------------------------------------------ */

	/**
	 * Link do edycji zamówienia (zgodny z HPOS i trybem klasycznym).
	 *
	 * @param int $order_id ID zamówienia.
	 * @return string
	 */
	protected function get_order_edit_link( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
			return $order->get_edit_order_url();
		}
		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}
}
