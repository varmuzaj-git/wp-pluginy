<?php
/**
 * Bezpečnost – administrace (načítá se jen v administraci).
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------
 * .htaccess – blokování už na úrovni serveru (bez spuštění PHP)
 * ------------------------------------------------------------------ */

function bzp_htaccess_path() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	return get_home_path() . '.htaccess';
}

function bzp_htaccess_rules() {
	// Na multisite sdílí .htaccess více webů – tam se nepoužívá.
	if ( ! bzp_opt( 'htaccess' ) || is_multisite() ) {
		return '';
	}
	$path = wp_normalize_path( BZP_FILE );
	if ( preg_match( '/["\x00-\x1f\x7f]/', $path ) ) {
		return ''; // neobvyklá cesta – do .htaccess raději nic nezapisovat
	}
	// Pravidla platí jen dokud existuje soubor pluginu – po smazání/přejmenování
	// složky pluginu přes FTP se wp-login.php automaticky zase zpřístupní.
	$cond = 'RewriteCond "' . $path . '" -f';
	// Cesta k WordPressu vůči složce s .htaccess (obvykle prázdná). Pravidla jsou ukotvená,
	// takže nezasáhnou jiné WordPressy v podsložkách.
	$home_p = trim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
	$site_p = trim( (string) wp_parse_url( get_option( 'siteurl' ), PHP_URL_PATH ), '/' );
	if ( '' === $home_p ) {
		$rel = $site_p;
	} elseif ( $site_p === $home_p || 0 === strpos( $site_p, $home_p . '/' ) ) {
		$rel = substr( $site_p, strlen( $home_p ) );
	} else {
		$rel = '';
	}
	$rel    = trim( $rel, '/' );
	$prefix = '^' . ( '' === $rel ? '' : preg_quote( $rel, '#' ) . '/' );
	$rules  = array();
	if ( bzp_opt( 'xmlrpc' ) ) {
		$rules[] = $cond;
		$rules[] = 'RewriteRule ' . $prefix . 'xmlrpc\.php$ - [R=404,L]';
	}
	if ( '' !== bzp_slug() ) {
		$rules[] = $cond;
		$rules[] = 'RewriteRule ' . $prefix . 'wp-login\.php$ - [R=404,L]';
	}
	$out = array();
	if ( $rules ) {
		$out[] = "<IfModule mod_rewrite.c>\nRewriteEngine On\n" . implode( "\n", $rules ) . "\n</IfModule>";
	}
	// Hlavičky i pro stránky z cache a statické soubory (PHP se u nich nespouští).
	if ( bzp_opt( 'headers' ) ) {
		$out[] = "<IfModule mod_headers.c>\nHeader always set X-Content-Type-Options \"nosniff\"\nHeader always set X-Frame-Options \"SAMEORIGIN\"\nHeader always set Referrer-Policy \"strict-origin-when-cross-origin\"\n</IfModule>";
	}
	return implode( "\n", $out );
}

/** Odstraní náš blok (jen přesně ohraničený samostatnými řádky se značkami). */
function bzp_htaccess_strip( $content ) {
	$new = preg_replace( '/^# BEGIN Bezpecnost[ \t]*\R(?:(?!# (?:BEGIN|END) Bezpecnost).*\R)*?# END Bezpecnost[ \t]*(?:\R|\z)(?:[ \t]*\R)*/m', '', $content );
	// Osiřelé nebo zdvojené značky (poškozený blok) – odstranit jen samotné řádky se značkou.
	return null === $new ? null : preg_replace( '/^# (?:BEGIN|END) Bezpecnost[ \t]*(?:\R|\z)/m', '', $new );
}

/** Zapíše (nebo s '' odstraní) náš blok na začátek .htaccess. Celé čtení i zápis pod jedním zámkem. */
function bzp_htaccess_write( $rules ) {
	$file = bzp_htaccess_path();
	if ( ! file_exists( $file ) ) {
		if ( '' === $rules ) {
			return true;
		}
		if ( ! is_writable( dirname( $file ) ) ) {
			return false;
		}
	} elseif ( ! is_readable( $file ) || ! is_writable( $file ) ) {
		return false;
	}
	$fh = @fopen( $file, 'c+' );
	if ( ! $fh ) {
		return false;
	}
	$ok = false;
	if ( flock( $fh, LOCK_EX ) ) {
		$content = stream_get_contents( $fh );
		$new     = false === $content ? null : bzp_htaccess_strip( $content );
		if ( null !== $new ) {
			if ( '' !== $rules ) {
				$new = "# BEGIN Bezpecnost\n" . $rules . "\n# END Bezpecnost\n\n" . $new;
			}
			if ( $new === $content ) {
				$ok = true;
			} elseif ( rewind( $fh ) && ftruncate( $fh, 0 ) ) {
				$ok = strlen( $new ) === fwrite( $fh, $new ) && fflush( $fh );
				if ( ! $ok ) { // pokus vrátit původní obsah
					rewind( $fh );
					ftruncate( $fh, 0 );
					fwrite( $fh, $content );
				}
			}
		}
		flock( $fh, LOCK_UN );
	}
	fclose( $fh );
	return $ok;
}

/** Obsahuje .htaccess právě naše pravidla? */
function bzp_htaccess_has( $rules ) {
	$c = @file_get_contents( bzp_htaccess_path() );
	if ( false === $c ) {
		return '' === $rules;
	}
	return '' === $rules ? false === strpos( $c, '# BEGIN Bezpecnost' ) : false !== strpos( $c, "# BEGIN Bezpecnost\n" . $rules . "\n# END Bezpecnost" );
}

/** Nouzový režim: odstranit blokaci wp-login.php bez jakýchkoli HTTP kontrol. */
function bzp_htaccess_emergency() {
	$rules = bzp_htaccess_rules(); // bzp_slug() je v nouzovém režimu prázdný → bez wp-login.php
	if ( bzp_htaccess_has( $rules ) ) {
		return;
	}
	$done = bzp_htaccess_write( $rules );
	update_option( 'bezpecnost_ht', array( 'hash' => $done ? md5( $rules ) : '', 'ok' => $done, 'active' => '' !== $rules, 'xmlrpc' => '', 'login' => '', 'time' => time() ), true );
}

/** Ověří, kdo požadavek zablokoval: server (.htaccess), PHP (plugin), nebo nikdo. */
function bzp_probe( $url ) {
	$r = wp_remote_get(
		$url,
		array(
			'timeout'     => 6,
			'redirection' => 0,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', true ),
			'user-agent'  => 'Bezpecnost-selftest',
		)
	);
	if ( is_wp_error( $r ) ) {
		return 'neověřeno';
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( false !== strpos( (string) wp_remote_retrieve_body( $r ), '<!--bzp-->' ) ) {
		return 'php';
	}
	if ( in_array( $code, array( 403, 404 ), true ) ) {
		return 'server';
	}
	if ( 302 === $code && untrailingslashit( (string) wp_remote_retrieve_header( $r, 'location' ) ) === untrailingslashit( home_url( '/' ) ) ) {
		return 'php'; // režim „přesměrovat na úvodní stránku“
	}
	return 'otevřeno (' . $code . ')';
}

/** Synchronizace .htaccess s nastavením (zápis jen při změně). */
function bzp_htaccess_sync( $force = false ) {
	$rules = bzp_htaccess_rules();
	$hash  = md5( $rules );
	$cur   = get_option( 'bezpecnost_ht' );
	if ( ! $force && is_array( $cur ) ) {
		if ( ! empty( $cur['ok'] ) && isset( $cur['hash'] ) && $cur['hash'] === $hash && bzp_htaccess_has( $rules ) ) {
			return; // vše sedí
		}
		if ( empty( $cur['ok'] ) && isset( $cur['hash'] ) && $cur['hash'] === $hash && time() - (int) $cur['time'] < HOUR_IN_SECONDS ) {
			return; // nedávno selhalo – zkusit znovu nejdřív za hodinu
		}
	}
	$ok = bzp_htaccess_write( $rules ) && bzp_htaccess_has( $rules );
	// Pojistka: pokud by server pravidla nesnesl (chyba 500), blok hned zase odstranit.
	if ( $ok && '' !== $rules ) {
		$r = wp_remote_get( home_url( '/?bzp_check=' . time() ), array( 'timeout' => 8, 'sslverify' => apply_filters( 'https_local_ssl_verify', true ), 'redirection' => 0 ) );
		if ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) >= 500 ) {
			bzp_htaccess_write( '' );
			$ok = false;
		}
	}
	$res = array(
		'hash'   => $hash,
		'ok'     => $ok,
		'active' => '' !== $rules,
		'xmlrpc' => '',
		'login'  => '',
		'time'   => time(),
	);
	if ( bzp_opt( 'xmlrpc' ) ) {
		$res['xmlrpc'] = bzp_probe( site_url( 'xmlrpc.php' ) );
	}
	if ( '' !== bzp_slug() ) {
		// Záměrně bez site_url() – ten je filtrovaný na skrytou adresu.
		$res['login'] = bzp_probe( untrailingslashit( (string) get_option( 'siteurl' ) ) . '/wp-login.php' );
	}
	update_option( 'bezpecnost_ht', $res, true );
}

add_action(
	'admin_init',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			bzp_htaccess_sync();
		}
	}
);

/* ------------------------------------------------------------------
 * Menu, odkazy, upozornění
 * ------------------------------------------------------------------ */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Bezpečnost', 'Bezpečnost', 'manage_options', 'bezpecnost', 'bzp_page' );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( BZP_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=bezpecnost' ) ) . '">Nastavení</a>' );
		return $links;
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'settings_page_bezpecnost' ), true ) ) {
			return;
		}
		$conflict = bzp_conflict();
		$slug     = (string) bzp_opt( 'login_slug' );
		if ( $conflict && '' !== $slug ) {
			echo '<div class="notice notice-warning"><p><strong>Bezpečnost:</strong> plugin <strong>' . esc_html( $conflict ) . '</strong> je stále aktivní. Po jeho deaktivaci Bezpečnost automaticky převezme přihlašovací adresu <code>' . esc_html( home_url( '/' . $slug . '/' ) ) . '</code>.</p></div>';
		} elseif ( $conflict ) {
			echo '<div class="notice notice-warning"><p><strong>Bezpečnost:</strong> plugin <strong>' . esc_html( $conflict ) . '</strong> také mění přihlašovací adresu. Používejte jen jeden z nich.</p></div>';
		} elseif ( '' !== $slug && ! bzp_slug_ok( $slug, true ) ) {
			echo '<div class="notice notice-warning"><p><strong>Bezpečnost:</strong> přihlašovací adresa <code>/' . esc_html( $slug ) . '/</code> je snadno uhodnutelná. <a href="' . esc_url( admin_url( 'options-general.php?page=bezpecnost' ) ) . '">Doporučuji ji změnit</a>.</p></div>';
		} elseif ( '' === $slug && ! is_multisite() ) {
			echo '<div class="notice notice-info"><p><strong>Bezpečnost:</strong> přihlašovací stránka není skrytá. <a href="' . esc_url( admin_url( 'options-general.php?page=bezpecnost' ) ) . '">Nastavte vlastní adresu</a>.</p></div>';
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( bzp_opt( 'xmlrpc' ) && array_intersect( array( 'disable-xml-rpc/disable-xml-rpc.php', 'disable-xml-rpc-api/disable-xml-rpc-api.php' ), $active ) ) {
			echo '<div class="notice notice-info"><p><strong>Bezpečnost:</strong> XML-RPC už blokuje tento plugin, <strong>Disable XML-RPC</strong> můžete deaktivovat.</p></div>';
		}
		if ( bzp_opt( 'limit' ) && in_array( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php', $active, true ) ) {
			echo '<div class="notice notice-info"><p><strong>Bezpečnost:</strong> pokusy o přihlášení už omezuje tento plugin, <strong>Limit Login Attempts Reloaded</strong> můžete deaktivovat.</p></div>';
		}
	}
);

/* ------------------------------------------------------------------
 * Uložení nastavení
 * ------------------------------------------------------------------ */

function bzp_reserved_slugs() {
	return array( 'wp-admin', 'wp-login', 'wp-login-php', 'login', 'admin', 'dashboard', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'index-php', 'xmlrpc', 'xmlrpc-php', 'wp-signup', 'wp-activate', 'wp-cron', 'wp-register', 'embed', 'comments', 'search', 'author', 'page', 'category', 'tag' );
}

add_action(
	'admin_post_bzp_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nedostatečná oprávnění.', 403 );
		}
		check_admin_referer( 'bzp_save' );

		$in  = wp_unslash( $_POST );
		$old = bzp_opt();
		$o   = $old;
		$msg = 'saved';

		$slug = sanitize_title( isset( $in['login_slug'] ) ? (string) $in['login_slug'] : '' );
		if ( '' !== $slug && $slug !== $old['login_slug'] ) {
			if ( ! bzp_slug_ok( $slug, true ) ) {
				$msg  = 'slug_bad';
				$slug = $old['login_slug'];
			} elseif ( get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) ) || term_exists( $slug ) ) {
				$msg  = 'slug_taken';
				$slug = $old['login_slug'];
			}
		}
		$o['login_slug']  = $slug;
		$o['login_block'] = ( isset( $in['login_block'] ) && 'home' === $in['login_block'] ) ? 'home' : '404';

		foreach ( array( 'xmlrpc', 'htaccess', 'limit', 'enum', 'login_errors', 'file_edit', 'version', 'headers' ) as $k ) {
			$o[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$o['limit_attempts'] = min( 50, max( 2, (int) ( isset( $in['limit_attempts'] ) ? $in['limit_attempts'] : 5 ) ) );
		$o['limit_minutes']  = min( 1440, max( 1, (int) ( isset( $in['limit_minutes'] ) ? $in['limit_minutes'] : 20 ) ) );
		$o['limit_long_after']  = min( 20, max( 1, (int) ( isset( $in['limit_long_after'] ) ? $in['limit_long_after'] : 4 ) ) );
		$o['limit_long_hours']  = min( 720, max( 1, (int) ( isset( $in['limit_long_hours'] ) ? $in['limit_long_hours'] : 24 ) ) );
		$o['limit_reset_hours'] = min( 168, max( 1, (int) ( isset( $in['limit_reset_hours'] ) ? $in['limit_reset_hours'] : 12 ) ) );

		$wl = array();
		foreach ( preg_split( '/[\s,]+/', isset( $in['whitelist'] ) ? (string) $in['whitelist'] : '' ) as $e ) {
			if ( bzp_parse_cidr( $e ) ) {
				$wl[] = $e;
			}
		}
		$o['whitelist'] = implode( "\n", array_unique( $wl ) );

		update_option( 'bezpecnost', $o );
		bzp_opt( null, true );
		bzp_htaccess_sync( true );

		$args = array( 'page' => 'bezpecnost', 'bzp' => $msg );
		if ( $slug !== $old['login_slug'] ) {
			$args['bzp_new'] = '1';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}
);

add_action(
	'admin_post_bzp_unlock',
	function () {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nedostatečná oprávnění.', 403 );
		}
		check_admin_referer( 'bzp_unlock' );
		// Nová „generace“ klíčů zneplatní všechny blokace i v object cache; staré záznamy smazat z databáze.
		update_option( 'bezpecnost_gen', (int) get_option( 'bezpecnost_gen', 0 ) + 1, true );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_bzp\\_%' OR option_name LIKE '\\_transient\\_timeout\\_bzp\\_%'" );
		wp_safe_redirect( admin_url( 'options-general.php?page=bezpecnost&bzp=unlocked' ) );
		exit;
	}
);

/* ------------------------------------------------------------------
 * Stránka nastavení
 * ------------------------------------------------------------------ */

function bzp_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o        = bzp_opt();
	$ht       = get_option( 'bezpecnost_ht' );
	$conflict = bzp_conflict();
	$msg      = isset( $_GET['bzp'] ) ? sanitize_key( $_GET['bzp'] ) : '';
	$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$log      = get_option( 'bezpecnost_log', array() );

	$msgs = array(
		'saved'      => array( 'success', 'Nastavení uloženo.' ),
		'unlocked'   => array( 'success', 'Všechny zablokované IP adresy byly odblokovány.' ),
		'slug_bad'   => array( 'error', 'Přihlašovací adresu nelze použít: 4–60 znaků, jen malá písmena bez diakritiky, číslice a pomlčky, a nesmí to být vyhrazené slovo (login, admin, wp-admin…). Ostatní nastavení bylo uloženo.' ),
		'slug_taken' => array( 'error', 'Přihlašovací adresa koliduje s existující stránkou, příspěvkem nebo rubrikou. Ostatní nastavení bylo uloženo.' ),
	);
	$on = function ( $k ) use ( $o ) {
		checked( ! empty( $o[ $k ] ) );
	};
	?>
	<div class="wrap">
		<h1>Bezpečnost</h1>
		<?php if ( isset( $msgs[ $msg ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $msgs[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $msgs[ $msg ][1] ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['bzp_new'] ) && '' !== bzp_slug() ) : ?>
			<div class="notice notice-warning"><p><strong>Nová přihlašovací adresa:</strong> <a href="<?php echo esc_url( bzp_login_url() ); ?>"><?php echo esc_html( bzp_login_url() ); ?></a> – uložte si ji do záložek.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bzp_save">
			<?php wp_nonce_field( 'bzp_save' ); ?>

			<h2>Přihlašovací stránka</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bzp-slug">Vlastní adresa přihlášení</label></th>
					<td>
						<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
						<input type="text" id="bzp-slug" name="login_slug" value="<?php echo esc_attr( $o['login_slug'] ); ?>" class="regular-text" style="max-width:16em" placeholder="např. vstup-123">
						<code>/</code>
						<p class="description">
							<?php if ( '' !== bzp_slug() ) : ?>
								Přihlášení: <a href="<?php echo esc_url( bzp_login_url() ); ?>"><strong><?php echo esc_html( bzp_login_url() ); ?></strong></a>. Adresy <code>wp-login.php</code> a <code>wp-admin</code> jsou pro nepřihlášené skryté.
							<?php elseif ( $conflict && '' !== $o['login_slug'] ) : ?>
								Čeká na deaktivaci pluginu <?php echo esc_html( $conflict ); ?>.
							<?php else : ?>
								Prázdné = výchozí <code>wp-login.php</code> (nedoporučeno).
							<?php endif; ?>
							<br>Při zapomenutí adresy: do <code>wp-config.php</code> přidejte <code>define( 'BEZPECNOST_NOUZOVY_REZIM', true );</code> nebo přes FTP přejmenujte složku pluginu.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Pokus o přístup na wp-login.php / wp-admin</th>
					<td>
						<label><input type="radio" name="login_block" value="404" <?php checked( '404' !== $o['login_block'] ); ?>> Zobrazit chybu 404 (doporučeno, nejmenší zátěž)</label><br>
						<label><input type="radio" name="login_block" value="home" <?php checked( 'home' === $o['login_block'] ); ?>> Přesměrovat na úvodní stránku</label>
					</td>
				</tr>
			</table>

			<h2>XML-RPC a blokování na serveru</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">XML-RPC</th>
					<td><label><input type="checkbox" name="xmlrpc" value="1" <?php $on( 'xmlrpc' ); ?>> Úplně vypnout <code>xmlrpc.php</code> (ochrana proti útokům hrubou silou a pingback spamu)</label>
					<p class="description">Nechte zapnuté, pokud nepoužíváte Jetpack nebo mobilní aplikaci WordPress.</p></td>
				</tr>
				<tr>
					<th scope="row">Blokovat přímo na serveru</th>
					<td><label><input type="checkbox" name="htaccess" value="1" <?php $on( 'htaccess' ); ?>> Blokovat <code>xmlrpc.php</code> a <code>wp-login.php</code> už v souboru <code>.htaccess</code></label>
					<p class="description">Boti pak vůbec nespustí WordPress, takže nezatěžují paměť ani procesor.</p>
					<?php
					if ( is_array( $ht ) && ! empty( $ht['active'] ) ) {
						if ( empty( $ht['ok'] ) ) {
							echo '<p><strong style="color:#b32d2e">Soubor .htaccess nelze zapsat</strong> – blokuje se jen v PHP.</p>';
						} else {
							$label = array(
								'server' => '<span style="color:#008a20">blokuje server</span>',
								'php'    => '<span style="color:#996800">blokuje PHP (server ne)</span>',
							);
							echo '<p>Stav: ';
							foreach ( array( 'xmlrpc' => 'xmlrpc.php', 'login' => 'wp-login.php' ) as $k => $n ) {
								if ( '' !== $ht[ $k ] ) {
									echo esc_html( $n ) . ' – ' . ( isset( $label[ $ht[ $k ] ] ) ? $label[ $ht[ $k ] ] : esc_html( $ht[ $k ] ) ) . '&nbsp;&nbsp; ';
								}
							}
							echo '</p>';
						}
					}
					?>
					</td>
				</tr>
			</table>

			<h2>Omezení pokusů o přihlášení</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Zapnout</th>
					<td><label><input type="checkbox" name="limit" value="1" <?php $on( 'limit' ); ?>> Po opakovaných neúspěšných pokusech dočasně zablokovat IP adresu</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="bzp-att">Počet pokusů</label></th>
					<td><input type="number" id="bzp-att" name="limit_attempts" min="2" max="50" value="<?php echo (int) $o['limit_attempts']; ?>" class="small-text"> neúspěšných pokusů → blokace na
						<input type="number" name="limit_minutes" min="1" max="1440" value="<?php echo (int) $o['limit_minutes']; ?>" class="small-text"> minut
</td>
				</tr>
				<tr>
					<th scope="row">Dlouhá blokace</th>
					<td>Po <input type="number" name="limit_long_after" min="1" max="20" value="<?php echo (int) $o['limit_long_after']; ?>" class="small-text"> blokacích zablokovat na
						<input type="number" name="limit_long_hours" min="1" max="720" value="<?php echo (int) $o['limit_long_hours']; ?>" class="small-text"> hodin</td>
				</tr>
				<tr>
					<th scope="row">Vynulování pokusů</th>
					<td>Neúspěšné pokusy a blokace zapomenout po <input type="number" name="limit_reset_hours" min="1" max="168" value="<?php echo (int) $o['limit_reset_hours']; ?>" class="small-text"> hodinách</td>
				</tr>
				<tr>
					<th scope="row"><label for="bzp-wl">Povolené IP adresy</label></th>
					<td><textarea id="bzp-wl" name="whitelist" rows="3" class="large-text code" placeholder="jedna adresa nebo rozsah (CIDR) na řádek"><?php echo esc_textarea( $o['whitelist'] ); ?></textarea>
						<p class="description">Tyto adresy se nikdy nezablokují. Vaše současná IP: <code><?php echo esc_html( $ip ); ?></code><?php echo bzp_whitelisted( $ip ) ? ' (je na seznamu)' : ''; ?></p></td>
				</tr>
			</table>

			<h2>Další ochrana</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Uživatelská jména</th><td><label><input type="checkbox" name="enum" value="1" <?php $on( 'enum' ); ?>> Skrýt uživatelská jména (<code>?author=1</code>, REST API <code>/wp/v2/users</code>, sitemapa, oEmbed)</label></td></tr>
				<tr><th scope="row">Chybová hláška</th><td><label><input type="checkbox" name="login_errors" value="1" <?php $on( 'login_errors' ); ?>> Při chybném přihlášení neprozradit, jestli uživatel existuje</label></td></tr>
				<tr><th scope="row">Editor souborů</th><td><label><input type="checkbox" name="file_edit" value="1" <?php $on( 'file_edit' ); ?>> Vypnout úpravy kódu šablon a pluginů v administraci</label></td></tr>
				<tr><th scope="row">Verze WordPressu</th><td><label><input type="checkbox" name="version" value="1" <?php $on( 'version' ); ?>> Odstranit značku s verzí WordPressu (meta generator)</label></td></tr>
				<tr><th scope="row">HTTP hlavičky</th><td><label><input type="checkbox" name="headers" value="1" <?php $on( 'headers' ); ?>> Posílat bezpečnostní hlavičky (<code>X-Frame-Options</code>, <code>X-Content-Type-Options</code>, <code>Referrer-Policy</code>)</label></td></tr>
			</table>

			<?php submit_button( 'Uložit nastavení' ); ?>
		</form>

		<h2>Poslední blokace</h2>
		<?php if ( empty( $log ) || ! is_array( $log ) ) : ?>
			<p>Zatím žádné.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:720px">
				<thead><tr><th>Čas</th><th>IP adresa</th><th>Uživatel</th><th>Blokace</th></tr></thead>
				<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'j. n. Y H:i', (int) $row[0] ) ); ?></td>
						<td><code><?php echo esc_html( $row[1] ); ?></code></td>
						<td><?php echo esc_html( $row[2] ); ?></td>
						<td><?php echo (int) $row[3] >= 60 && 0 === (int) $row[3] % 60 ? (int) ( $row[3] / 60 ) . ' h' : (int) $row[3] . ' min'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em">
			<input type="hidden" name="action" value="bzp_unlock">
			<?php wp_nonce_field( 'bzp_unlock' ); ?>
			<?php submit_button( 'Odblokovat všechny IP adresy', 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
