=== TextitBiz SMS ===
Contributors: VJ-Ranga
Tags: sms, forms, notifications, metform, elementor, contact-form-7, woocommerce
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send SMS notifications through Textit.biz when selected WordPress forms are submitted.

== Description ==

TextitBiz SMS connects selected WordPress forms to Textit.biz SMS delivery.

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

1. Upload the plugin to `/wp-content/plugins/textitbiz-sms/`
2. Activate the plugin through the WordPress Plugins screen
3. Go to `Settings -> TextitBiz SMS`
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

= 0.2.6 =
* Cleaner SMS logs UI with collapsible details
* Hide noisy info logs and keep important statuses visible

= 0.2.5 =
* Fixed Elementor form submission fatal error by replacing wp_url_to_postid fallback
* Improved package title lookup from submitted page URL

= 0.2.3 =
* Disabled submission cooldown for reliable live form testing
* Keeps dynamic package fallback from current page title

= 0.2.2 =
* Added fallback package detection from current page title for Elementor dynamic hidden fields

= 0.2.1 =
* Improve Elementor field capture by merging sent_data
* Auto-append Subject in SMS when available and template omits it

= 0.2 =
* Release version update to v0.2
* Includes Elementor Pro submission fixes, test SMS panel, and settings visibility improvements

= 0.1.13 =
* Renamed plugin display name to TextitBiz SMS

= 0.1.11 =
* Restored stable settings visibility and Settings action link
* Improved Elementor Pro submission capture and monitoring match
* Added fallback integration safeguards to avoid silent disable states

= 0.1.6 =
* Improved Textit.biz API compatibility with POST/GET fallback
* Added better API response handling and error reporting

= 0.1.5 =
* Added Plugins page quick action link: Settings

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
