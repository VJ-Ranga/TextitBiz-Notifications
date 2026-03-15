<?php
/**
 * Plugin Name: TextitBiz Notifications
 * Plugin URI: https://github.com/VJ-Ranga/TextitBiz-Notifications
 * Description: Send SMS alerts through Textit.biz for selected WordPress form submissions.
 * Version: 0.1.3
 * Author: VJ Ranga
 * License: GPL-2.0-or-later
 * Text Domain: textitbiz-notifications
 * Update URI: https://github.com/VJ-Ranga/TextitBiz-Notifications
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TEXTITBIZ_NOTIFICATIONS_VERSION', '0.1.3' );
define( 'TEXTITBIZ_NOTIFICATIONS_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-textitbiz-detector.php';
require_once __DIR__ . '/includes/class-textitbiz-api.php';
require_once __DIR__ . '/includes/class-textitbiz-admin.php';
require_once __DIR__ . '/includes/class-textitbiz-integration-manager.php';
require_once __DIR__ . '/includes/class-textitbiz-notifications.php';
require_once __DIR__ . '/includes/class-textitbiz-github-updater.php';

TextitBiz_Notifications::instance();

new TextitBiz_GitHub_Updater(
	TEXTITBIZ_NOTIFICATIONS_FILE,
	TEXTITBIZ_NOTIFICATIONS_VERSION,
	'VJ-Ranga',
	'TextitBiz-Notifications'
);
