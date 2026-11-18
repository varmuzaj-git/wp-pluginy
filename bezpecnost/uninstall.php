<?php
/**
 * Bezpečnost – odinstalace: smaže nastavení, log a dočasné záznamy o blokacích.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Pro jistotu odstranit blok z .htaccess (běžně už ho odstranila deaktivace).
if ( ! function_exists( 'get_home_path' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}
$bzp_file = get_home_path() . '.htaccess';
if ( is_file( $bzp_file ) && is_writable( $bzp_file ) && ( $bzp_fh = @fopen( $bzp_file, 'c+' ) ) ) {
	// Čtení i zápis pod jedním zámkem.
	if ( flock( $bzp_fh, LOCK_EX ) ) {
		$bzp_c = stream_get_contents( $bzp_fh );
		if ( false !== $bzp_c && false !== strpos( $bzp_c, '# BEGIN Bezpecnost' ) ) {
			$bzp_n = preg_replace( '/^# BEGIN Bezpecnost[ \t]*\R(?:(?!# (?:BEGIN|END) Bezpecnost).*\R)*?# END Bezpecnost[ \t]*(?:\R|\z)(?:[ \t]*\R)*/m', '', $bzp_c );
			$bzp_n = null === $bzp_n ? null : preg_replace( '/^# (?:BEGIN|END) Bezpecnost[ \t]*(?:\R|\z)/m', '', $bzp_n );
			if ( null !== $bzp_n && rewind( $bzp_fh ) && ftruncate( $bzp_fh, 0 ) ) {
				fwrite( $bzp_fh, $bzp_n );
				fflush( $bzp_fh );
			}
		}
		flock( $bzp_fh, LOCK_UN );
	}
	fclose( $bzp_fh );
}

global $wpdb;
delete_option( 'bezpecnost' );
delete_option( 'bezpecnost_log' );
delete_option( 'bezpecnost_ht' );
delete_option( 'bezpecnost_gen' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_bzp\\_%' OR option_name LIKE '\\_transient\\_timeout\\_bzp\\_%'" );
