=== Shipfunk WooCommerce Shipping ===
Contributors: shipfunk
Donate link: http://www.shipfunk.com/
Tags: woocommerce shipment, toimitustavat, toimitus, shipping rates, shipping, shipping calculator, Shipping extension, shipping method, logistics, delivery, checkout, postage, shipfunk, Posti, Matkahuolto, shipment, rates, widget, prinetti
Requires at least: 3.0.1
Tested up to: 6.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Customers get shipping options of various carriers based on shopping cart content. You get shipping labels and tracking codes automatically.

== Description ==

Shipfunk Logistics Platform offers you a flexible way to differentiate from your competitors. With Shipfunk’s platform you can offer your customers multiple shipping options (i.a. Posti, Matkahuolto, DB Schenker, GLS, DHL and UPS) and extensive amount of pick-up -sites, as well as all-in-one tracking site and returns portal. All shipping options are fully adjustable to achieve better customer experience. We generate package labels and EDI -messages, so the system is scalable to high volume.

You can generate labels easily from the plugin or on our Extranet (no need to use e.g. Posti SmartShip/Prinetti).

You do not need to pay any fixed service fees, starting fees or monthly fees. You pay only per use.

Sign up free at [Shipfunk](https://shipfunkservices.com/extranet/register/eshop).

More information at [www.shipfunk.com](https://www.shipfunk.com)

== Installation ==

1. [Sign up to Shipfunk](https://shipfunkservices.com/extranet/register) and follow the setup wizard.
2. Install and activate the Shipfunk plugin
3. Go to: WooCommerce > Settings > Shipping and enter your account details.

Full installation and configuration guide can be found at [http://support.shipfunk.com/tuetut-verkkokauppa-alustat/woocommerce-shipfunk](http://support.shipfunk.com/tuetut-verkkokauppa-alustat/woocommerce-shipfunk)

== Frequently Asked Questions ==

You'll find the FAQ on http://support.shipfunk.com

== Screenshots ==

1. Admin settings
2. Order review on the checkout page
3. Package cards on the order details page

== Changelog ==
= 1.3.5 =
- Improve compatibility with payment plugins
- Fix missing API call on WC 4.0.0

= 1.3.4 =
- Fix product quantity on API calls

= 1.3.3 =
- Remember chosen shipping method

= 1.3.2 =
- Include product SKU code in API calls

= 1.3.1 =
- Tax settings are taken into consideration on API calls which includes product prices

= 1.3.0 =
- Shipments are created using a new Shipfunk API, which makes the process more robust
- Free delivery coupon support

= 1.2.2 =
- Rollback order process data caching changes
- Custom delivery option name support

= 1.2.1 =
- Changed order process data caching to mitigate problems with orders that do not get sent to Shipfunk

= 1.2.0 =
- Show delivery time estimate on shipping label as a setting

= 1.1.9 =
- Custom meta box action to manually set order visible on Shipfunk.

= 1.1.8 =
- Pickup info on order details page fixed.

= 1.1.7 =
- Compatibility with Klarna Checkout plugin updated.

= 1.1.6 =
- Include tracking code in order emails. Check WooCommerce settings in Shipfunk Extranet to enable it.

= 1.1.5 =
- Order status API action hook changed. Failed orders do not get sent to Shipfunk.
- Minor translation tweaks and couple notes added.

= 1.1.4 =
- Tag support fixed when product is product variation

= 1.1.3 =
- Support for shipping tags added

= 1.1.2 =
- Template error handling

= 1.1.1 =
- Bug fixes

= 1.1.0 =
- Shipfunk API 1.2 implementation
- API authentication with API keys
- Default product dimensions
- Default warehouse setting removed

= 1.0.7 =
- Properties accesses updated

= 1.0.6 =
- Fixed unnecessary api call when shipping was not Shipfunk's

= 1.0.5 =
- Fixed bug related to deleting parcels with empty fields

= 1.0.4 =
- Fixed text domain in cart-shipping -template

= 1.0.3 =
- Changed plugin name on the plugin header
- Fixed incorrect language filename

= 1.0.2 =
- Fixed reload pickups functionality
- Fixed cart-shipping -template overriding
- Docs url updated
- Text domain added to plugin header

= 1.0.1 =
- Fixed several variables encoding to valid XML

= 1.0.0 =
- Initial revision

== Upgrade Notice ==

= 1.1.1 =
This new version communicates with our new API and it has a different authentication system - you need to input your new API key(s) to continue using the plugin! Please, check your new API keys from Shipfunk Extranet -> Account -> API keys before you update. After updating the plugin save the API keys to the plugin settings (WooCommerce -> Settings -> Shipping -> Shipfunk). We have also a new setting: default product dimensions, which you can configure. The setting is used when a product does not have dimensions set.
