/* global SascomRC */
( function () {
	'use strict';

	if ( typeof SascomRC === 'undefined' ) {
		return;
	}

	var form = document.getElementById( 'sascom-rc-form' );
	if ( ! form ) {
		return;
	}

	var step1        = form.querySelector( '.sascom-rc-step-1' );
	var step2        = form.querySelector( '.sascom-rc-step-2' );
	var stepDone     = form.querySelector( '.sascom-rc-step-done' );
	var lookupBtn    = document.getElementById( 'sascom-rc-lookup-btn' );
	var backBtn      = document.getElementById( 'sascom-rc-back-btn' );
	var submitBtn    = document.getElementById( 'sascom-rc-submit-btn' );
	var lookupMsg    = form.querySelector( '.sascom-rc-lookup-message' );
	var submitMsg    = form.querySelector( '.sascom-rc-submit-message' );
	var typeNotice   = form.querySelector( '.sascom-rc-type-notice' );
	var orderSummary = form.querySelector( '.sascom-rc-order-summary' );
	var productsWrap = form.querySelector( '.sascom-rc-products' );
	var productsField = form.querySelector( '.sascom-rc-products-wrap' );
	var bankField    = form.querySelector( '.sascom-rc-bank-field' );

	// Dane bieżącego zamówienia zwrócone w kroku 1.
	var orderData = null;

	/**
	 * Wyświetla komunikat w danym kontenerze.
	 */
	function showMessage( el, text, type ) {
		el.textContent = text;
		el.className = 'sascom-rc-message sascom-rc-message-' + ( type || 'error' );
		el.hidden = false;
	}

	function hideMessage( el ) {
		el.hidden = true;
		el.textContent = '';
	}

	/**
	 * Bezpieczny odczyt wartości pola po ID.
	 * Zwraca '' gdy element nie istnieje (np. opcjonalny honeypot) – bez wyjątku.
	 */
	function val( id ) {
		var el = document.getElementById( id );
		return el ? el.value : '';
	}

	/**
	 * Bezpieczne tworzenie elementu z tekstem (bez wstrzykiwania HTML).
	 */
	function createEl( tag, className, text ) {
		var el = document.createElement( tag );
		if ( className ) {
			el.className = className;
		}
		if ( typeof text !== 'undefined' ) {
			el.textContent = text;
		}
		return el;
	}

	/**
	 * Wysyłka żądania AJAX (application/x-www-form-urlencoded).
	 */
	function post( params ) {
		var body = new URLSearchParams();
		body.append( 'nonce', SascomRC.nonce );
		Object.keys( params ).forEach( function ( key ) {
			var value = params[ key ];
			if ( value && typeof value === 'object' ) {
				Object.keys( value ).forEach( function ( subKey ) {
					body.append( key + '[' + subKey + ']', value[ subKey ] );
				} );
			} else {
				body.append( key, value );
			}
		} );

		return fetch( SascomRC.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( res ) {
			return res.json();
		} );
	}

	/**
	 * Renderuje listę produktów z możliwością wyboru.
	 */
	function renderProducts( products ) {
		productsWrap.innerHTML = '';

		products.forEach( function ( product ) {
			// available_qty liczone serwerowo (ordered − już objęte aktywnymi zgłoszeniami).
			var available = typeof product.available_qty !== 'undefined' ? parseInt( product.available_qty, 10 ) : parseInt( product.qty, 10 );
			var unavailable = available <= 0;

			var rowEl = createEl( 'div', 'sascom-rc-product' + ( unavailable ? ' sascom-rc-product-unavailable' : '' ) );

			var checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.className = 'sascom-rc-product-check';
			checkbox.value = product.item_id;
			checkbox.id = 'sascom-rc-item-' + product.item_id;
			checkbox.disabled = unavailable;

			var label = createEl( 'label', 'sascom-rc-product-label', product.name + ' ' );
			label.htmlFor = checkbox.id;

			rowEl.appendChild( checkbox );
			rowEl.appendChild( label );

			if ( unavailable ) {
				// Produkt już objęty aktywnym zgłoszeniem – nie do zaznaczenia.
				var reason = product.unavailable_reason || SascomRC.i18n.alreadyRequested;
				rowEl.appendChild( createEl( 'span', 'sascom-rc-product-reason', reason ) );
				productsWrap.appendChild( rowEl );
				return;
			}

			var qty = document.createElement( 'input' );
			qty.type = 'number';
			qty.className = 'sascom-rc-product-qty';
			qty.min = '1';
			qty.max = String( available );
			qty.value = '1';
			qty.setAttribute( 'data-item', product.item_id );
			qty.disabled = true;

			checkbox.addEventListener( 'change', function () {
				qty.disabled = ! checkbox.checked;
			} );

			var qtyHint = createEl( 'span', 'sascom-rc-product-hint', ' (max ' + available + ')' );

			rowEl.appendChild( qty );
			rowEl.appendChild( qtyHint );
			productsWrap.appendChild( rowEl );
		} );
	}

	/**
	 * Aktualizuje komunikaty i widoczność pól zależnie od typu zgłoszenia.
	 */
	function onTypeChange() {
		var selected = form.querySelector( 'input[name="type"]:checked' );
		hideMessage( typeNotice );
		hideMessage( submitMsg );

		if ( ! selected || ! orderData ) {
			bankField.hidden = true;
			return;
		}

		var isReturn = selected.value === SascomRC.typeReturn;
		bankField.hidden = ! isReturn;

		if ( orderData.within_limit ) {
			submitBtn.disabled = false;
			return;
		}

		// Zamówienie starsze niż 30 dni.
		if ( isReturn ) {
			showMessage( typeNotice, orderData.notices.return_over_limit, 'warning' );
			// Nie blokujemy „brutalnie” – informujemy i wyłączamy wysyłkę online.
			submitBtn.disabled = true;
		} else {
			showMessage( typeNotice, orderData.notices.complaint_over_limit, 'warning' );
			submitBtn.disabled = false;
		}
	}

	/**
	 * Zbiera zaznaczone produkty.
	 */
	function collectItems() {
		var items = {};
		var checks = productsWrap.querySelectorAll( '.sascom-rc-product-check:checked' );
		checks.forEach( function ( check ) {
			var qtyInput = productsWrap.querySelector( '.sascom-rc-product-qty[data-item="' + check.value + '"]' );
			var qty = qtyInput ? parseInt( qtyInput.value, 10 ) : 1;
			items[ check.value ] = qty > 0 ? qty : 1;
		} );
		return items;
	}

	// --- Krok 1: wyszukanie zamówienia ---
	lookupBtn.addEventListener( 'click', function () {
		hideMessage( lookupMsg );

		var orderNumber = val( 'sascom-rc-order-number' ).trim();
		var email = val( 'sascom-rc-email' ).trim();

		if ( ! orderNumber || ! email ) {
			showMessage( lookupMsg, SascomRC.i18n.genericError );
			return;
		}

		lookupBtn.disabled = true;

		post( {
			action: 'sascom_rc_lookup_order',
			order_number: orderNumber,
			email: email
		} ).then( function ( response ) {
			lookupBtn.disabled = false;

			if ( ! response || ! response.success ) {
				showMessage( lookupMsg, response && response.data ? response.data.message : SascomRC.i18n.genericError );
				return;
			}

			orderData = response.data;

			// Podsumowanie zamówienia.
			orderSummary.innerHTML = '';
			orderSummary.appendChild( createEl( 'p', null, orderData.customer_name ) );
			orderSummary.appendChild( createEl( 'p', 'sascom-rc-order-meta',
				'#' + orderData.order_number + ' • ' + orderData.order_date ) );

			renderProducts( orderData.products );

			step1.hidden = true;
			step2.hidden = false;
		} ).catch( function () {
			lookupBtn.disabled = false;
			showMessage( lookupMsg, SascomRC.i18n.genericError );
		} );
	} );

	// --- Powrót do kroku 1 ---
	backBtn.addEventListener( 'click', function () {
		step2.hidden = true;
		step1.hidden = false;
	} );

	// --- Zmiana typu zgłoszenia ---
	form.querySelectorAll( 'input[name="type"]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', onTypeChange );
	} );

	// --- Krok 2: wysłanie zgłoszenia ---
	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		hideMessage( submitMsg );

		var selectedType = form.querySelector( 'input[name="type"]:checked' );
		if ( ! selectedType ) {
			showMessage( submitMsg, SascomRC.i18n.selectType );
			return;
		}

		var items = collectItems();
		if ( Object.keys( items ).length === 0 ) {
			showMessage( submitMsg, SascomRC.i18n.selectProduct );
			return;
		}

		submitBtn.disabled = true;

		post( {
			action: 'sascom_rc_submit_return',
			order_number: val( 'sascom-rc-order-number' ).trim(),
			email: val( 'sascom-rc-email' ).trim(),
			type: selectedType.value,
			customer_message: val( 'sascom-rc-customer-message' ),
			bank_account: val( 'sascom-rc-bank-account' ),
			sascom_rc_website: val( 'sascom-rc-website' ),
			items: items
		} ).then( function ( response ) {
			if ( ! response || ! response.success ) {
				submitBtn.disabled = false;
				showMessage( submitMsg, response && response.data ? response.data.message : SascomRC.i18n.genericError );
				return;
			}

			// Ekran potwierdzenia.
			step2.hidden = true;
			stepDone.hidden = false;
			var successEl = stepDone.querySelector( '.sascom-rc-success' );
			successEl.className = 'sascom-rc-message sascom-rc-message-success';
			successEl.textContent = response.data.message;
			successEl.hidden = false;
		} ).catch( function () {
			submitBtn.disabled = false;
			showMessage( submitMsg, SascomRC.i18n.genericError );
		} );
	} );

	// Referencja zachowana dla potencjalnych rozszerzeń.
	void productsField;
}() );
