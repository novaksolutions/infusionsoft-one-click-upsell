=== Infusionsoft One-click Upsell ===
Contributors: zulugrid, joeynovak
Tags: infusionsoft, upsell, one-click, javascript
Author URI: http://novaksolutions.com/
Plugin URI: http://novaksolutions.com/wordpress-plugins/infusionsoft-one-click-upsell/
Requires at least: 2.7
Tested up to: 3.8.1
Stable tag: 1.1.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easily upsell Infusionsoft® customers from within WordPress using shortcodes.

== Description ==

The One-click Upsell Plugin makes it easy to add an upsell button to your shopping cart and order form Thank You pages.

Use the upsell shortcode on any Thank You page or post. Make sure you select the option in Infusionsoft to pass the contact's information to the Thank You page.

Once the customer clicks the upsell button, One-click Upsell will charge the customer's last used credit card and place the order in Infusionsoft.

== Frequently Asked Questions ==

= Do I need an Infusionsoft account? =

Yes, you will need to provide your Infusionsoft API key.

== Screenshots ==

1. The upsell button is highly configurable. The settings page guides you through the configuration process.
2. Detailed usage instructions are provided by the plugin.
3. Example shortcodes -- using your REAL product data -- are provided by the plugin.
4. Using the shortcode in a post or page is easy.
5. The upsell shows up as a button on your post or page.

== Changelog ==

= 1.1.7 =
* Replaced a few <? with <?php

= 1.1.6 =
* Add ability to set a CSS ID on your button.
* Mark certain fields as required.
* Refuse to show button if required fields are not set.
* Add link to more plugins by Novak Solutions.
* Add link requesting ratings at WP.org.
* Add screenshots.

= 1.1.5 =
* Suggests merchant account ID based on recent usage within Infusionsoft

= 1.1.4 =
* Fix missing parameter for Infusionsoft_InvoiceService::addOrderItem call

= 1.1.3 =
* Only pull credit card ID (grabbing more can cause failures)
* Use correct date format when creating blank order

= 1.1.2 =
* Bug fix

= 1.1.1 =
* Removed error reporting code.

= 1.1.0 =
* Removed the Novak Solutions SDK.
* Plugin is now dependent on the Infusionsoft SDK plugin.
* Fix undefined index errors.
* Removed call to function that didn't exist.
* Removed function that wasn't used.
* Tested up to 3.8.1

= 1.0.0 =
* First release on WordPress.org

== Upgrade Notice ==

= 1.1.0 =
The SDK has been moved to its own plugin. This fixes blank screen issues that happen if you have more than one plugin installed that requires the SDK. Install the Infusionsoft SDK plugin before upgrading!
