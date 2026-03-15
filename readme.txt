=== TextitBiz Notifications ===
Contributors: VJ-Ranga
Tags: sms, forms, notifications, metform, elementor, contact-form-7, woocommerce
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send SMS notifications through Textit.biz when selected WordPress forms are submitted.

== Description ==

TextitBiz Notifications connects selected WordPress forms to Textit.biz SMS delivery.

Features:

* Detect active supported form plugins
* Show only active integrations in settings
* Let admins choose which forms should send SMS
* Generate simple SMS templates based on selected form fields
* Show shortcodes that match the selected form
* Send SMS alerts through Textit.biz Basic HTTP API

Supported plugins:

* MetForm
* Elementor Pro Forms
* Contact Form 7
* WooCommerce checkout

== Installation ==

1. Upload the plugin to `/wp-content/plugins/textitbiz-notifications/`
2. Activate the plugin through the WordPress Plugins screen
3. Go to `Settings -> TextitBiz Notifications`
4. Select the forms you want to monitor
5. Enter your Textit.biz credentials and admin mobile number
6. Save settings and test a submission

== Frequently Asked Questions ==

= Does it send SMS for every form? =

No. It only sends SMS for the forms you select in the settings page.

= Which Textit.biz API does it use? =

It uses the Textit.biz Basic HTTP API.

= Can I use actual field names in the message? =

Yes. The plugin shows field-based shortcodes like `{field:mf-tel}` depending on the selected form.

== Changelog ==

= 0.1.4 =
* Improved GitHub updater cache and force-check behavior
* Prefer GitHub release ZIP asset when available

= 0.1.3 =
* Verification release for GitHub auto-update testing

= 0.1.2 =
* Added GitHub release-based update checker
* Added Update URI header for safer custom plugin updates

= 0.1.1 =
* Fixed settings save issue caused by nested form in admin page
* Improved admin security and credential handling
* Added SMS logs panel and clear logs action

= 0.1 =
* Initial development version
* Added form detection and selective SMS notifications
* Added Textit.biz Basic HTTP API integration
