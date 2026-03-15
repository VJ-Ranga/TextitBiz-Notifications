<?php
/**
 * Plugin Name: TextitBiz Notifications
 * Plugin URI: https://github.com/VJ-Ranga/TextitBiz-Notifications
 * Description: Send SMS alerts through Textit.biz for selected WordPress form submissions.
 * Version: 0.1
 * Author: VJ Ranga
 * License: GPL-2.0-or-later
 * Text Domain: textitbiz-notifications
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-textitbiz-detector.php';
require_once __DIR__ . '/includes/class-textitbiz-api.php';
require_once __DIR__ . '/includes/class-textitbiz-admin.php';
require_once __DIR__ . '/includes/class-textitbiz-integration-manager.php';
require_once __DIR__ . '/includes/class-textitbiz-notifications.php';

TextitBiz_Notifications::instance();
