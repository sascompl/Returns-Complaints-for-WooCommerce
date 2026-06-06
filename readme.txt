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

Returns & Complaints for WooCommerce adds a public form that lets customers submit a return (withdrawal from contract) or a complaint about a product. The form works for all WooCommerce customers, including those who ordered as guests without an account.

Features:

* Embeddable form via the `[sascom_rc_returns_form]` shortcode.
* Order verification by order number and the e-mail address used at checkout (billing e-mail).
* Order data is never exposed when the e-mail address does not match.
* Two request types: return / withdrawal from contract, and complaint / product issue.
* Selection of specific products from the order.
* Technical scope of the online form: the last 30 days. This does not extend any statutory return deadline.
* Complaints older than 30 days are accepted and flagged for manual verification.
* Returns older than 30 days show a message directing the customer to contact the store by e-mail, without a hard block.
* Custom post type `sascom_return_request` with dedicated statuses.
* Confirmation e-mail to the customer and a notification e-mail to the store administrator.
* Order linking: private order note, a flag, a request list and an admin-only column on the orders list.
* Compatible with WooCommerce HPOS (High-Performance Order Storage).

The plugin does not process payments, automatic refunds or courier integrations.

== Installation ==

1. Copy the `returns-complaints-for-woocommerce` folder to `wp-content/plugins/`.
2. Activate the plugin in the WordPress admin (WooCommerce must be active).
3. Create a page (for example `/returns-and-complaints/`) and add the `[sascom_rc_returns_form]` shortcode.

== Request statuses ==

* new
* manual_verification
* waiting_for_customer_shipment
* received_by_store
* refund_pending
* refund_completed
* closed

== Changelog ==

= 1.0.0 =
* Initial release.
