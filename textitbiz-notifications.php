<?php
/**
 * Plugin Name: TextitBiz SMS
 * Plugin URI: https://github.com/VJ-Ranga/TextitBiz-SMS
 * Description: Send SMS alerts through Textit.biz for selected WordPress form submissions.
 * Version: 0.2.6
 * Author: VJ Ranga
 * License: GPL-2.0-or-later
 * Text Domain: textitbiz-notifications
 * Update URI: https://github.com/VJ-Ranga/TextitBiz-SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TEXTITBIZ_NOTIFICATIONS_VERSION', '0.2.6' );
define( 'TEXTITBIZ_NOTIFICATIONS_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-textitbiz-detector.php';
require_once __DIR__ . '/includes/class-textitbiz-api.php';
require_once __DIR__ . '/includes/class-textitbiz-admin.php';
require_once __DIR__ . '/includes/class-textitbiz-integration-manager.php';
require_once __DIR__ . '/includes/class-textitbiz-notifications.php';
require_once __DIR__ . '/includes/class-textitbiz-github-updater.php';

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'textitbiz_notifications_action_links' );

function textitbiz_notifications_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=textitbiz-notifications' ) ) . '">Settings</a>';

	array_unshift( $links, $settings_link );

	return $links;
}

TextitBiz_Notifications::instance();

new TextitBiz_GitHub_Updater(
	TEXTITBIZ_NOTIFICATIONS_FILE,
	TEXTITBIZ_NOTIFICATIONS_VERSION,
	'VJ-Ranga',
	'TextitBiz-SMS'
);
