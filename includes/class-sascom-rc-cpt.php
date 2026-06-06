<?php
/**
 * Rejestracja Custom Post Type oraz definicja statusów i typów zgłoszeń.
 *
 * @package Sascom_RC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa odpowiedzialna za typ wpisu sascom_rc_request.
 */
class Sascom_RC_CPT {

	const POST_TYPE = 'sascom_rc_request'; // 17 znaków (limit WordPress: 20).

	/**
	 * Klucze meta zgłoszenia.
	 */
	const META_ORDER_ID      = '_sascom_rc_order_id';
	const META_ORDER_NUMBER  = '_sascom_rc_order_number';
	const META_EMAIL         = '_sascom_rc_email';
	const META_CUSTOMER_NAME = '_sascom_rc_customer_name';
	const META_PRODUCTS      = '_sascom_rc_products';
	const META_TYPE          = '_sascom_rc_type';
	const META_REASON        = '_sascom_rc_reason';
	const META_MESSAGE       = '_sascom_rc_message';
	const META_BANK_ACCOUNT  = '_sascom_rc_bank_account';
	const META_STATUS        = '_sascom_rc_status';

	/**
	 * Klucze meta zapisywane po stronie zamówienia WooCommerce.
	 */
	const ORDER_META_HAS_REQUEST = '_sascom_rc_has_request';
	const ORDER_META_REQUEST_IDS = '_sascom_rc_request_ids';

	/**
	 * Typy zgłoszeń.
	 */
	const TYPE_RETURN    = 'return';
	const TYPE_COMPLAINT = 'complaint';

	/**
	 * Maksymalna liczba aktywnych zgłoszeń przypadających na jedno zamówienie.
	 */
	const MAX_ACTIVE_PER_ORDER = 3;

	/**
	 * Rejestracja hooków.
	 */
	public function hooks() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Rejestracja typu wpisu.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Zwroty i reklamacje', 'returns-complaints-for-woocommerce' ),
			'singular_name'      => __( 'Zgłoszenie', 'returns-complaints-for-woocommerce' ),
			'menu_name'          => __( 'Zwroty i reklamacje', 'returns-complaints-for-woocommerce' ),
			'all_items'          => __( 'Wszystkie zgłoszenia', 'returns-complaints-for-woocommerce' ),
			'view_item'          => __( 'Zobacz zgłoszenie', 'returns-complaints-for-woocommerce' ),
			'edit_item'          => __( 'Zgłoszenie', 'returns-complaints-for-woocommerce' ),
			'search_items'       => __( 'Szukaj zgłoszeń', 'returns-complaints-for-woocommerce' ),
			'not_found'          => __( 'Brak zgłoszeń', 'returns-complaints-for-woocommerce' ),
			'not_found_in_trash' => __( 'Brak zgłoszeń w koszu', 'returns-complaints-for-woocommerce' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,        // Brak publicznego widoku – ochrona danych.
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-update-alt',
			'menu_position'       => 56,
			'capability_type'     => 'post',
			'capabilities'        => array(
				'create_posts' => 'do_not_allow', // Zgłoszenia tworzy wyłącznie formularz.
			),
			'map_meta_cap'        => true,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Lista dostępnych statusów zgłoszenia (slug => etykieta).
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'new'                           => __( 'Nowe', 'returns-complaints-for-woocommerce' ),
			'manual_verification'           => __( 'Weryfikacja ręczna', 'returns-complaints-for-woocommerce' ),
			'waiting_for_customer_shipment' => __( 'Oczekuje na wysyłkę od klienta', 'returns-complaints-for-woocommerce' ),
			'received_by_store'             => __( 'Odebrane przez sklep', 'returns-complaints-for-woocommerce' ),
			'refund_pending'                => __( 'Zwrot środków w toku', 'returns-complaints-for-woocommerce' ),
			'refund_completed'              => __( 'Zwrot środków zrealizowany', 'returns-complaints-for-woocommerce' ),
			'closed'                        => __( 'Zamknięte', 'returns-complaints-for-woocommerce' ),
		);
	}

	/**
	 * Statusy uznawane za „aktywne" (zgłoszenie w toku).
	 *
	 * Statusy zamknięte: refund_completed, closed.
	 *
	 * @return array
	 */
	public static function get_active_statuses() {
		return array(
			'new',
			'manual_verification',
			'waiting_for_customer_shipment',
			'received_by_store',
			'refund_pending',
		);
	}

	/**
	 * Etykieta dla pojedynczego statusu.
	 *
	 * @param string $status Slug statusu.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$statuses = self::get_statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	}

	/**
	 * Lista typów zgłoszeń (slug => etykieta).
	 *
	 * @return array
	 */
	public static function get_types() {
		return array(
			self::TYPE_RETURN    => __( 'Zwrot / odstąpienie od umowy', 'returns-complaints-for-woocommerce' ),
			self::TYPE_COMPLAINT => __( 'Reklamacja / problem z produktem', 'returns-complaints-for-woocommerce' ),
		);
	}

	/**
	 * Etykieta dla typu zgłoszenia.
	 *
	 * @param string $type Slug typu.
	 * @return string
	 */
	public static function get_type_label( $type ) {
		$types = self::get_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : $type;
	}
}
