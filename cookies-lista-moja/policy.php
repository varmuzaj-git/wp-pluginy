<?php
/**
 * Cookies lišta moja – tabulka cookies pro stránku Zásady cookies ([cookies_lista_seznam]).
 */

defined( 'ABSPATH' ) || exit;

function clm_policy_table() {
	$o   = clm_opt();
	$out = '<div class="clm-policy">';
	foreach ( clm_categories() as $c ) {
		$rows = array();
		foreach ( (array) $o['services'] as $s ) {
			if ( isset( $s['cat'] ) && $s['cat'] === $c ) {
				$rows[] = $s;
			}
		}
		if ( ! $rows ) {
			continue;
		}
		$out .= '<h3>' . esc_html( $o[ 'cat_' . $c ] ) . '</h3><p>' . esc_html( $o[ 'desc_' . $c ] ) . '</p>';
		$out .= '<table><thead><tr><th>Služba</th><th>Účel</th><th>Cookies</th></tr></thead><tbody>';
		foreach ( $rows as $s ) {
			$out .= '<tr><td>' . esc_html( $s['name'] ) . '</td><td>' . esc_html( isset( $s['desc'] ) ? $s['desc'] : '' ) . '</td><td><code>' . esc_html( isset( $s['cookies'] ) ? $s['cookies'] : '' ) . '</code></td></tr>';
		}
		$out .= '</tbody></table>';
	}
	$out .= '<p><button type="button" class="clm-open wp-element-button">' . esc_html( $o['btn_settings'] ) . ' cookies</button></p></div>';
	return $out;
}
