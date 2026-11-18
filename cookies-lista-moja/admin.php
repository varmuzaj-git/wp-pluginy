<?php
/**
 * Cookies lišta moja – administrace (načítá se jen v administraci).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		$hook = add_options_page( 'Cookies lišta', 'Cookies lišta', 'manage_options', 'cookies-lista', 'clm_page' );
		add_action(
			'admin_print_scripts-' . $hook,
			function () {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".clm-color").wpColorPicker();});' );
			}
		);
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( CLM_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=cookies-lista' ) ) . '">Nastavení</a>' );
		return $links;
	}
);

/** Upozornění na jiné cookies lišty – dvě lišty najednou nedávají smysl. */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'settings_page_cookies-lista', 'dashboard' ), true ) ) {
			return;
		}
		$others = array(
			'complianz-gdpr/complianz-gpdr.php'                => 'Complianz',
			'complianz-gdpr-premium/complianz-gpdr-premium.php' => 'Complianz Premium',
			'wpconsent-cookies-banner-privacy-suite/wpconsent.php' => 'WPConsent',
			'cookie-law-info/cookie-law-info.php'              => 'CookieYes',
			'cookie-notice/cookie-notice.php'                  => 'Cookie Notice',
			'gdpr-cookie-compliance/moove-gdpr.php'            => 'GDPR Cookie Compliance',
			'real-cookie-banner/index.php'                     => 'Real Cookie Banner',
			'cookiebot/cookiebot.php'                          => 'Cookiebot',
			'borlabs-cookie/borlabs-cookie.php'                => 'Borlabs Cookie',
			'pressidium-cookie-consent/pressidium-cookie-consent.php' => 'Pressidium Cookie Consent',
		);
		$active = (array) get_option( 'active_plugins', array() );
		foreach ( $others as $file => $name ) {
			if ( in_array( $file, $active, true ) ) {
				echo '<div class="notice notice-warning"><p><strong>Cookies lišta moja:</strong> je aktivní také plugin <strong>' . esc_html( $name ) . '</strong>. Na webu by se zobrazily dvě lišty – deaktivujte jeden z nich.</p></div>';
			}
		}
	}
);

/* ------------------------------------------------------------------
 * Uložení, prohledání, obnovení souhlasů
 * ------------------------------------------------------------------ */

function clm_check( $action ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nedostatečná oprávnění.', 403 );
	}
	check_admin_referer( $action );
}

function clm_back( $msg ) {
	clm_purge_cache();
	wp_safe_redirect( admin_url( 'options-general.php?page=cookies-lista&clm=' . $msg ) );
	exit;
}

add_action(
	'admin_post_clm_save',
	function () {
		clm_check( 'clm_save' );
		$in = wp_unslash( $_POST );
		$o  = clm_opt();
		$d  = clm_defaults();

		$o['position'] = in_array( isset( $in['position'] ) ? $in['position'] : '', array( 'bar-bottom', 'bar-top', 'box-left', 'box-right', 'center' ), true ) ? $in['position'] : $d['position'];
		$o['reopen']   = in_array( isset( $in['reopen'] ) ? $in['reopen'] : '', array( 'left', 'right', 'none' ), true ) ? $in['reopen'] : $d['reopen'];
		$o['show']     = ( isset( $in['show'] ) && 'auto' === $in['show'] ) ? 'auto' : 'always';
		foreach ( array( 'bg', 'text', 'accent', 'accent_text' ) as $k ) {
			$c       = sanitize_hex_color( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			$o[ $k ] = $c ? $c : $d[ $k ];
		}
		$o['radius']      = min( 30, max( 0, (int) ( isset( $in['radius'] ) ? $in['radius'] : 10 ) ) );
		$o['font_size']   = min( 22, max( 11, (int) ( isset( $in['font_size'] ) ? $in['font_size'] : 15 ) ) );
		$o['expiry']      = min( 395, max( 1, (int) ( isset( $in['expiry'] ) ? $in['expiry'] : 180 ) ) );
		$o['policy_page'] = max( 0, (int) ( isset( $in['policy_page'] ) ? $in['policy_page'] : 0 ) );
		$o['gcm']         = empty( $in['gcm'] ) ? 0 : 1;
		$o['block']       = empty( $in['block'] ) ? 0 : 1;

		foreach ( array( 'title', 'btn_accept', 'btn_reject', 'btn_settings', 'btn_save', 'modal_title', 'policy_text', 'cat_necessary', 'cat_statistics', 'cat_marketing', 'placeholder_btn' ) as $k ) {
			$v       = sanitize_text_field( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			$o[ $k ] = '' !== $v ? $v : $d[ $k ];
		}
		foreach ( array( 'message', 'desc_necessary', 'desc_statistics', 'desc_marketing', 'placeholder' ) as $k ) {
			$v       = sanitize_textarea_field( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			$o[ $k ] = '' !== $v ? $v : $d[ $k ];
		}

		$services = array();
		if ( isset( $in['services'] ) && is_array( $in['services'] ) ) {
			foreach ( $in['services'] as $s ) {
				if ( ! is_array( $s ) || ! empty( $s['del'] ) ) {
					continue;
				}
				$name = sanitize_text_field( isset( $s['name'] ) ? $s['name'] : '' );
				if ( '' === $name ) {
					continue;
				}
				$id         = sanitize_key( isset( $s['id'] ) ? $s['id'] : '' );
				$services[] = array(
					'id'       => '' !== $id ? $id : 'vlastni-' . substr( md5( $name . microtime() ), 0, 8 ),
					'name'     => $name,
					'cat'      => in_array( isset( $s['cat'] ) ? $s['cat'] : '', clm_categories(), true ) ? $s['cat'] : 'marketing',
					'cookies'  => sanitize_text_field( isset( $s['cookies'] ) ? $s['cookies'] : '' ),
					'patterns' => sanitize_textarea_field( isset( $s['patterns'] ) ? $s['patterns'] : '' ),
					'desc'     => sanitize_text_field( isset( $s['desc'] ) ? $s['desc'] : '' ),
				);
			}
		}
		// Smazané služby si zapamatovat, aby je další prohledání znovu nepřidalo.
		$kept    = wp_list_pluck( $services, 'id' );
		$ignored = isset( $o['ignored'] ) ? (array) $o['ignored'] : array();
		foreach ( (array) $o['services'] as $old ) {
			if ( ! empty( $old['id'] ) && ! in_array( $old['id'], $kept, true ) ) {
				$ignored[] = $old['id'];
			}
		}
		$o['ignored']  = array_values( array_unique( array_diff( $ignored, $kept ) ) );
		$o['services'] = $services;

		update_option( 'clm', $o );
		clm_opt( null, true );
		clm_back( 'saved' );
	}
);

add_action(
	'admin_post_clm_scan',
	function () {
		clm_check( 'clm_scan' );
		require_once __DIR__ . '/scan.php';
		clm_scan( true );
		clm_back( 'scanned' );
	}
);

add_action(
	'admin_post_clm_reset',
	function () {
		clm_check( 'clm_reset' );
		$o            = clm_opt();
		$o['version'] = (int) $o['version'] + 1;
		update_option( 'clm', $o );
		clm_back( 'reset' );
	}
);

/* ------------------------------------------------------------------
 * Stránka nastavení
 * ------------------------------------------------------------------ */

function clm_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o    = clm_opt();
	$msg  = isset( $_GET['clm'] ) ? sanitize_key( $_GET['clm'] ) : '';
	$cats = array(
		'necessary'  => $o['cat_necessary'],
		'statistics' => $o['cat_statistics'],
		'marketing'  => $o['cat_marketing'],
	);
	$info = isset( $o['scan_info'] ) ? $o['scan_info'] : null;

	$text = function ( $k, $label, $area = false ) use ( $o ) {
		echo '<tr><th scope="row"><label for="clm-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( $area ) {
			echo '<textarea id="clm-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" rows="3" class="large-text">' . esc_textarea( $o[ $k ] ) . '</textarea>';
		} else {
			echo '<input type="text" id="clm-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $o[ $k ] ) . '" class="regular-text">';
		}
		echo '</td></tr>';
	};
	$select = function ( $k, $choices ) use ( $o ) {
		echo '<select name="' . esc_attr( $k ) . '" id="clm-' . esc_attr( $k ) . '">';
		foreach ( $choices as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( $o[ $k ], $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	};
	$msgs = array(
		'saved'   => 'Nastavení uloženo.',
		'scanned' => 'Web byl prohledán.',
		'reset'   => 'Všichni návštěvníci budou znovu požádáni o souhlas.',
	);
	?>
	<div class="wrap">
		<h1>Cookies lišta</h1>
		<?php if ( isset( $msgs[ $msg ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msgs[ $msg ] ); ?></p></div>
		<?php endif; ?>

		<p>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'clm_preview', '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener">Náhled lišty na webu</a>
			&nbsp;Do stránky Zásady cookies vložte <code>[cookies_lista_seznam]</code> (tabulka cookies) a případně <code>[cookies_lista_nastaveni]</code> (tlačítko pro změnu souhlasu). Odkaz s adresou <code>#clm-nastaveni</code> také otevře nastavení.
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="clm_save">
			<?php wp_nonce_field( 'clm_save' ); ?>

			<h2>Vzhled</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="clm-position">Umístění</label></th><td>
					<?php $select( 'position', array( 'box-left' => 'Okénko vlevo dole', 'box-right' => 'Okénko vpravo dole', 'bar-bottom' => 'Lišta dole přes celou šířku', 'bar-top' => 'Lišta nahoře přes celou šířku', 'center' => 'Uprostřed obrazovky (se ztmavením)' ) ); ?>
				</td></tr>
				<tr><th scope="row">Barvy</th><td>
					<label>Pozadí <input type="text" name="bg" value="<?php echo esc_attr( $o['bg'] ); ?>" class="clm-color"></label><br>
					<label>Text <input type="text" name="text" value="<?php echo esc_attr( $o['text'] ); ?>" class="clm-color"></label><br>
					<label>Hlavní tlačítko <input type="text" name="accent" value="<?php echo esc_attr( $o['accent'] ); ?>" class="clm-color"></label><br>
					<label>Text hlavního tlačítka <input type="text" name="accent_text" value="<?php echo esc_attr( $o['accent_text'] ); ?>" class="clm-color"></label>
				</td></tr>
				<tr><th scope="row">Rozměry</th><td>
					<label>Zaoblení rohů <input type="number" name="radius" min="0" max="30" value="<?php echo (int) $o['radius']; ?>" class="small-text"> px</label>&nbsp;&nbsp;
					<label>Velikost písma <input type="number" name="font_size" min="11" max="22" value="<?php echo (int) $o['font_size']; ?>" class="small-text"> px</label>
				</td></tr>
				<tr><th scope="row"><label for="clm-reopen">Tlačítko pro změnu souhlasu</label></th><td>
					<?php $select( 'reopen', array( 'left' => 'Ikonka vlevo dole', 'right' => 'Ikonka vpravo dole', 'none' => 'Nezobrazovat (použiji odkaz nebo shortcode)' ) ); ?>
				</td></tr>
			</table>

			<h2>Texty</h2>
			<table class="form-table" role="presentation">
				<?php
				$text( 'title', 'Nadpis' );
				$text( 'message', 'Text lišty', true );
				$text( 'btn_accept', 'Tlačítko „přijmout“' );
				$text( 'btn_reject', 'Tlačítko „odmítnout“' );
				$text( 'btn_settings', 'Tlačítko „nastavení“' );
				$text( 'btn_save', 'Tlačítko „uložit výběr“' );
				$text( 'modal_title', 'Nadpis okna s nastavením' );
				$text( 'policy_text', 'Text odkazu na zásady' );
				?>
				<tr><th scope="row"><label for="clm-policy">Stránka se zásadami cookies</label></th><td>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => 'policy_page',
							'id'                => 'clm-policy',
							'selected'          => (int) $o['policy_page'],
							'show_option_none'  => '— žádná —',
							'option_none_value' => '0',
						)
					);
					?>
				</td></tr>
				<?php
				foreach ( clm_categories() as $c ) {
					$text( 'cat_' . $c, 'Kategorie: název (' . $c . ')' );
					$text( 'desc_' . $c, 'Kategorie: popis (' . $c . ')', true );
				}
				$text( 'placeholder', 'Text místo blokovaného videa/mapy (%s = služba)', true );
				$text( 'placeholder_btn', 'Tlačítko místo blokovaného obsahu' );
				?>
			</table>

			<h2>Chování</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Zobrazovat lištu</th><td>
					<label><input type="radio" name="show" value="always" <?php checked( 'auto' !== $o['show'] ); ?>> Vždy</label><br>
					<label><input type="radio" name="show" value="auto" <?php checked( 'auto' === $o['show'] ); ?>> Jen pokud web používá statistické nebo marketingové služby</label>
				</td></tr>
				<tr><th scope="row">Blokování</th><td><label><input type="checkbox" name="block" value="1" <?php checked( $o['block'] ); ?>> Blokovat skripty, videa a mapy služeb do udělení souhlasu</label></td></tr>
				<tr><th scope="row">Google Consent Mode v2</th><td><label><input type="checkbox" name="gcm" value="1" <?php checked( $o['gcm'] ); ?>> Předávat souhlas Google značkám (Analytics, Ads, Tag Manager)</label></td></tr>
				<tr><th scope="row"><label for="clm-expiry">Platnost souhlasu</label></th><td><input type="number" id="clm-expiry" name="expiry" min="1" max="395" value="<?php echo (int) $o['expiry']; ?>" class="small-text"> dní</td></tr>
			</table>

			<h2>Služby a cookies</h2>
			<p>
				<?php if ( $info ) : ?>
					Naposledy prohledáno <?php echo esc_html( wp_date( 'j. n. Y H:i', (int) $info['time'] ) ); ?>
					<?php echo $info['http'] ? '(prohledáno stránek: ' . (int) $info['pages'] . ')' : '(jen pluginy a obsah – prohledání stránek proběhne na pozadí)'; ?>.
				<?php else : ?>
					Web zatím nebyl prohledán.
				<?php endif; ?>
				Prohledání hledá <strong>známé služby</strong> (pluginy, obsah stránek a HTML webu) – nemusí najít všechno, po větších změnách webu ho zopakujte a seznam zkontrolujte.
				Do pole „Blokovat podle“ patří část adresy skriptu nebo iframu (každá na samostatný řádek). Google Tag Manager může načítat i marketingové kódy – podle jeho obsahu zvolte kategorii.
			</p>
			<table class="widefat striped clm-services">
				<thead><tr><th>Služba</th><th>Kategorie</th><th>Cookies</th><th>Blokovat podle</th><th>Popis</th><th>Smazat</th></tr></thead>
				<tbody>
				<?php
				$rows   = (array) $o['services'];
				$rows[] = array(
					'id'       => '',
					'name'     => '',
					'cat'      => 'marketing',
					'cookies'  => '',
					'patterns' => '',
					'desc'     => '',
				);
				foreach ( array_values( $rows ) as $i => $s ) :
					$n = 'services[' . $i . ']';
					?>
					<tr>
						<td><input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $s['id'] ); ?>">
							<input type="text" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( $s['name'] ); ?>" placeholder="<?php echo '' === $s['id'] ? 'Přidat službu…' : ''; ?>" style="width:100%"></td>
						<td><select name="<?php echo esc_attr( $n ); ?>[cat]">
							<?php foreach ( $cats as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['cat'], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select></td>
						<td><input type="text" name="<?php echo esc_attr( $n ); ?>[cookies]" value="<?php echo esc_attr( $s['cookies'] ); ?>" style="width:100%"></td>
						<td><textarea name="<?php echo esc_attr( $n ); ?>[patterns]" rows="2" style="width:100%;font-family:monospace;font-size:12px"><?php echo esc_textarea( isset( $s['patterns'] ) ? $s['patterns'] : '' ); ?></textarea></td>
						<td><input type="text" name="<?php echo esc_attr( $n ); ?>[desc]" value="<?php echo esc_attr( isset( $s['desc'] ) ? $s['desc'] : '' ); ?>" style="width:100%"></td>
						<td><?php if ( '' !== $s['id'] ) : ?><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[del]" value="1" aria-label="Smazat"><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( 'Uložit nastavení' ); ?>
		</form>

		<h2>Nástroje</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:10px">
			<input type="hidden" name="action" value="clm_scan">
			<?php wp_nonce_field( 'clm_scan' ); ?>
			<?php submit_button( 'Prohledat web znovu', 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block" onsubmit="return confirm('Opravdu znovu požádat všechny návštěvníky o souhlas?');">
			<input type="hidden" name="action" value="clm_reset">
			<?php wp_nonce_field( 'clm_reset' ); ?>
			<?php submit_button( 'Znovu požádat všechny o souhlas', 'secondary', 'submit', false ); ?>
		</form>
		<p class="description">Nové prohledání jen přidá nově nalezené služby – vaše úpravy a ručně přidané služby zůstanou.</p>
	</div>
	<?php
}
