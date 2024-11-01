=== WooCommerce and WP eMember Integration ===
Contributors: Tips and Tricks HQ, wptipsntricks
Donate link: https://www.tipsandtricks-hq.com/wordpress-emember-easy-to-use-wordpress-membership-plugin-1706
Tags: woocommerce, login, member, members, membership, payment, e-commerce, wp members, membership payment
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 2.3
License: GPLv2 or later

An addon to integrate WooCommerce plugin with WP eMember plugin for membership payment

== Description ==

This addon allows you to accept payment for a membership via WooCommerce.

Your users will be able to use any payment gateway supported in WooCommerce to do the checkout.

The membership side of things is managed by [WP eMember plugin](https://www.tipsandtricks-hq.com/wordpress-emember-easy-to-use-wordpress-membership-plugin-1706).

After you install this addon, edit the WooCommerce product that you want to use for membership payment and specify the level ID in the "Membership Level ID" field.

View the full [usage instruction here](https://www.tipsandtricks-hq.com/wordpress-membership/woocommerce-wp-emember-integration-1013)

== Installation ==
1. Upload `emember-woocommerce-addon` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==
None

== Screenshots ==
View screenshots here:
https://www.tipsandtricks-hq.com/wordpress-emember-easy-to-use-wordpress-membership-plugin-1706

== Changelog ==

= 2.3 =
* Replaced the usage of 2 deprecated function calls to use the suggested alternatives. 

= 2.2 =
* Added a debug log entry outputting the product ID.

= 2.1 =
* Supports the auto-login functionality of woocommerce (after checkout).

= 2.0 =
* Added a mechanism to stop any processing of duplicate order notification from woocommerce.

= 1.9 =
* Updated a function name for PHP compatibility.

= 1.8 =
* Updated some calls to use a function instead of the variable.

= 1.7 =
* Added compatibility for the "disabled" option of account upgrade email.

= 1.6 =
* Updated the woocommerce order status processing hook handling for better compatibility.

= 1.5 =
* Added support for eMember's multiple membership level feature.

= 1.3 =
* WooCommerce subscription addon compatibility

= 1.2 =
* WordPress 4.1 compatibility

= 1.1 =
* First commit to wordpress.org

== Upgrade Notice ==
None
