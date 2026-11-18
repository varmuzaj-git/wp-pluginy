<?php
/**
 * Plugin Name:       Cookies lišta moja
 * Description:       Jednoduchá a lehká cookies lišta. Při aktivaci sama prohledá web, najde používané služby (Google Analytics, YouTube, Facebook…), nastaví je a do souhlasu je blokuje.
 * Version:           1.2.0
 * Author:            Jan Varmuža
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/varmuzaj-git/wp-pluginy
 */

defined( 'ABSPATH' ) || exit;

define( 'CLM_VER', '1.2.0' );
define( 'CLM_FILE', __FILE__ );

// Aktualizace z GitHubu (varmuzaj-git/wp-pluginy).
require_once __DIR__ . '/updater.php';

/** Kategorie cookies. */
function clm_categories() {
	return array( 'necessary', 'statistics', 'marketing' );
}

/** Výchozí nastavení. */
function clm_defaults() {
	return array(
		// Vzhled.
		'position'      => 'box-left',   // bar-bottom | bar-top | box-left | box-right | center
		'bg'            => '#ffffff',
		'text'          => '#1e1e1e',
		'accent'        => '#1d4ed8',
		'accent_text'   => '#ffffff',
		'radius'        => 10,
		'font_size'     => 15,
		'reopen'        => 'left',        // left | right | none – plovoucí tlačítko pro změnu souhlasu
		// Texty.
		'title'         => 'Používáme cookies',
		'message'       => 'Tento web používá cookies. Nezbytné cookies zajišťují jeho fungování, ostatní (statistické a marketingové) použijeme jen s vaším souhlasem.',
		'btn_accept'    => 'Přijmout vše',
		'btn_reject'    => 'Odmítnout',
		'btn_settings'  => 'Nastavení',
		'btn_save'      => 'Uložit výběr',
		'modal_title'   => 'Nastavení cookies',
		'policy_text'   => 'Zásady cookies',
		'policy_page'   => 0,
		'cat_necessary' => 'Nezbytné',
		'cat_statistics' => 'Statistické',
		'cat_marketing' => 'Marketingové',
		'desc_necessary' => 'Zajišťují základní funkce webu, například přihlášení nebo zapamatování vaší volby cookies. Nelze je vypnout.',
		'desc_statistics' => 'Pomáhají nám pochopit, jak návštěvníci web používají (anonymní statistiky návštěvnosti).',
		'desc_marketing' => 'Používají je vložená videa, mapy, sociální sítě a reklamní systémy ke sledování napříč weby.',
		'placeholder'   => 'Tento obsah (%s) se zobrazí až po vašem souhlasu s cookies.',
		'placeholder_btn' => 'Povolit a zobrazit',
		// Chování.
		'show'          => 'always',      // always | auto (jen když web používá nepovinné služby)
		'expiry'        => 180,           // dní
		'version'       => 1,             // zvýšení = znovu se zeptat všech návštěvníků
		'gcm'           => 1,             // Google Consent Mode v2
		'block'         => 1,             // blokovat skripty a iframy do souhlasu
		// Služby (naplní automatické prohledání webu).
		'services'      => array(),
		'scanned'       => 0,
	);
}

/** Nastavení (jednou za požadavek). */
function clm_opt( $key = null, $reset = false ) {
	static $o = null;
	if ( null === $o || $reset ) {
		$o = get_option( 'clm' );
		$o = ( is_array( $o ) ? $o : array() ) + clm_defaults();
	}
	return null === $key ? $o : ( isset( $o[ $key ] ) ? $o[ $key ] : null );
}

/** Služby vloženého obsahu – rozpoznávají se jen podle adresy iframu/skriptu, ne podle textu v kódu. */
function clm_embed_ids() {
	return array( 'youtube', 'vimeo', 'google-maps', 'mapy-com', 'spotify', 'instagram', 'facebook', 'twitter' );
}

/** Služby, které je potřeba blokovat (nepovinné kategorie). */
function clm_blocked_services() {
	$out = array();
	foreach ( (array) clm_opt( 'services' ) as $s ) {
		if ( ! empty( $s['cat'] ) && 'necessary' !== $s['cat'] && ! empty( $s['patterns'] ) ) {
			$s['embed'] = ! empty( $s['embed'] ) || ( isset( $s['id'] ) && in_array( $s['id'], clm_embed_ids(), true ) );
			$out[]      = $s;
		}
	}
	return $out;
}

/** Má se lišta zobrazit? */
function clm_active() {
	if ( 'auto' !== clm_opt( 'show' ) ) {
		return true;
	}
	foreach ( (array) clm_opt( 'services' ) as $s ) {
		if ( ! empty( $s['cat'] ) && 'necessary' !== $s['cat'] ) {
			return true;
		}
	}
	return false;
}

/* ------------------------------------------------------------------
 * Frontend
 * ------------------------------------------------------------------ */

if ( ! is_admin() ) {

	// Google Consent Mode – musí být v <head> co nejdřív, před Google značkami.
	add_action(
		'wp_head',
		function () {
			if ( ! clm_opt( 'gcm' ) || ! clm_active() ) {
				return;
			}
			$v = (int) clm_opt( 'version' );
			echo "<script id=\"clm-gcm\" data-clm-skip>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};"
				. "gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});"
				. "(function(){var m=document.cookie.match(/(?:^|; )clm_consent=([^;]*)/);if(!m)return;var p;try{p=decodeURIComponent(m[1]).split('.');}catch(e){return;}if(p[0]!=='" . $v . "')return;var a=(p[1]||'').split('-'),s=a.indexOf('statistics')>-1?'granted':'denied',k=a.indexOf('marketing')>-1?'granted':'denied';"
				. "gtag('consent','update',{analytics_storage:s,ad_storage:k,ad_user_data:k,ad_personalization:k});})();</script>\n";
		},
		1
	);

	add_action(
		'wp_enqueue_scripts',
		function () {
			if ( ! clm_active() ) {
				return;
			}
			$url = plugin_dir_url( CLM_FILE ) . 'assets/';
			$dir = plugin_dir_path( CLM_FILE ) . 'assets/';
			wp_enqueue_style( 'clm', $url . 'clm.css', array(), CLM_VER . '.' . (int) @filemtime( $dir . 'clm.css' ) );
			wp_enqueue_script(
				'clm',
				$url . 'clm.js',
				array(),
				CLM_VER . '.' . (int) @filemtime( $dir . 'clm.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	);

	// Konfigurace pro JS (stejná pro všechny návštěvníky – funguje i s cache stránek).
	add_action(
		'wp_footer',
		function () {
			if ( ! clm_active() ) {
				return;
			}
			echo '<script id="clm-config" type="application/json">' . wp_json_encode( clm_front_config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
		},
		5
	);

	// Blokování skriptů a iframů do udělení souhlasu.
	add_action(
		'template_redirect',
		function () {
			if ( ! clm_opt( 'block' ) || ! clm_active() || is_feed() || is_embed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				return;
			}
			if ( clm_blocked_services() ) {
				ob_start( 'clm_filter_html' );
			}
		},
		PHP_INT_MAX
	);

	add_shortcode(
		'cookies_lista_nastaveni',
		function ( $atts ) {
			$atts = shortcode_atts( array( 'text' => 'Nastavení cookies' ), $atts );
			return '<button type="button" class="clm-open wp-element-button">' . esc_html( $atts['text'] ) . '</button>';
		}
	);
	add_shortcode(
		'cookies_lista_seznam',
		function () {
			require_once __DIR__ . '/policy.php';
			return clm_policy_table();
		}
	);
}

/** Data pro JavaScript. */
function clm_front_config() {
	$o    = clm_opt();
	$cats = array();
	foreach ( clm_categories() as $c ) {
		$cats[ $c ] = array(
			'name'     => $o[ 'cat_' . $c ],
			'desc'     => $o[ 'desc_' . $c ],
			'services' => array(),
		);
	}
	$cookies = array();
	foreach ( (array) $o['services'] as $s ) {
		$c = isset( $s['cat'] ) && isset( $cats[ $s['cat'] ] ) ? $s['cat'] : 'marketing';
		$cats[ $c ]['services'][] = array( $s['name'], isset( $s['cookies'] ) ? $s['cookies'] : '' );
		if ( 'necessary' !== $c && ! empty( $s['cookies'] ) ) {
			foreach ( preg_split( '/\s*,\s*/', $s['cookies'] ) as $ck ) {
				if ( '' !== $ck ) {
					$cookies[ $c ][] = $ck;
				}
			}
		}
	}
	$policy = $o['policy_page'] ? get_permalink( (int) $o['policy_page'] ) : '';
	return array(
		'v'       => (int) $o['version'],
		'exp'     => (int) $o['expiry'],
		'pos'     => $o['position'],
		'reopen'  => $o['reopen'],
		'style'   => array(
			'bg'     => $o['bg'],
			'text'   => $o['text'],
			'accent' => $o['accent'],
			'atext'  => $o['accent_text'],
			'radius' => (int) $o['radius'],
			'font'   => (int) $o['font_size'],
		),
		't'       => array(
			'title'    => $o['title'],
			'msg'      => $o['message'],
			'accept'   => $o['btn_accept'],
			'reject'   => $o['btn_reject'],
			'settings' => $o['btn_settings'],
			'save'     => $o['btn_save'],
			'mtitle'   => $o['modal_title'],
			'policy'   => $o['policy_text'],
			'ph'       => $o['placeholder'],
			'phbtn'    => $o['placeholder_btn'],
		),
		'policy'  => $policy ? $policy : '',
		'cats'    => $cats,
		'cookies' => $cookies,
	);
}

/**
 * Načte otevírací značku od pozice $i (hned za názvem značky) podle pravidel HTML tokenizéru.
 * Vrací [atributy (první výskyt vyhrává, hodnoty dekódované), index znaku '>' nebo délka řetězce].
 * Lineární průchod – žádné regulární výrazy nad obsahem stránky, takže nehrozí selhání PCRE.
 */
function clm_read_tag( $s, $i ) {
	$len   = strlen( $s );
	$attrs = array();
	$ws    = " \t\n\r\f";
	while ( $i < $len ) {
		while ( $i < $len && ( false !== strpos( $ws, $s[ $i ] ) || '/' === $s[ $i ] ) ) {
			$i++;
		}
		if ( $i >= $len || '>' === $s[ $i ] ) {
			break;
		}
		$st = $i;
		$i++; // první znak patří do názvu vždy (i '=')
		while ( $i < $len && false === strpos( $ws . '/>=', $s[ $i ] ) ) {
			$i++;
		}
		$name = strtolower( substr( $s, $st, $i - $st ) );
		while ( $i < $len && false !== strpos( $ws, $s[ $i ] ) ) {
			$i++;
		}
		$val = '';
		if ( $i < $len && '=' === $s[ $i ] ) {
			$i++;
			while ( $i < $len && false !== strpos( $ws, $s[ $i ] ) ) {
				$i++;
			}
			if ( $i < $len && ( '"' === $s[ $i ] || "'" === $s[ $i ] ) ) {
				$e   = strpos( $s, $s[ $i ], $i + 1 );
				$e   = false === $e ? $len : $e;
				$val = substr( $s, $i + 1, $e - $i - 1 );
				$i   = $e + 1;
			} else {
				$st = $i;
				while ( $i < $len && false === strpos( $ws . '>', $s[ $i ] ) ) {
					$i++;
				}
				$val = substr( $s, $st, $i - $st );
			}
		}
		if ( ! isset( $attrs[ $name ] ) ) {
			$attrs[ $name ] = html_entity_decode( $val, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
	}
	return array( $attrs, min( $i, $len ) );
}

/** Sestaví otevírací značku z atributů. */
function clm_build_tag( $tag, $attrs ) {
	$out = '<' . $tag;
	foreach ( $attrs as $n => $v ) {
		if ( preg_match( '/^[^\s"\'<>\/=]+$/', (string) $n ) ) {
			$out .= ' ' . $n . '="' . esc_attr( $v ) . '"';
		}
	}
	return $out . '>';
}

/** Je typ skriptu spustitelný JavaScript? (podle HTML specifikace) */
function clm_is_js_type( $type ) {
	$type = strtolower( trim( (string) $type ) );
	return '' === $type || 'module' === $type
		|| (bool) preg_match( '#^(text|application)/(x-)?(java|ecma)script$|^text/javascript1\.[0-5]$|^text/(jscript|livescript)$#', $type );
}

/** Normalizace textu pro porovnání se vzory: bez mezer, jednotné uvozovky. */
function clm_norm( $t ) {
	$n = preg_replace( '/\s+/', '', (string) $t );
	return str_replace( array( '"', '`' ), "'", null === $n ? (string) $t : $n );
}

/**
 * Nejpřísnější služba, jejíž vzor se v textu vyskytuje (marketing > statistika).
 * Porovnává se doslovně i po normalizaci (mezery, typ uvozovek).
 * Vrací [kategorie, název, pořadí] nebo null.
 */
function clm_match_service( $haystack, $services ) {
	static $rank = array( 'necessary' => 0, 'statistics' => 1, 'marketing' => 2 );
	$best = null;
	$norm = null;
	foreach ( $services as $s ) {
		$cat = isset( $rank[ $s['cat'] ] ) ? $s['cat'] : 'marketing';
		if ( $best && $best[2] >= $rank[ $cat ] ) {
			continue;
		}
		foreach ( preg_split( '/\R+/', (string) $s['patterns'] ) as $p ) {
			$p = trim( $p );
			if ( '' === $p ) {
				continue;
			}
			if ( false === stripos( $haystack, $p ) ) {
				if ( null === $norm ) {
					$norm = clm_norm( $haystack );
				}
				if ( false === stripos( $norm, clm_norm( $p ) ) ) {
					continue;
				}
			}
			$best = array( $cat, $s['name'], $rank[ $cat ] );
			break;
		}
	}
	return $best;
}

/** Pozice konce uzavírací značky </name…> od pozice $from (nebo délka řetězce). */
function clm_find_close( $html, $name, $from ) {
	$len = strlen( $html );
	$l   = strlen( $name ) + 2;
	while ( false !== ( $c = stripos( $html, '</' . $name, $from ) ) ) {
		$nx = isset( $html[ $c + $l ] ) ? $html[ $c + $l ] : '>';
		if ( false !== strpos( " \t\n\r\f/>", $nx ) ) {
			$g = strpos( $html, '>', $c );
			return array( $c, false === $g ? $len : $g + 1 );
		}
		$from = $c + $l;
	}
	return array( $len, $len );
}

/**
 * Úprava HTML: skripty a iframy služeb dostanou type="text/plain" / data-clm-src.
 * Lineární průchod dokumentem podle pravidel HTML: přeskakuje komentáře, atributy
 * ostatních značek i obsah prvků, ve kterých se značky neinterpretují (style, textarea…).
 */
function clm_filter_html( $html ) {
	if ( strlen( $html ) < 200 || false === stripos( $html, '<html' ) ) {
		return $html;
	}
	$services = clm_blocked_services();
	if ( ! $services ) {
		return $html;
	}
	$code_services = array_values( array_filter( $services, function ( $s ) { return empty( $s['embed'] ); } ) );
	$raw    = array( 'style' => 1, 'textarea' => 1, 'title' => 1, 'xmp' => 1, 'noscript' => 1, 'noembed' => 1, 'noframes' => 1, 'plaintext' => 1 );
	$len    = strlen( $html );
	$out    = '';
	$copied = 0;
	$pos    = 0;
	while ( false !== ( $lt = strpos( $html, '<', $pos ) ) ) {
		// Komentář.
		if ( 0 === substr_compare( $html, '<!--', $lt, 4 ) ) {
			// Ukončení komentáře podle HTML: "<!-->", "<!--->", jinak první "-->" nebo "--!>".
			$k = $lt + 4;
			if ( isset( $html[ $k ] ) && '>' === $html[ $k ] ) {
				$pos = $k + 1;
			} elseif ( 0 === substr_compare( $html, '->', $k, 2 ) ) {
				$pos = $k + 2;
			} else {
				$a   = strpos( $html, '-->', $k );
				$b   = strpos( $html, '--!>', $k );
				$pos = false === $a && false === $b ? $len : ( false === $b || ( false !== $a && $a < $b ) ? $a + 3 : $b + 4 );
			}
			continue;
		}
		$c = isset( $html[ $lt + 1 ] ) ? $html[ $lt + 1 ] : '';
		// <!DOCTYPE>, <?…> – až do '>'.
		if ( '!' === $c || '?' === $c ) {
			$e   = strpos( $html, '>', $lt );
			$pos = false === $e ? $len : $e + 1;
			continue;
		}
		$closing = '/' === $c;
		$ns      = $lt + ( $closing ? 2 : 1 );
		if ( ! isset( $html[ $ns ] ) || ! ctype_alpha( $html[ $ns ] ) ) {
			$pos = $lt + 1; // obyčejný znak '<' v textu
			continue;
		}
		$ne = $ns;
		while ( $ne < $len && false === strpos( " \t\n\r\f/>", $html[ $ne ] ) ) {
			$ne++;
		}
		$name               = strtolower( substr( $html, $ns, $ne - $ns ) );
		list( $attrs, $gt ) = clm_read_tag( $html, $ne );
		$open_end           = min( $gt + 1, $len );
		if ( $closing ) {
			$pos = $open_end;
			continue;
		}
		if ( 'plaintext' === $name ) {
			break;
		}
		if ( isset( $raw[ $name ] ) ) {
			list( , $pos ) = clm_find_close( $html, $name, $open_end );
			continue;
		}

		if ( 'script' === $name ) {
			list( $cstart, $cend ) = clm_find_close( $html, 'script', $open_end );
			$hit                   = null;
			if ( ! isset( $attrs['data-clm-skip'] ) && ! isset( $attrs['data-clm-cat'] ) && clm_is_js_type( isset( $attrs['type'] ) ? $attrs['type'] : '' ) ) {
				// Adresa skriptu se porovná se všemi službami, obsah skriptu jen se službami měření/reklamy.
				$hit = isset( $attrs['src'] ) && '' !== $attrs['src'] ? clm_match_service( $attrs['src'], $services ) : null;
				$h2  = $code_services ? clm_match_service( substr( $html, $open_end, $cstart - $open_end ), $code_services ) : null;
				if ( $h2 && ( ! $hit || $h2[2] > $hit[2] ) ) {
					$hit = $h2;
				}
			}
			if ( $hit ) {
				$type = strtolower( trim( isset( $attrs['type'] ) ? $attrs['type'] : '' ) );
				unset( $attrs['type'] );
				$new = array( 'type' => 'text/plain', 'data-clm-cat' => $hit[0] );
				if ( 'module' === $type ) {
					$new['data-clm-type'] = 'module';
				}
				$out   .= substr( $html, $copied, $lt - $copied ) . clm_build_tag( 'script', $new + $attrs );
				$copied = $open_end;
			}
			$pos = $cend;
			continue;
		}

		if ( 'iframe' === $name && ! isset( $attrs['data-clm-cat'] ) && ! isset( $attrs['data-clm-skip'] ) ) {
			// Vyhodnotit všechny zdroje (i líné načítání a srcdoc), platí nejpřísnější.
			$hit = null;
			$url = '';
			foreach ( array( 'src', 'data-src', 'data-lazy-src' ) as $a ) {
				if ( isset( $attrs[ $a ] ) && '' !== $attrs[ $a ] && 'about:blank' !== $attrs[ $a ] ) {
					$h = clm_match_service( $attrs[ $a ], $services );
					if ( $h && ( ! $hit || $h[2] > $hit[2] ) ) {
						$hit = $h;
						$url = $attrs[ $a ];
					}
				}
			}
			$doc = isset( $attrs['srcdoc'] ) ? $attrs['srcdoc'] : '';
			if ( '' !== $doc ) {
				$h = clm_match_service( $doc, $services );
				if ( $h && ( ! $hit || $h[2] > $hit[2] ) ) {
					$hit = $h;
				}
			}
			if ( $hit ) {
				$new = array( 'data-clm-cat' => $hit[0], 'data-clm-name' => $hit[1] );
				if ( '' !== $url ) {
					$new['data-clm-src'] = $url;
				} elseif ( isset( $attrs['src'] ) && '' !== $attrs['src'] && 'about:blank' !== $attrs['src'] ) {
					$new['data-clm-src'] = $attrs['src'];
				}
				if ( '' !== $doc ) {
					$new['data-clm-srcdoc'] = $doc;
				}
				unset( $attrs['src'], $attrs['data-src'], $attrs['data-lazy-src'], $attrs['srcdoc'] );
				$out   .= substr( $html, $copied, $lt - $copied ) . clm_build_tag( 'iframe', $new + $attrs );
				$copied = $open_end;
			}
		}
		if ( 'iframe' === $name ) {
			// Obsah iframe je pro prohlížeč jen text – přeskočit až za </iframe>.
			list( , $pos ) = clm_find_close( $html, 'iframe', $open_end );
			continue;
		}
		$pos = $open_end;
	}
	return $out . substr( $html, $copied );
}

/** Vyčistit cache stránek, aby se změna projevila hned (WP Super Cache, W3TC, WP Rocket, LiteSpeed…). */
function clm_purge_cache() {
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
	do_action( 'litespeed_purge_all' );
	do_action( 'clm_purge_cache' );
}

/* ------------------------------------------------------------------
 * Administrace, aktivace, prohledání webu
 * ------------------------------------------------------------------ */

if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

add_action(
	'clm_scan_event',
	function () {
		require_once __DIR__ . '/scan.php';
		clm_scan( true );
		clm_purge_cache();
	}
);

register_activation_hook( __FILE__, 'clm_activate' );
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'clm_scan_event' );
		if ( function_exists( 'clm_purge_cache' ) ) {
			clm_purge_cache();
		}
	}
);

function clm_activate() {
	$o = get_option( 'clm' );
	if ( ! is_array( $o ) ) {
		$o   = clm_defaults();
		$pid = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $pid && 'publish' === get_post_status( $pid ) ) {
			$o['policy_page'] = $pid;
		}
		add_option( 'clm', $o );
	}
	clm_opt( null, true );
	// Rychlé prohledání hned (pluginy + obsah v databázi), prohledání HTML stránek na pozadí.
	require_once __DIR__ . '/scan.php';
	clm_scan( false );
	if ( ! wp_next_scheduled( 'clm_scan_event' ) ) {
		wp_schedule_single_event( time() + 15, 'clm_scan_event' );
	}
	if ( function_exists( 'clm_purge_cache' ) ) {
		clm_purge_cache();
	}
}
