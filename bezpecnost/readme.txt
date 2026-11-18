=== Bezpečnost ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later

Lehká náhrada pluginů WPS Hide Login / Admin Login URL Change, Disable XML-RPC a základního omezení pokusů o přihlášení.

== Funkce ==

* Vlastní přihlašovací adresa (např. /vstup-123/). wp-login.php a wp-admin vrací nepřihlášeným 404 (nebo přesměrování na úvod).
* Úplné vypnutí XML-RPC.
* Blokování xmlrpc.php a wp-login.php přímo v .htaccess – boti vůbec nespustí WordPress.
* Omezení pokusů o přihlášení podle IP (IPv6 po /64), seznam povolených IP, log blokací.
* Obecná chybová hláška při přihlášení, skrytí uživatelských jmen (?author=, REST API, sitemapa, oEmbed).
* Vypnutí editoru souborů, skrytí verze WordPressu, bezpečnostní HTTP hlavičky.

Při aktivaci převezme přihlašovací adresu z WPS Hide Login nebo Admin Login URL Change. Dokud je starý plugin aktivní, skrytí přihlášení nechává na něm; po jeho deaktivaci adresu převezme automaticky.

Na frontendu se načítá jen jeden soubor; administrace je v samostatném souboru.

== Nouzový přístup ==

Pokud zapomenete přihlašovací adresu:
1. přes FTP přejmenujte složku wp-content/plugins/bezpecnost (pravidla v .htaccess se tím automaticky vypnou), nebo
2. do wp-config.php přidejte: define( 'BEZPECNOST_NOUZOVY_REZIM', true );

Pokud by se po deaktivaci nebo smazání pluginu nedalo přihlásit (soubor .htaccess nešel upravit),
odstraňte přes FTP z .htaccess blok mezi řádky "# BEGIN Bezpecnost" a "# END Bezpecnost".

== Aktualizace ==

Nové verze se nabízejí z GitHubu (https://github.com/varmuzaj-git/wp-pluginy) mezi běžnými aktualizacemi
pluginů ve WordPressu i v MainWP. Nic se neinstaluje automaticky.

== Changelog ==

= 1.2.0 =
* Aktualizace z GitHubu (knihovna Plugin Update Checker 5.7, načítá se jen v administraci, cronu a pro MainWP).
