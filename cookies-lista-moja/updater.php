<?php
/**
 * Nabídka aktualizací z GitHubu: https://github.com/varmuzaj-git/wp-pluginy
 * WordPress (i MainWP) pak novou verzi ukáže mezi běžnými aktualizacemi pluginů;
 * nic se neinstaluje samo, aktualizaci potvrzuje správce.
 *
 * Knihovna Plugin Update Checker (Jānis Elsts, MIT) se načítá jen tam, kde je potřeba:
 * administrace, WP-Cron, WP-CLI a požadavky z MainWP Dashboardu. Na běžném frontendu se nenačítá.
 */

defined( 'ABSPATH' ) || exit;

if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || isset( $_REQUEST['mainwpsignature'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- jen rozhoduje o načtení knihovny
	require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
	\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://raw.githubusercontent.com/varmuzaj-git/wp-pluginy/main/updates/cookies-lista-moja.json',
		CLM_FILE,
		'cookies-lista-moja'
	);
}
