=== NLB Payment Gateway For Woocommerce ===

- Contributors: m1tk00, webpigment
- Tags: woocommerce, payment gateway, gateway, manual payment
- Requires at least: 3.8
- Tested up to: 5.3.2
- Requires WooCommerce at least: 3.2
- Tested WooCommerce up to: 3.8.2
- Stable Tag: 2.0.1
- License: GPLv3
- License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

> **Requires: WooCommerce 2.1+**

This plugin allows your store to make payments via NLB payment service.

If the transaction is successful the order status will be changed to "processing". If the payment charge failed the order status will be changed to "cancelled". If something is wrong with the connection between your server and the NLB server the order status will be changed to "on-hold". After successful transaction the customer is redirected to the default WP thank you page.

== Installation ==

1. Be sure you're running WooCommerce 2.1+ in your shop.
2. You can: (1) upload the entire `wc-gateway-tebank` folder to the `/wp-content/plugins/` directory, (2) upload the .zip file with the plugin under **Plugins &gt; Add New &gt; Upload**
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **WooCommerce &gt; Settings &gt; Checkout** and select "NLB Payment Gateway" to configure.
5. Make sure you fill in all NLB required fields.
6. Upload the resourse.cng file from the settings section. The requested information can be found under the NLB console.
7. Make sure you add the terminal alias.
8. Make sure you select the currency.
9. Make sure you select the language.
10. Select your needed transaction type.
11. Select your Error URL. This is the page that the customers will be redirected to after unsuccessful transaction.

== Frequently Asked Questions ==

**What is the text domain for translations?**
The text domain is `nlb-payment-gateway-for-woocommerce`.

**What is the Instructions field?**
This field adds extra content to the thank you page, and the successful transaction tank you page.

== Changelog ==
= 2019.07.04 - version 2.0 =
* Bug fixes
* Add new gateway for BankArt

= 2019.07.04 - version 1.3.1 =
* Bug fixes

= 2017.02.23 - version 1.3 =
* Now supporting woocommerce 3.2+

= 2017.02.23 - version 1.2 =
* Add filter for order price 'nlb_payment_price', for better of modification of the order price if you need to convert currency
* Add option for automatically capturing the authorized transactions.

= 2017.02.08 - version 1.1.2 =
* Add payment complete action

= 2017.02.08 - version 1.1.0 =
* Fix plugin domain
* Small fixes

= 2017.01.24 - version 1.0.0 =
* Initial Release
