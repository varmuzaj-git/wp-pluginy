<?php
/**
 * Cookies lišta moja – automatické prohledání webu.
 * Načítá se jen při aktivaci, v administraci a v naplánované úloze.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Známé služby.
 * patterns = text v adrese/obsahu skriptu nebo iframu, podle kterého se blokuje,
 * plugins  = soubory pluginů, které službu přidávají,
 * db       = text, který se hledá v obsahu stránek a widgetů.
 */
function clm_signatures() {
	return array(
		'wordpress'       => array(
			'name'    => 'WordPress',
			'cat'     => 'necessary',
			'cookies' => 'wordpress_*, wordpress_logged_in_*, wp-settings-*, comment_author_*',
			'desc'    => 'Přihlášení do administrace a zapamatování údajů v komentářích.',
		),
		'clm'             => array(
			'name'    => 'Cookies lišta',
			'cat'     => 'necessary',
			'cookies' => 'clm_consent',
			'desc'    => 'Uložení vaší volby souhlasu s cookies.',
		),
		'woocommerce'     => array(
			'name'    => 'WooCommerce',
			'cat'     => 'necessary',
			'cookies' => 'woocommerce_cart_hash, woocommerce_items_in_cart, wp_woocommerce_session_*',
			'plugins' => array( 'woocommerce/woocommerce.php' ),
			'desc'    => 'Nákupní košík e-shopu.',
		),
		'polylang'        => array(
			'name'    => 'Polylang',
			'cat'     => 'necessary',
			'cookies' => 'pll_language',
			'plugins' => array( 'polylang/polylang.php' ),
			'desc'    => 'Zapamatování zvoleného jazyka.',
		),
		'wpml'            => array(
			'name'    => 'WPML',
			'cat'     => 'necessary',
			'cookies' => 'wp-wpml_current_language',
			'plugins' => array( 'sitepress-multilingual-cms/sitepress.php' ),
			'desc'    => 'Zapamatování zvoleného jazyka.',
		),
		'wordfence'       => array(
			'name'    => 'Wordfence',
			'cat'     => 'necessary',
			'cookies' => 'wfwaf-authcookie-*',
			'plugins' => array( 'wordfence/wordfence.php' ),
			'desc'    => 'Bezpečnostní firewall (jen pro přihlášené).',
		),
		'recaptcha'       => array(
			'name'    => 'Google reCAPTCHA',
			'cat'     => 'necessary',
			'cookies' => '_GRECAPTCHA',
			'html'    => array( 'google.com/recaptcha', 'gstatic.com/recaptcha', 'recaptcha.net/recaptcha' ),
			'desc'    => 'Ochrana formulářů proti spamu.',
		),
		'google-analytics' => array(
			'name'     => 'Google Analytics',
			'cat'      => 'statistics',
			'cookies'  => '_ga, _ga_*, _gid, _gat*',
			'patterns' => "googletagmanager.com/gtag/js\ngoogle-analytics.com/analytics.js\ngoogle-analytics.com/ga.js\ngtag('config'\ngtag(\"config\"",
			'plugins'  => array( 'google-site-kit/google-site-kit.php', 'google-analytics-for-wordpress/googleanalytics.php', 'ga-google-analytics/ga-google-analytics.php', 'google-analytics-dashboard-for-wp/gadwp.php', 'woocommerce-google-analytics-integration/woocommerce-google-analytics-integration.php' ),
			'desc'     => 'Měření návštěvnosti.',
		),
		'gtm'             => array(
			'name'     => 'Google Tag Manager',
			'cat'      => 'statistics',
			'cookies'  => '',
			'patterns' => "googletagmanager.com/gtm.js\ngoogletagmanager.com/ns.html",
			'plugins'  => array( 'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' ),
			'desc'     => 'Správce měřicích a marketingových kódů.',
		),
		'clarity'         => array(
			'name'     => 'Microsoft Clarity',
			'cat'      => 'statistics',
			'cookies'  => '_clck, _clsk, CLID, MUID',
			'patterns' => 'clarity.ms',
			'plugins'  => array( 'microsoft-clarity/clarity.php' ),
			'desc'     => 'Analýza chování návštěvníků.',
		),
		'hotjar'          => array(
			'name'     => 'Hotjar',
			'cat'      => 'statistics',
			'cookies'  => '_hjSession_*, _hjSessionUser_*, _hjAbsoluteSessionInProgress',
			'patterns' => 'static.hotjar.com',
			'plugins'  => array( 'hotjar/hotjar.php' ),
			'desc'     => 'Analýza chování návštěvníků.',
		),
		'matomo'          => array(
			'name'     => 'Matomo',
			'cat'      => 'statistics',
			'cookies'  => '_pk_id.*, _pk_ses.*',
			'patterns' => "matomo.js\npiwik.js",
			'plugins'  => array( 'matomo/matomo.php', 'wp-piwik/wp-piwik.php' ),
			'desc'     => 'Měření návštěvnosti.',
		),
		'jetpack-stats'   => array(
			'name'     => 'Jetpack Statistiky',
			'cat'      => 'statistics',
			'cookies'  => 'tk_ai, tk_qs',
			'patterns' => 'stats.wp.com',
			'desc'     => 'Měření návštěvnosti (WordPress.com).',
		),
		'smartsupp'       => array(
			'name'     => 'Smartsupp',
			'cat'      => 'statistics',
			'cookies'  => 'ssupp.vid, ssupp.visits, ssupp.chatid',
			'patterns' => 'smartsuppchat.com',
			'plugins'  => array( 'smartsupp-live-chat/smartsupp.php' ),
			'desc'     => 'Online chat.',
		),
		'tawk'            => array(
			'name'     => 'Tawk.to',
			'cat'      => 'statistics',
			'cookies'  => 'TawkConnectionTime, twk_*, __tawkuuid',
			'patterns' => 'embed.tawk.to',
			'plugins'  => array( 'tawkto-live-chat/tawkto.php' ),
			'desc'     => 'Online chat.',
		),
		'facebook-pixel'  => array(
			'name'     => 'Facebook Pixel',
			'cat'      => 'marketing',
			'cookies'  => '_fbp, _fbc, fr',
			'patterns' => "connect.facebook.net\nfbq('init'\nfbq(\"init\"",
			'plugins'  => array( 'official-facebook-pixel/facebook-for-wordpress.php', 'pixelyoursite/facebook-pixel-master.php' ),
			'desc'     => 'Měření a cílení reklam na Facebooku a Instagramu.',
		),
		'facebook'        => array(
			'name'     => 'Facebook (vložený obsah)',
			'cat'      => 'marketing',
			'cookies'  => 'fr, datr, sb',
			'patterns' => 'facebook.com/plugins/',
			'desc'     => 'Vložené příspěvky, videa a tlačítka Facebooku.',
		),
		'youtube'         => array(
			'name'     => 'YouTube',
			'cat'      => 'marketing',
			'cookies'  => 'YSC, VISITOR_INFO1_LIVE, VISITOR_PRIVACY_METADATA',
			'patterns' => "youtube.com/embed\nyoutube-nocookie.com/embed\nyoutube.com/iframe_api",
			'db'       => array( 'youtube.com/', 'youtu.be/', 'youtube-nocookie.com' ),
			'desc'     => 'Vložená videa.',
		),
		'vimeo'           => array(
			'name'     => 'Vimeo',
			'cat'      => 'marketing',
			'cookies'  => 'vuid',
			'patterns' => 'player.vimeo.com',
			'db'       => array( 'vimeo.com/' ),
			'desc'     => 'Vložená videa.',
		),
		'google-maps'     => array(
			'name'     => 'Google Mapy',
			'cat'      => 'marketing',
			'cookies'  => 'NID',
			'patterns' => "google.com/maps/embed\nmaps.google.\nmaps.googleapis.com",
			'db'       => array( 'google.com/maps', 'maps.google.' ),
			'desc'     => 'Vložené mapy.',
		),
		'mapy-com'        => array(
			'name'     => 'Mapy.com (Seznam)',
			'cat'      => 'marketing',
			'cookies'  => '',
			'patterns' => "frame.mapy.cz\nmapy.cz/s/\nmapy.com/s/\nmapy.cz/?\nmapy.com/?\napi.mapy.cz",
			'db'       => array( 'mapy.cz/', 'mapy.com/' ),
			'desc'     => 'Vložené mapy. Seznam může ukládat vlastní cookies.',
		),
		'seznam'          => array(
			'name'     => 'Seznam Sklik (retargeting a konverze)',
			'cat'      => 'marketing',
			'cookies'  => 'sid, KADUSERCOOKIE',
			'patterns' => "c.seznam.cz/js/rc.js\nc.imedia.cz",
			'desc'     => 'Měření a cílení reklam Sklik.',
		),
		'google-ads'      => array(
			'name'     => 'Google Ads',
			'cat'      => 'marketing',
			'cookies'  => '_gcl_au, IDE, test_cookie',
			'patterns' => "googleadservices.com\ngoogletagservices.com\ngtag/js?id=AW-\npagead2.googlesyndication.com\ngtag('config','AW-\n'send_to':'AW-",
			'desc'     => 'Měření a cílení reklam Google.',
		),
		'linkedin'        => array(
			'name'     => 'LinkedIn Insight',
			'cat'      => 'marketing',
			'cookies'  => 'li_sugr, bcookie, lidc, UserMatchHistory',
			'patterns' => 'snap.licdn.com',
			'desc'     => 'Měření a cílení reklam LinkedIn.',
		),
		'tiktok'          => array(
			'name'     => 'TikTok Pixel',
			'cat'      => 'marketing',
			'cookies'  => '_ttp, _tt_enable_cookie',
			'patterns' => 'analytics.tiktok.com',
			'desc'     => 'Měření a cílení reklam TikTok.',
		),
		'twitter'         => array(
			'name'     => 'X (Twitter)',
			'cat'      => 'marketing',
			'cookies'  => 'guest_id, personalization_id',
			'patterns' => "platform.twitter.com\nplatform.x.com",
			'desc'     => 'Vložené příspěvky.',
		),
		'instagram'       => array(
			'name'     => 'Instagram',
			'cat'      => 'marketing',
			'cookies'  => '',
			'patterns' => "instagram.com/embed\ninstagram.com/p/",
			'desc'     => 'Vložené příspěvky.',
		),
		'spotify'         => array(
			'name'     => 'Spotify',
			'cat'      => 'marketing',
			'cookies'  => 'sp_t, sp_landing',
			'patterns' => 'open.spotify.com/embed',
			'desc'     => 'Vložený přehrávač.',
		),
	);
}

/** Hledá patterns/html v textu. */
function clm_scan_text( $text, $sig ) {
	$needles = array();
	if ( ! empty( $sig['patterns'] ) ) {
		$needles = preg_split( '/\R+/', $sig['patterns'] );
	}
	if ( ! empty( $sig['html'] ) ) {
		$needles = array_merge( $needles, $sig['html'] );
	}
	$norm = null;
	foreach ( $needles as $n ) {
		if ( '' === $n ) {
			continue;
		}
		if ( false !== stripos( $text, $n ) ) {
			return true;
		}
		if ( null === $norm ) {
			$norm = clm_norm( $text ); // stejná normalizace jako při blokování
		}
		if ( false !== stripos( $norm, clm_norm( $n ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Prohledá web a aktualizuje seznam služeb.
 *
 * @param bool $http Načíst i HTML stránek (pomalejší – na pozadí nebo tlačítkem).
 * @return array Nalezené id služeb.
 */
function clm_scan( $http = true ) {
	global $wpdb;
	if ( get_transient( 'clm_scan_lock' ) ) {
		return array(); // prohledávání už běží
	}
	set_transient( 'clm_scan_lock', 1, 120 );
	$sigs   = clm_signatures();
	$found  = array( 'wordpress' => 1, 'clm' => 1 );
	$active = (array) get_option( 'active_plugins', array() );

	// 1) Aktivní pluginy.
	foreach ( $sigs as $id => $s ) {
		if ( ! empty( $s['plugins'] ) && array_intersect( $s['plugins'], $active ) ) {
			$found[ $id ] = 1;
		}
	}

	// 2) Obsah stránek, příspěvků, šablon a widgetů v databázi.
	foreach ( $sigs as $id => $s ) {
		if ( isset( $found[ $id ] ) ) {
			continue;
		}
		$needles = isset( $s['db'] ) ? $s['db'] : ( ! empty( $s['patterns'] ) ? preg_split( '/\R+/', $s['patterns'] ) : array() );
		foreach ( $needles as $n ) {
			if ( '' === $n ) {
				continue;
			}
			$like  = '%' . $wpdb->esc_like( $n ) . '%';
			$embed = in_array( $id, clm_embed_ids(), true );
			if ( $embed ) {
				// Vložený obsah: počítá se jen iframe/embed blok, ne obyčejný odkaz v textu.
				$hit   = false;
				$texts = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision','attachment','nav_menu_item') AND post_content LIKE %s LIMIT 20",
						$like
					)
				);
				foreach ( $texts as $t ) {
					if ( preg_match( '#<iframe\b[^>]*' . preg_quote( $n, '#' ) . '|<!--\s*wp:(?:core-)?embed\b[^>]*' . preg_quote( $n, '#' ) . '#i', $t ) ) {
						$hit = true;
						break;
					}
				}
			} else {
				$hit = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision','attachment','nav_menu_item') AND post_content LIKE %s LIMIT 1",
						$like
					)
				);
			}
			if ( ! $hit && ! $embed ) {
				$hit = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT option_id FROM {$wpdb->options} WHERE ( option_name LIKE 'widget\\_%' OR option_name LIKE 'theme\\_mods\\_%' ) AND option_value LIKE %s LIMIT 1",
						$like
					)
				);
			}
			if ( $hit ) {
				$found[ $id ] = 1;
				break;
			}
		}
	}

	// 3) HTML úvodní stránky a několika dalších stránek.
	$fetched = 0;
	if ( $http ) {
		$urls = array( home_url( '/' ) );
		$ids  = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 6,
				'orderby'        => 'modified',
				'fields'         => 'ids',
				'has_password'   => false,
			)
		);
		foreach ( $ids as $pid ) {
			$urls[] = get_permalink( $pid );
		}
		foreach ( array_unique( array_slice( $urls, 0, 6 ) ) as $u ) {
			// Jen vlastní web (stejný host jako home_url).
			if ( strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
				continue;
			}
			$r = wp_remote_get(
				add_query_arg( 'clm_scan', time(), $u ),
				array(
					'timeout'             => 8,
					'redirection'         => 0, // přesměrování nenásledovat (jen vlastní stránky)
					'limit_response_size' => 2 * MB_IN_BYTES,
					'sslverify'           => apply_filters( 'https_local_ssl_verify', true ),
					'user-agent'          => 'Mozilla/5.0 (CookiesListaMoja scan)',
				)
			);
			if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
				continue;
			}
			$fetched++;
			$html = (string) wp_remote_retrieve_body( $r );
			// Adresy iframů a skriptů – pro služby vloženého obsahu se hledá jen v nich.
			preg_match_all( '#<(?:iframe|script)\b[^>]*?\s(?:data-(?:lazy-)?)?src\s*=\s*["\']?([^"\'\s>]+)#i', $html, $mm );
			$srcs = html_entity_decode( implode( "\n", $mm[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			foreach ( $sigs as $id => $s ) {
				if ( isset( $found[ $id ] ) ) {
					continue;
				}
				if ( clm_scan_text( in_array( $id, clm_embed_ids(), true ) ? $srcs : $html, $s ) ) {
					$found[ $id ] = 1;
				}
			}
		}
	}

	// 4) Sloučit se stávajícím seznamem – ruční úpravy a vlastní služby zůstanou.
	$o        = get_option( 'clm' );
	$o        = ( is_array( $o ) ? $o : array() ) + clm_defaults();
	$services = array();
	foreach ( (array) $o['services'] as $s ) {
		if ( ! empty( $s['id'] ) ) {
			$services[ $s['id'] ] = $s;
		}
	}
	$new = 0;
	$ignored = isset( $o['ignored'] ) ? (array) $o['ignored'] : array();
	foreach ( array_keys( $found ) as $id ) {
		if ( isset( $services[ $id ] ) || in_array( $id, $ignored, true ) ) {
			continue;
		}
		$s               = $sigs[ $id ];
		$services[ $id ] = array(
			'id'       => $id,
			'name'     => $s['name'],
			'cat'      => $s['cat'],
			'cookies'  => isset( $s['cookies'] ) ? $s['cookies'] : '',
			'patterns' => isset( $s['patterns'] ) ? $s['patterns'] : '',
			'desc'     => isset( $s['desc'] ) ? $s['desc'] : '',
			'auto'     => 1,
		);
		$new++;
	}
	$o['services'] = array_values( $services );
	$o['scanned']  = time();
	$o['scan_info'] = array(
		'time'    => time(),
		'found'   => array_keys( $found ),
		'new'     => $new,
		'pages'   => $fetched,
		'http'    => $http ? 1 : 0,
	);
	update_option( 'clm', $o );
	clm_opt( null, true );
	delete_transient( 'clm_scan_lock' );
	return array_keys( $found );
}
