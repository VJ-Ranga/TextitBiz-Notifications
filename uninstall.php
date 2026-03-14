<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'textitbiz_notifications_settings' );
delete_option( 'textitbiz_notifications_logs' );
