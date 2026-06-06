<?php
/**
 * Prosta strona ustawień: dane do zwrotów (adres, paczkomat, e-mail, instrukcje).
 *
 * Wartości są opcjonalne i domyślnie puste. Filtry sascom_rc_return_* nadal
 * działają i nadpisują wartość zapisaną w ustawieniach.
 *
 * @package Sascom_RC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa strony ustawień.
 */
class Sascom_RC_Settings {

	const OPTION       = 'sascom_rc_settings';
	const GROUP        = 'sascom_rc_settings_group';
	const PAGE         = 'sascom_rc_settings';
	const CAPABILITY   = 'manage_woocommerce';

	/**
	 * Rejestracja hooków (tylko panel administracyjny).
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Wartości ustawień scalone z domyślnymi (puste).
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			// Zwroty.
			'shipping_address'          => '',
			'parcel_locker'             => '',
			'contact_email'             => '',
			'instructions'              => '',
			// Reklamacje.
			'complaint_same_as_return'  => '1', // Domyślnie ten sam adres co do zwrotów.
			'complaint_address'         => '',
			'complaint_parcel_locker'   => '',
			'complaint_contact_email'   => '',
			'complaint_instructions'    => '',
		);

		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Dane odesłania dla ZWROTU (z ustawień).
	 *
	 * @return array address, parcel, contact, instructions
	 */
	public static function get_return_data() {
		$s = self::get_settings();
		return array(
			'address'      => $s['shipping_address'],
			'parcel'       => $s['parcel_locker'],
			'contact'      => $s['contact_email'],
			'instructions' => $s['instructions'],
		);
	}

	/**
	 * Dane odesłania dla REKLAMACJI (z ustawień).
	 *
	 * Adres/paczkomat/e-mail: takie jak do zwrotów, gdy zaznaczono „ten sam adres";
	 * w przeciwnym razie osobne pola reklamacji. Instrukcje zawsze osobne.
	 *
	 * @return array address, parcel, contact, instructions
	 */
	public static function get_complaint_data() {
		$s    = self::get_settings();
		$same = ! empty( $s['complaint_same_as_return'] );

		return array(
			'address'      => $same ? $s['shipping_address'] : $s['complaint_address'],
			'parcel'       => $same ? $s['parcel_locker'] : $s['complaint_parcel_locker'],
			'contact'      => $same ? $s['contact_email'] : $s['complaint_contact_email'],
			'instructions' => $s['complaint_instructions'],
		);
	}

	/**
	 * Podstrona w menu „Zwroty i reklamacje".
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Sascom_RC_CPT::POST_TYPE,
			__( 'Ustawienia zwrotów', 'returns-complaints-for-woocommerce' ),
			__( 'Ustawienia', 'returns-complaints-for-woocommerce' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Rejestracja ustawienia, sekcji i pól.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'sascom_rc_return_section',
			__( 'Dane do zwrotów', 'returns-complaints-for-woocommerce' ),
			array( $this, 'section_intro' ),
			self::PAGE
		);

		add_settings_field(
			'shipping_address',
			__( 'Adres zwrotu (magazyn / biuro)', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_shipping_address' ),
			self::PAGE,
			'sascom_rc_return_section'
		);

		add_settings_field(
			'parcel_locker',
			__( 'Paczkomat', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_parcel_locker' ),
			self::PAGE,
			'sascom_rc_return_section'
		);

		add_settings_field(
			'contact_email',
			__( 'E-mail kontaktowy', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_contact_email' ),
			self::PAGE,
			'sascom_rc_return_section'
		);

		add_settings_field(
			'instructions',
			__( 'Dodatkowe instrukcje (zwrot)', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_instructions' ),
			self::PAGE,
			'sascom_rc_return_section'
		);

		// --- Sekcja: dane do reklamacji ---
		add_settings_section(
			'sascom_rc_complaint_section',
			__( 'Dane do reklamacji', 'returns-complaints-for-woocommerce' ),
			array( $this, 'complaint_section_intro' ),
			self::PAGE
		);

		add_settings_field(
			'complaint_same_as_return',
			__( 'Adres reklamacji', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_complaint_same' ),
			self::PAGE,
			'sascom_rc_complaint_section'
		);

		add_settings_field(
			'complaint_address',
			__( 'Adres reklamacji (gdy inny niż zwrot)', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_complaint_address' ),
			self::PAGE,
			'sascom_rc_complaint_section'
		);

		add_settings_field(
			'complaint_parcel_locker',
			__( 'Paczkomat (reklamacja)', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_complaint_parcel_locker' ),
			self::PAGE,
			'sascom_rc_complaint_section'
		);

		add_settings_field(
			'complaint_contact_email',
			__( 'E-mail kontaktowy (reklamacja)', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_complaint_contact_email' ),
			self::PAGE,
			'sascom_rc_complaint_section'
		);

		add_settings_field(
			'complaint_instructions',
			__( 'Dodatkowe instrukcje (reklamacja)', 'returns-complaints-for-woocommerce' ),
			array( $this, 'field_complaint_instructions' ),
			self::PAGE,
			'sascom_rc_complaint_section'
		);
	}

	/**
	 * Sanityzacja zapisywanych wartości.
	 *
	 * @param array $input Surowe dane formularza.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'shipping_address'         => isset( $input['shipping_address'] ) ? sanitize_textarea_field( $input['shipping_address'] ) : '',
			'parcel_locker'            => isset( $input['parcel_locker'] ) ? sanitize_text_field( $input['parcel_locker'] ) : '',
			'contact_email'            => isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '',
			'instructions'             => isset( $input['instructions'] ) ? sanitize_textarea_field( $input['instructions'] ) : '',
			'complaint_same_as_return' => empty( $input['complaint_same_as_return'] ) ? '' : '1',
			'complaint_address'        => isset( $input['complaint_address'] ) ? sanitize_textarea_field( $input['complaint_address'] ) : '',
			'complaint_parcel_locker'  => isset( $input['complaint_parcel_locker'] ) ? sanitize_text_field( $input['complaint_parcel_locker'] ) : '',
			'complaint_contact_email'  => isset( $input['complaint_contact_email'] ) ? sanitize_email( $input['complaint_contact_email'] ) : '',
			'complaint_instructions'   => isset( $input['complaint_instructions'] ) ? sanitize_textarea_field( $input['complaint_instructions'] ) : '',
		);
	}

	/**
	 * Wstęp sekcji zwrotów.
	 */
	public function section_intro() {
		echo '<p>' . esc_html__( 'Te dane pojawiają się w e-mailu do klienta tylko dla zgłoszeń typu „Zwrot / odstąpienie od umowy". Pola puste są pomijane.', 'returns-complaints-for-woocommerce' ) . '</p>';
	}

	/**
	 * Wstęp sekcji reklamacji.
	 */
	public function complaint_section_intro() {
		echo '<p>' . esc_html__( 'Te dane pojawiają się w e-mailu do klienta tylko dla zgłoszeń typu „Reklamacja / problem z produktem". Zaznacz „ten sam adres co do zwrotów", aby nie powielać danych. Pola puste są pomijane.', 'returns-complaints-for-woocommerce' ) . '</p>';
	}

	/**
	 * Pole: adres zwrotu.
	 */
	public function field_shipping_address() {
		$value = self::get_settings()['shipping_address'];
		printf(
			'<textarea name="%1$s[shipping_address]" rows="4" class="large-text">%2$s</textarea>',
			esc_attr( self::OPTION ),
			esc_textarea( $value )
		);
	}

	/**
	 * Pole: paczkomat.
	 */
	public function field_parcel_locker() {
		$value = self::get_settings()['parcel_locker'];
		printf(
			'<input type="text" name="%1$s[parcel_locker]" value="%2$s" class="regular-text">',
			esc_attr( self::OPTION ),
			esc_attr( $value )
		);
	}

	/**
	 * Pole: e-mail kontaktowy.
	 */
	public function field_contact_email() {
		$value = self::get_settings()['contact_email'];
		printf(
			'<input type="email" name="%1$s[contact_email]" value="%2$s" class="regular-text">',
			esc_attr( self::OPTION ),
			esc_attr( $value )
		);
	}

	/**
	 * Pole: dodatkowe instrukcje.
	 */
	public function field_instructions() {
		$value = self::get_settings()['instructions'];
		printf(
			'<textarea name="%1$s[instructions]" rows="3" class="large-text">%2$s</textarea>',
			esc_attr( self::OPTION ),
			esc_textarea( $value )
		);
	}

	/**
	 * Pole: reklamacja – ten sam adres co do zwrotów (checkbox).
	 */
	public function field_complaint_same() {
		$checked = ! empty( self::get_settings()['complaint_same_as_return'] );
		printf(
			'<label><input type="checkbox" name="%1$s[complaint_same_as_return]" value="1" %2$s> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( $checked, true, false ),
			esc_html__( 'Użyj tego samego adresu / paczkomatu / e-maila co do zwrotów', 'returns-complaints-for-woocommerce' )
		);
		echo '<p class="description">' . esc_html__( 'Gdy zaznaczone, pola poniżej są ignorowane (oprócz instrukcji reklamacji).', 'returns-complaints-for-woocommerce' ) . '</p>';
	}

	/**
	 * Pole: adres reklamacji.
	 */
	public function field_complaint_address() {
		$value = self::get_settings()['complaint_address'];
		printf(
			'<textarea name="%1$s[complaint_address]" rows="4" class="large-text">%2$s</textarea>',
			esc_attr( self::OPTION ),
			esc_textarea( $value )
		);
	}

	/**
	 * Pole: paczkomat reklamacji.
	 */
	public function field_complaint_parcel_locker() {
		$value = self::get_settings()['complaint_parcel_locker'];
		printf(
			'<input type="text" name="%1$s[complaint_parcel_locker]" value="%2$s" class="regular-text">',
			esc_attr( self::OPTION ),
			esc_attr( $value )
		);
	}

	/**
	 * Pole: e-mail kontaktowy reklamacji.
	 */
	public function field_complaint_contact_email() {
		$value = self::get_settings()['complaint_contact_email'];
		printf(
			'<input type="email" name="%1$s[complaint_contact_email]" value="%2$s" class="regular-text">',
			esc_attr( self::OPTION ),
			esc_attr( $value )
		);
	}

	/**
	 * Pole: dodatkowe instrukcje reklamacji.
	 */
	public function field_complaint_instructions() {
		$value = self::get_settings()['complaint_instructions'];
		printf(
			'<textarea name="%1$s[complaint_instructions]" rows="3" class="large-text">%2$s</textarea>',
			esc_attr( self::OPTION ),
			esc_textarea( $value )
		);
	}

	/**
	 * Render strony ustawień.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ustawienia zwrotów', 'returns-complaints-for-woocommerce' ); ?></h1>

			<div class="notice notice-info inline" style="margin:1em 0;padding:.8em 1em;">
				<p style="margin:.2em 0;">
					<strong><?php esc_html_e( 'Shortcode formularza:', 'returns-complaints-for-woocommerce' ); ?></strong>
					<code>[sascom_rc_returns_form]</code>
				</p>
				<p class="description" style="margin:.2em 0;">
					<?php esc_html_e( 'Wstaw ten shortcode na dowolnej stronie lub wpisie (np. „Zwroty i reklamacje”), aby wyświetlić publiczny formularz zgłoszeń.', 'returns-complaints-for-woocommerce' ); ?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
