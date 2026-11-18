# WP pluginy (ValachWeb)

Vlastní pluginy pro weby, které spravuje Jan Varmuža:

- **Bezpečnost** – skrytá adresa přihlášení, omezení pokusů o přihlášení, vypnutí XML-RPC.
- **Cookies lišta moja** – cookies lišta s blokováním skriptů do souhlasu.

Aktualizace se weby nabízejí z vydání (Releases) tohoto repozitáře; instaluje je správce ručně (WordPress / MainWP).

## Struktura

| Cesta | Co to je |
|---|---|
| `bezpecnost/`, `cookies-lista-moja/` | zdrojové kódy pluginů (včetně knihovny `lib/plugin-update-checker`, MIT) |
| `releases/<slug>-<verze>.zip` | vydané verze – už se nemění |
| `updates/<slug>.json` | metadata nejnovější verze; z nich weby zjišťují, že je k dispozici aktualizace |
| `tools/build.py <slug>` | sestaví zip a metadata (verze z hlavičky pluginu, changelog z readme.txt) |

## Vydání nové verze

1. Úprava kódu, zvýšení `Version`, konstanty `*_VER` a `Stable tag`, záznam v Changelogu (readme.txt).
2. `tools/build.py <slug>` → commit → push.
3. Weby novou verzi uvidí při další kontrole (WordPress ji dělá zhruba 2× denně, MainWP při synchronizaci) a správce ji nainstaluje.
