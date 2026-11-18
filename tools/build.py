#!/usr/bin/env python3
"""Sestaví zip pluginu do releases/ a metadata pro aktualizace do updates/<slug>.json.
Použití: tools/build.py <slug>   (verze a changelog se berou z hlavičky pluginu a readme.txt)
Weby si updates/<slug>.json čtou přes raw.githubusercontent.com → po pushi se nová verze nabídne."""
import datetime, hashlib, html, json, os, re, sys, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RAW = 'https://raw.githubusercontent.com/varmuzaj-git/wp-pluginy/main/'
MAIN = {'bezpecnost': 'bezpecnost.php', 'cookies-lista-moja': 'cookies-lista-moja.php'}

def header(src, name):
    m = re.search(r'^\s*\*\s*' + re.escape(name) + r':\s*(.+)$', src, re.M)
    return m.group(1).strip() if m else ''

def changelog_html(readme):
    part = readme.split('== Changelog ==', 1)[1] if '== Changelog ==' in readme else ''
    out = []
    for block in re.split(r'^= ', part, flags=re.M)[1:]:
        ver, _, rest = block.partition(' =')
        items = [html.escape(l[1:].strip()) for l in rest.splitlines() if l.startswith('*')]
        out.append(f'<h4>{html.escape(ver.strip())}</h4><ul>' + ''.join(f'<li>{i}</li>' for i in items) + '</ul>')
    return ''.join(out)

slug = sys.argv[1]
src_dir = os.path.join(ROOT, slug)
main = open(os.path.join(src_dir, MAIN[slug]), encoding='utf-8').read()
readme = open(os.path.join(src_dir, 'readme.txt'), encoding='utf-8').read()
ver = header(main, 'Version')
const = re.search(r"define\( '[A-Z]+_VER', '([^']+)' \)", main)
assert const and const.group(1) == ver, 'verze v hlavičce a v konstantě se liší'
assert f'Stable tag: {ver}' in readme, 'readme.txt: Stable tag nesedí'

os.makedirs(os.path.join(ROOT, 'releases'), exist_ok=True)
zname = f'{slug}-{ver}.zip'
zpath = os.path.join(ROOT, 'releases', zname)
assert not os.path.exists(zpath), f'{zname} už existuje – vydanou verzi neměň, zvyš číslo verze'
with zipfile.ZipFile(zpath, 'w', zipfile.ZIP_DEFLATED) as z:
    for d, dirs, files in os.walk(src_dir):
        dirs[:] = sorted(x for x in dirs if not x.startswith('.'))
        for f in sorted(files):
            if f.startswith('.'): continue
            full = os.path.join(d, f)
            z.write(full, os.path.join(slug, os.path.relpath(full, src_dir)))
sha = hashlib.sha256(open(zpath, 'rb').read()).hexdigest()

desc = readme.split('\n\n', 2)[1].strip() if '\n\n' in readme else ''
meta = {
    'name': header(main, 'Plugin Name'), 'slug': slug, 'version': ver,
    'download_url': RAW + 'releases/' + zname,
    'homepage': 'https://github.com/varmuzaj-git/wp-pluginy',
    'author': header(main, 'Author'),
    'requires': header(main, 'Requires at least'), 'requires_php': header(main, 'Requires PHP'), 'tested': '7.1',
    'last_updated': datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),
    'sections': {'description': '<p>' + html.escape(desc) + '</p>', 'changelog': changelog_html(readme)},
    'sha256': sha,
}
os.makedirs(os.path.join(ROOT, 'updates'), exist_ok=True)
with open(os.path.join(ROOT, 'updates', slug + '.json'), 'w', encoding='utf-8') as f:
    json.dump(meta, f, ensure_ascii=False, indent=1); f.write('\n')
print(zname, os.path.getsize(zpath), 'B, sha256', sha)
