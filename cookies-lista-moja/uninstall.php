<?php
/**
 * Cookies lišta moja – odinstalace: smaže nastavení.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'clm' );
wp_clear_scheduled_hook( 'clm_scan_event' );
