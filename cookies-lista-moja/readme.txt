=== Cookies lišta moja ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later

Jednoduchá a lehká cookies lišta s automatickým prohledáním webu.

== Funkce ==

* Lišta nebo okénko (5 umístění), vlastní barvy, zaoblení, velikost písma a texty.
* Tři kategorie: nezbytné, statistické, marketingové. Tlačítka Přijmout vše / Odmítnout / Nastavení.
* Při aktivaci prohledá aktivní pluginy, obsah stránek a HTML webu a sama nastaví nalezené služby
  (Google Analytics, Tag Manager, Facebook Pixel, YouTube, Vimeo, Google Mapy, Mapy.com, Sklik, Clarity, Hotjar, Smartsupp…).
* Skripty a iframy služeb blokuje do udělení souhlasu (videa a mapy nahradí zástupným blokem s tlačítkem).
* Google Consent Mode v2.
* Po odvolání souhlasu smaže cookies dané kategorie.
* Funguje s cache stránek (WP Super Cache) – HTML je pro všechny stejné, lišta se sestaví v prohlížeči.
* Shortcody: [cookies_lista_seznam] – tabulka cookies, [cookies_lista_nastaveni] – tlačítko pro změnu souhlasu.
  Odkaz na #clm-nastaveni také otevře nastavení. JavaScript: window.clmOpen(), událost 'clm:consent'.

Na frontendu se načítá jen hlavní soubor pluginu, malý CSS (~4,5 kB) a JS (~10 kB).

== Aktualizace ==

Nové verze se nabízejí z GitHubu (https://github.com/varmuzaj-git/wp-pluginy) mezi běžnými aktualizacemi
pluginů ve WordPressu i v MainWP. Nic se neinstaluje automaticky.

== Changelog ==

= 1.2.0 =
* Aktualizace z GitHubu (knihovna Plugin Update Checker 5.7, načítá se jen v administraci, cronu a pro MainWP).
* Zahrnuje úpravy z 1.1.2: vyšší specificita CSS (.clm-root), tlačítko zástupného bloku YouTube v barvě akcentu.
