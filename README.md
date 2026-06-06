# Returns & Complaints for WooCommerce

![Returns & Complaints for WooCommerce](docs/banner.png)

## Description

Lightweight WooCommerce plugin that adds a public return, withdrawal and complaint
request form, including support for guest customers.

Customers identify their order with the order number and the e-mail address used at
checkout. The plugin verifies the order against its `billing_email`, never exposing
order data when the e-mail does not match. Each submission creates a dedicated
`sascom_rc_request` custom post type entry, sends a confirmation e-mail to the
customer and a notification to the store administrator, and links the request back to
the WooCommerce order (private order note, flag, request list and an admin-only column
on the orders list).

Key features:

- Two request types: return / withdrawal from contract and complaint / product issue.
- Product selection from the actual order line items.
- 30-day technical scope for the online form (this does not extend any statutory
  return deadline). Complaints older than 30 days are accepted and flagged for manual
  verification; returns older than 30 days are redirected to e-mail contact.
- Dedicated request statuses and an admin list under "Zwroty i reklamacje".
- HPOS (High-Performance Order Storage) compatible.
- Security: nonces, input sanitization, output escaping, server-side re-validation.

The plugin does **not** process payments, automatic refunds or courier integrations.

## Installation

1. Upload the `returns-complaints-for-woocommerce` folder to `wp-content/plugins/`
   (or install the ZIP via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin (WooCommerce must be active).
3. Create a page, e.g. `/zwroty-i-reklamacje/`, and add the shortcode below.

## Shortcode

```
[sascom_rc_returns_form]
```

Place it on any page or post to render the public return/complaint form.

## Author

Sascom - Bartosz Sudół
https://sascom.pl/

## License

Copyright (c) 2026 Sascom - Bartosz Sudół

This plugin is licensed under GPL-2.0-or-later.
Please keep the original copyright notice and author attribution.

Full license text: https://www.gnu.org/licenses/gpl-2.0.html
