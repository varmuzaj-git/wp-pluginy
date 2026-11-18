<?php
/**
 * Plugin Name:       Bezpečnost
 * Description:       Lehká ochrana WordPressu: vlastní přihlašovací adresa, vypnutí XML-RPC, omezení pokusů o přihlášení, ochrana proti zjišťování uživatelů a bezpečnostní hlavičky.
 * Version:           1.2.0
 * Author:            Jan Varmuža
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/varmuzaj-git/wp-pluginy
 *
 * Nouzové vypnutí skrytí přihlášení: do wp-config.php přidejte
 * define( 'BEZPECNOST_NOUZOVY_REZIM', true );
 * nebo přejmenujte složku pluginu přes FTP.
 */

defined( 'ABSPATH' ) || exit;

define( 'BZP_VER', '1.2.0' );
define( 'BZP_FILE', __FILE__ );

// Aktualizace z GitHubu (varmuzaj-git/wp-pluginy).
require_once __DIR__ . '/updater.php';

/** Výchozí nastavení. */
function bzp_defaults() {
	return array(
		'login_slug'     => '',
		'login_block'    => '404',  // 404 | home
		'xmlrpc'         => 1,
		'htaccess'       => 1,
		'limit'          => 1,
		'limit_attempts' => 5,
		'limit_minutes'  => 20,
		'limit_long_after' => 4,   // po kolika blokacích přijde dlouhá blokace
		'limit_long_hours' => 24,  // délka dlouhé blokace
		'limit_reset_hours' => 12, // po kolika hodinách se neúspěšné pokusy vynulují
		'whitelist'      => '',
		'enum'           => 1,
		'login_errors'   => 1,
		'file_edit'      => 1,
		'version'        => 1,
		'headers'        => 1,
	);
}

/** Nastavení (načtené jednou za požadavek). */
function bzp_opt( $key = null, $reset = false ) {
	static $o = null;
	if ( null === $o || $reset ) {
		$o = get_option( 'bezpecnost' );
		$o = ( is_array( $o ) ? $o : array() ) + bzp_defaults();
	}
	return null === $key ? $o : ( isset( $o[ $key ] ) ? $o[ $key ] : null );
}

/** Pluginy, které také mění přihlašovací adresu – při souběhu se naše skrytí přihlášení nepoužije. */
function bzp_conflicts() {
	return array(
		'wps-hide-login/wps-hide-login.php'                 => 'WPS Hide Login',
		'admin-login-url-change/admin-login-url-change.php' => 'Admin Login URL Change',
		'rename-wp-login/rename-wp-login.php'               => 'Rename wp-login.php',
	);
}

function bzp_conflict() {
	static $c = null;
	if ( null === $c ) {
		$c      = '';
		$active = (array) get_option( 'active_plugins', array() );
		foreach ( bzp_conflicts() as $file => $name ) {
			if ( in_array( $file, $active, true ) ) {
				$c = $name;
				break;
			}
		}
	}
	return $c;
}

/** Aktivní přihlašovací slug, nebo '' pokud je skrytí přihlášení vypnuté. */
function bzp_slug() {
	if ( ( defined( 'BEZPECNOST_NOUZOVY_REZIM' ) && BEZPECNOST_NOUZOVY_REZIM ) || is_multisite() ) {
		return '';
	}
	$s = (string) bzp_opt( 'login_slug' );
	// Neplatná uložená adresa (např. ze starší verze) se nepoužije – raději výchozí přihlášení než zablokovaný přístup.
	return ( '' !== $s && '' === bzp_conflict() && bzp_slug_ok( $s, false ) ) ? $s : '';
}

/**
 * Je slug přihlašovací adresy platný? Jen a–z, 0–9 a pomlčky (4–60 znaků).
 * $strict = true navíc odmítne snadno uhodnutelné „login“, „admin“ a „dashboard“
 * (při převzetí ze starého pluginu je ponecháme, aby se přihlašování nezměnilo).
 */
function bzp_slug_ok( $slug, $strict = true ) {
	$slug = (string) $slug;
	if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{2,59}$/', $slug ) ) {
		return false;
	}
	$hard = array( 'wp-admin', 'wp-login', 'wp-login-php', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'index-php', 'xmlrpc', 'xmlrpc-php', 'wp-signup', 'wp-activate', 'wp-cron', 'wp-register', 'embed', 'comments', 'search', 'author', 'page', 'category', 'tag', 'wp-sitemap' );
	$weak = array( 'login', 'admin', 'dashboard' );
	if ( in_array( $slug, $hard, true ) || ( $strict && in_array( $slug, $weak, true ) ) ) {
		return false;
	}
	return $strict ? strlen( $slug ) >= 4 : true;
}

/** Adresa přihlašovací stránky. */
function bzp_login_url( $scheme = null ) {
	$s = bzp_slug();
	if ( get_option( 'permalink_structure' ) ) {
		return home_url( '/' . $s . '/', $scheme );
	}
	return home_url( '/', $scheme ) . '?' . $s;
}

/** Ukončí požadavek prázdnou chybou 404 (bez načítání šablony – co nejmenší zátěž). */
function bzp_404( $allow_home = true ) {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	if ( $allow_home && 'home' === bzp_opt( 'login_block' ) && ! headers_sent() ) {
		header( 'Cache-Control: no-store, max-age=0' );
		header( 'Location: ' . home_url( '/' ), true, 302 );
		exit;
	}
	if ( ! headers_sent() ) {
		http_response_code( 404 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
	echo "<!doctype html><meta charset=\"utf-8\"><title>404</title><h1>404 – Stránka nenalezena</h1><!--bzp-->";
	exit;
}

/* ------------------------------------------------------------------
 * 1) Okamžité blokování – běží hned při načtení pluginu,
 *    dříve než se načte většina ostatních pluginů a šablona.
 * ------------------------------------------------------------------ */

function bzp_early() {
	global $pagenow;

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	// XML-RPC.
	if ( bzp_opt( 'xmlrpc' ) && defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		bzp_404( false );
	}

	$slug = bzp_slug();
	if ( '' === $slug ) {
		// Nouzový režim: odstranit blokaci wp-login.php z .htaccess, jinak by se nešlo přihlásit.
		// Stačí otevřít libovolnou stránku webu; bez HTTP kontrol (žádné volání sama sebe).
		if ( defined( 'BEZPECNOST_NOUZOVY_REZIM' ) && BEZPECNOST_NOUZOVY_REZIM && ! is_multisite() ) {
			require_once __DIR__ . '/admin.php';
			bzp_htaccess_emergency();
		}
		return;
	}

	// Přímý přístup na wp-login.php, wp-signup.php, wp-activate.php.
	if ( in_array( $pagenow, array( 'wp-login.php', 'wp-signup.php', 'wp-activate.php', 'wp-register.php' ), true ) && ! is_admin() ) {
		bzp_404();
	}

	// Administrace pro nepřihlášené (bez přihlašovací cookie) – kromě AJAXu a admin-post.php.
	if ( is_admin() && ! wp_doing_ajax() && 'admin-post.php' !== $pagenow && ! wp_doing_cron()
		&& ( ! defined( 'LOGGED_IN_COOKIE' ) || empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) ) {
		bzp_404();
	}

	// Je to požadavek na naši přihlašovací adresu?
	$GLOBALS['bzp_is_login'] = false;
	if ( ! is_admin() ) {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
		$path = parse_url( $uri, PHP_URL_PATH );
		$path = is_string( $path ) ? rawurldecode( $path ) : '/';
		$home = rtrim( (string) parse_url( home_url(), PHP_URL_PATH ), '/' );
		$path = rtrim( $path, '/' );
		if ( $path === $home . '/' . $slug ) {
			$GLOBALS['bzp_is_login'] = true;
		} elseif ( ! get_option( 'permalink_structure' ) && isset( $_GET[ $slug ] ) && ( $path === $home || $path === $home . '/index.php' ) ) {
			$GLOBALS['bzp_is_login'] = true;
		}
	}
}
bzp_early();

/** Nastavení hlavičky pro wp-login.php (jako WPS Hide Login – až po načtení všech pluginů). */
add_action(
	'plugins_loaded',
	function () {
		global $pagenow;
		if ( ! empty( $GLOBALS['bzp_is_login'] ) ) {
			$pagenow                = 'wp-login.php';
			$_SERVER['SCRIPT_NAME'] = bzp_slug();
		}
	},
	9999
);

/* ------------------------------------------------------------------
 * 2) Vlastní přihlašovací adresa
 * ------------------------------------------------------------------ */

if ( '' !== bzp_slug() ) {

	// Zobrazení přihlašovací stránky na vlastní adrese.
	add_action(
		'wp_loaded',
		function () {
			global $pagenow;

			// Administrace s neplatnou/expirovanou cookie.
			if ( is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && 'admin-post.php' !== $pagenow && ! is_user_logged_in() ) {
				bzp_404();
			}

			if ( empty( $GLOBALS['bzp_is_login'] ) ) {
				return;
			}
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}

			// Doplnit koncové lomítko.
			$path = (string) parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
			if ( get_option( 'permalink_structure' ) && '/' !== substr( $path, -1 ) ) {
				$qs = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
				wp_safe_redirect( bzp_login_url() . $qs );
				exit;
			}

			// Přihlášený uživatel bez akce → rovnou do administrace.
			if ( is_user_logged_in() && ! isset( $_REQUEST['action'] ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}

			// wp-login.php počítá s globálním rozsahem proměnných.
			global $error, $interim_login, $action, $user_login, $user, $redirect_to, $errors;
			require_once ABSPATH . 'wp-login.php';
			exit;
		},
		1
	);

	// Přepis všech odkazů na wp-login.php.
	function bzp_filter_url( $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) ) {
			return $url;
		}
		$q    = strpos( $url, '?' );
		$base = false === $q ? $url : substr( $url, 0, $q );
		if ( 'wp-login.php' !== substr( $base, -12 ) ) {
			return $url;
		}
		$host = parse_url( $base, PHP_URL_HOST );
		if ( $host && strtolower( $host ) !== strtolower( (string) parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return $url; // cizí web (např. wordpress.com)
		}
		$new = bzp_login_url( parse_url( $base, PHP_URL_SCHEME ) ?: null );
		if ( false !== $q ) {
			$new .= ( false === strpos( $new, '?' ) ? '?' : '&' ) . substr( $url, $q + 1 );
		}
		return $new;
	}
	add_filter( 'site_url', 'bzp_filter_url', 100 );
	add_filter( 'network_site_url', 'bzp_filter_url', 100 );
	add_filter( 'wp_redirect', 'bzp_filter_url', 100 );
	add_filter(
		'site_option_welcome_email',
		function ( $v ) {
			return str_replace( 'wp-login.php', trailingslashit( bzp_slug() ), $v );
		}
	);

	// Zkratky /login, /admin, /dashboard by prozradily skrytou adresu.
	remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
}

/* ------------------------------------------------------------------
 * 3) XML-RPC
 * ------------------------------------------------------------------ */

if ( bzp_opt( 'xmlrpc' ) ) {
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'xmlrpc_methods', '__return_empty_array' );
	add_filter(
		'wp_headers',
		function ( $h ) {
			unset( $h['X-Pingback'] );
			return $h;
		}
	);
	remove_action( 'wp_head', 'rsd_link' );
	add_filter( 'pings_open', '__return_false', 100 );
}

/* ------------------------------------------------------------------
 * 4) Omezení pokusů o přihlášení
 * ------------------------------------------------------------------ */

/** Skutečná IP klienta (REMOTE_ADDR); IPv4 zapsaná jako IPv6 (::ffff:1.2.3.4) se převede na IPv4. */
function bzp_remote_ip() {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$bin = @inet_pton( $ip );
	if ( false === $bin ) {
		return $ip;
	}
	if ( 16 === strlen( $bin ) && 0 === strpos( $bin, str_repeat( "\0", 10 ) . "\xff\xff" ) ) {
		$bin = substr( $bin, 12 );
	}
	return inet_ntop( $bin );
}

/** IP pro počítání pokusů; IPv6 se seskupuje po /64 (útočník jich má k dispozici tisíce). */
function bzp_ip() {
	$ip  = bzp_remote_ip();
	$bin = @inet_pton( $ip );
	if ( false !== $bin && 16 === strlen( $bin ) ) {
		return inet_ntop( substr( $bin, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
	}
	return $ip;
}

/** Přísné načtení IP nebo rozsahu CIDR. Vrací [binární adresa, počet bitů] nebo null. */
function bzp_parse_cidr( $entry ) {
	$entry = trim( (string) $entry );
	$parts = explode( '/', $entry, 2 );
	$net   = @inet_pton( $parts[0] );
	if ( '' === $entry || false === $net ) {
		return null;
	}
	$max = strlen( $net ) * 8;
	if ( isset( $parts[1] ) ) {
		if ( ! preg_match( '/^\d{1,3}$/', $parts[1] ) || (int) $parts[1] > $max ) {
			return null;
		}
		return array( $net, (int) $parts[1] );
	}
	return array( $net, $max );
}

/** Je IP na seznamu povolených? Podporuje přesné adresy i rozsahy CIDR (IPv4 i IPv6). */
function bzp_whitelisted( $ip = null ) {
	$list = trim( (string) bzp_opt( 'whitelist' ) );
	if ( '' === $list ) {
		return false;
	}
	$ip  = null === $ip ? bzp_remote_ip() : $ip;
	$bin = @inet_pton( $ip );
	if ( false === $bin ) {
		return false;
	}
	foreach ( preg_split( '/[\s,]+/', $list ) as $entry ) {
		$c = bzp_parse_cidr( $entry );
		if ( ! $c || strlen( $c[0] ) !== strlen( $bin ) ) {
			continue; // neplatný záznam se ignoruje (nikdy ho nerozšiřujeme na celou síť)
		}
		list( $net, $bits ) = $c;
		$full = intdiv( $bits, 8 );
		if ( substr( $bin, 0, $full ) !== substr( $net, 0, $full ) ) {
			continue;
		}
		$rest = $bits % 8;
		if ( $rest ) {
			$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
			if ( ( ord( $bin[ $full ] ) & $mask ) !== ( ord( $net[ $full ] ) & $mask ) ) {
				continue;
			}
		}
		return true;
	}
	return false;
}

function bzp_key() {
	// „Generace“ – zvýšením se okamžitě zneplatní všechny blokace (i v object cache).
	return md5( (int) get_option( 'bezpecnost_gen', 0 ) . '|' . bzp_ip() );
}

/** Do kdy je aktuální IP zablokovaná (0 = není). */
function bzp_locked_until() {
	if ( bzp_whitelisted() ) {
		return 0;
	}
	$until = (int) get_transient( 'bzp_l_' . bzp_key() );
	return $until > time() ? $until : 0;
}

function bzp_lock_message( $until ) {
	return sprintf( '<strong>Chyba:</strong> Příliš mnoho neúspěšných pokusů o přihlášení. Zkuste to znovu za %d min.', max( 1, (int) ceil( ( $until - time() ) / 60 ) ) );
}

if ( bzp_opt( 'limit' ) ) {

	// Zablokovaná IP: heslo se vůbec neověřuje (šetří procesor)…
	add_filter(
		'authenticate',
		function ( $user ) {
			if ( bzp_locked_until() ) {
				remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
				remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
			}
			return $user;
		},
		1
	);
	// …a přihlášení se odmítne i se správným heslem.
	add_filter(
		'authenticate',
		function ( $user ) {
			$until = bzp_locked_until();
			return $until ? new WP_Error( 'bzp_locked', bzp_lock_message( $until ) ) : $user;
		},
		99
	);
	// Totéž pro aplikační hesla (REST API).
	add_filter(
		'wp_authenticate_application_password_errors',
		function ( $error ) {
			$until = bzp_locked_until();
			if ( $until && is_wp_error( $error ) ) {
				$error->add( 'bzp_locked', wp_strip_all_tags( bzp_lock_message( $until ) ) );
			}
			return $error;
		}
	);

	$bzp_fail = function ( $username, $error = null ) {
		if ( is_wp_error( $error ) && in_array( 'bzp_locked', $error->get_error_codes(), true ) ) {
			return;
		}
		if ( bzp_whitelisted() ) {
			return;
		}
		$k       = bzp_key();
		$minutes = max( 1, (int) bzp_opt( 'limit_minutes' ) );
		$max     = max( 1, (int) bzp_opt( 'limit_attempts' ) );
		$reset   = max( 1, (int) bzp_opt( 'limit_reset_hours' ) ) * HOUR_IN_SECONDS;
		$fails   = (int) get_transient( 'bzp_f_' . $k ) + 1;
		if ( $fails < $max ) {
			set_transient( 'bzp_f_' . $k, $fails, $reset );
			return;
		}
		delete_transient( 'bzp_f_' . $k );
		$n = (int) get_transient( 'bzp_n_' . $k ) + 1;
		set_transient( 'bzp_n_' . $k, $n, $reset );
		// Po opakovaných blokacích → dlouhá blokace (jako Limit Login Attempts Reloaded).
		if ( $n >= max( 1, (int) bzp_opt( 'limit_long_after' ) ) ) {
			$lock = max( 1, (int) bzp_opt( 'limit_long_hours' ) ) * 60;
			delete_transient( 'bzp_n_' . $k );
		} else {
			$lock = $minutes;
		}
		set_transient( 'bzp_l_' . $k, time() + $lock * 60, $lock * 60 );

		$log = get_option( 'bezpecnost_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( time(), bzp_ip(), substr( sanitize_user( (string) $username ), 0, 60 ), $lock ) );
		update_option( 'bezpecnost_log', array_slice( $log, 0, 30 ), false );
	};
	add_action( 'wp_login_failed', $bzp_fail, 10, 2 );
	add_action(
		'application_password_failed_authentication',
		function ( $error ) use ( $bzp_fail ) {
			$bzp_fail( '(aplikační heslo)', $error );
		}
	);
}

/* ------------------------------------------------------------------
 * 5) Další ochrana
 * ------------------------------------------------------------------ */

// Obecná chybová hláška – neprozradí, jestli uživatel existuje.
if ( bzp_opt( 'login_errors' ) ) {
	add_filter(
		'authenticate',
		function ( $user ) {
			if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'incorrect_password', 'invalid_email' ) ) ) {
				return new WP_Error( 'bzp_invalid', '<strong>Chyba:</strong> Nesprávné přihlašovací údaje.' );
			}
			return $user;
		},
		98
	);
}

// Obnova hesla: pro neexistující účet stejná odpověď jako pro existující.
if ( bzp_opt( 'login_errors' ) ) {
	add_action(
		'lostpassword_post',
		function ( $errors, $user_data ) {
			if ( ! $user_data && is_wp_error( $errors ) && ! empty( $_POST['user_login'] )
				&& ! array_diff( $errors->get_error_codes(), array( 'invalid_email', 'invalidcombo' ) ) ) {
				wp_safe_redirect( site_url( 'wp-login.php?checkemail=confirm', 'login' ) );
				exit;
			}
		},
		99,
		2
	);
}

// Zjišťování uživatelských jmen (?author=1, REST API, sitemapa, oEmbed).
if ( bzp_opt( 'enum' ) ) {
	add_action(
		'parse_request',
		function () {
			if ( isset( $_REQUEST['author'] ) && ! is_user_logged_in() ) {
				bzp_404( false );
			}
		},
		1
	);
	add_filter(
		'rest_pre_dispatch',
		function ( $result, $server, $request ) {
			if ( null === $result && ! is_user_logged_in() && preg_match( '#^/wp/v2/users#i', (string) $request->get_route() ) ) {
				return new WP_Error( 'rest_forbidden', 'Přístup odepřen.', array( 'status' => 401 ) );
			}
			return $result;
		},
		10,
		3
	);
	add_filter(
		'wp_sitemaps_add_provider',
		function ( $provider, $name ) {
			return 'users' === $name ? false : $provider;
		},
		10,
		2
	);
	add_filter(
		'oembed_response_data',
		function ( $data ) {
			unset( $data['author_name'], $data['author_url'] );
			return $data;
		}
	);
}

// Zákaz úprav souborů šablon a pluginů z administrace.
if ( bzp_opt( 'file_edit' ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// Skrytí verze WordPressu.
if ( bzp_opt( 'version' ) ) {
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );
}

// Bezpečnostní HTTP hlavičky.
if ( bzp_opt( 'headers' ) ) {
	$bzp_headers = function () {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	};
	add_action( 'send_headers', $bzp_headers );
	add_action( 'admin_init', $bzp_headers );
	add_action( 'login_init', $bzp_headers );
}

/* ------------------------------------------------------------------
 * 6) Administrace, aktivace, deaktivace
 * ------------------------------------------------------------------ */

if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

register_activation_hook( __FILE__, 'bzp_activate' );
register_deactivation_hook( __FILE__, 'bzp_deactivate' );

function bzp_activate() {
	// Náhodný jmenný prostor klíčů blokací – po reinstalaci neplatí staré záznamy ani v object cache.
	if ( false === get_option( 'bezpecnost_gen', false ) ) {
		add_option( 'bezpecnost_gen', wp_rand( 1, 999999999 ) );
	}
	$o = get_option( 'bezpecnost' );
	if ( ! is_array( $o ) ) {
		$o = bzp_defaults();
		// Převzít přihlašovací adresu ze stávajících pluginů, aby se přihlašování nezměnilo.
		foreach ( array( 'whl_page', 'jh_new_login_url' ) as $old ) {
			$s = sanitize_title( (string) get_option( $old, '' ) );
			if ( bzp_slug_ok( $s, false ) ) {
				$o['login_slug'] = $s;
				break;
			}
		}
		// Převzít nastavení z Limit Login Attempts Reloaded.
		$llar = array(
			'limit_attempts'    => array( 'limit_login_allowed_retries', 1, 2, 50 ),
			'limit_minutes'     => array( 'limit_login_lockout_duration', 60, 1, 1440 ),
			'limit_long_after'  => array( 'limit_login_allowed_lockouts', 1, 1, 20 ),
			'limit_long_hours'  => array( 'limit_login_long_duration', 3600, 1, 720 ),
			'limit_reset_hours' => array( 'limit_login_valid_duration', 3600, 1, 168 ),
		);
		foreach ( $llar as $k => $d ) {
			$v = get_option( $d[0], null );
			if ( is_numeric( $v ) && $v > 0 ) {
				$o[ $k ] = min( $d[3], max( $d[2], (int) round( $v / $d[1] ) ) );
			}
		}
		$wl = get_option( 'limit_login_whitelist', array() );
		if ( is_array( $wl ) && $wl ) {
			$ok = array();
			foreach ( $wl as $ip ) {
				$ip = trim( (string) $ip );
				if ( bzp_parse_cidr( $ip ) ) {
					$ok[] = $ip;
				}
			}
			$o['whitelist'] = implode( "\n", $ok );
		}
		add_option( 'bezpecnost', $o );
	}
	bzp_opt( null, true );
	if ( function_exists( 'bzp_htaccess_sync' ) ) {
		bzp_htaccess_sync( true );
	}
}

function bzp_deactivate() {
	if ( ! function_exists( 'bzp_htaccess_write' ) ) {
		require_once __DIR__ . '/admin.php';
	}
	if ( bzp_htaccess_write( '' ) ) {
		delete_option( 'bezpecnost_ht' );
	}
}
