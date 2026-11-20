#!/usr/bin/env python3
"""
MX-CONNECT — rendre traduisibles les textes français des vues Blade (multilingue, 2026-11-28).

Usage :
  python3 devtools/i18n/extraire-blade.py --dry-run  <fichiers ou dossiers…>   # compte, ne modifie rien
  python3 devtools/i18n/extraire-blade.py --apply    <fichiers ou dossiers…>   # remplace dans les vues
  python3 devtools/i18n/extraire-blade.py --list     <fichiers ou dossiers…>   # liste les textes trouvés

Ce que l'outil remplace (et seulement cela) :
  - un nœud de texte « pur » entre deux balises : >Texte français<  →  >{{ __('Texte français') }}<
  - les attributs placeholder, aria-label, title, alt, data-confirm au texte « pur ».
Un texte « pur » ne contient ni {{, ni {!!, ni @, ni $, ni balise, et au moins une lettre.
Le texte mêlé à des variables (« Bonjour {{ $nom }} ») est LAISSÉ tel quel et listé à part :
il se traite à la main avec __('Bonjour :nom', ['nom' => …]).
Ignorés : <script>, <style>, <svg>, commentaires Blade/HTML, textes sans lettre, codes (MX-CONNECT…).
Le texte de la clé est le texte français tel qu'affiché (espaces internes normalisés, entités
HTML gardées telles quelles). Les clés trouvées sont ajoutées à lang/fr.json (valeur = clé).
"""
import json, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
APP = os.path.join(ROOT, 'production', 'mxconnect')
FR_JSON = os.path.join(APP, 'lang', 'fr.json')

SKIP_BLOCKS = re.compile(r'(<script\b.*?</script>|<style\b.*?</style>|<svg\b.*?</svg>|\{\{--.*?--\}\}|<!--.*?-->|@php\b.*?@endphp|\{\{.*?\}\}|\{!!.*?!!\}|@\w+(?:\s*\((?:[^()]|\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))*\))?)', re.S)
TEXT_NODE = re.compile(r'>([^<>]+)<')
ATTR = re.compile(r'(?<![:\w-])(placeholder|aria-label|title|alt|data-confirm)="([^"]*)"')
KEEP = {'https://', 'XXXX-XXXX-XXXX', 'MX-CONNECT', 'MX', 'CONNECT', 'FCFA', 'XAF', 'XOF', 'PDF', 'CSV', 'QR', 'OK', 'ID', 'Android', 'iOS', 'WhatsApp', 'Facebook', 'Telegram', 'LinkedIn', 'X', 'SMS', 'Mobile Money'}
LETTER = re.compile(r'[A-Za-zÀ-ÿœŒ]')


def pure(s: str) -> bool:
    t = s.strip()
    if not t or not LETTER.search(t):
        return False
    if any(x in t for x in ('{{', '{!!', '@', '$', '}}', '<', '>', '=>', '::')):
        return False
    if t in KEEP or re.fullmatch(r'[A-Z0-9_\-\.·:/ ]+', t) and len(t) <= 4:
        return False
    return True


def norm(s: str) -> str:
    return re.sub(r'\s+', ' ', s.strip())


def esc(s: str) -> str:
    return s.replace('\\', '\\\\').replace("'", "\\'")


def protect(src: str):
    """Masque les zones à ne pas toucher (blocs, échos, directives) par des jetons."""
    store = []

    def keep(m):
        store.append(m.group(0))
        return f'\x00{len(store) - 1}\x00'
    return SKIP_BLOCKS.sub(keep, src), store


def restore(src: str, store):
    return re.sub(r'\x00(\d+)\x00', lambda m: store[int(m.group(1))], src)


def process(path: str, apply: bool, found: dict, mixed: list):
    src = open(path, encoding='utf-8').read()
    masked, store = protect(src)
    changed = [False]

    def text_node(m):
        raw = m.group(1)
        if '\x00' in raw:
            # texte mêlé à du Blade : les morceaux purs autour des jetons restent (traités à la main)
            parts = [p for p in re.split(r'\x00\d+\x00', raw) if LETTER.search(p or '')]
            if parts and any(pure(p) for p in parts):
                mixed.append((path, norm(restore(raw, store))))
            return m.group(0)
        if not pure(raw):
            return m.group(0)
        key = norm(raw)
        found.setdefault(key, set()).add(path)
        lead = raw[:len(raw) - len(raw.lstrip())]
        trail = raw[len(raw.rstrip()):]
        changed[0] = True
        return '>' + lead + "{{ __('" + esc(key) + "') }}" + trail + '<'

    def attr(m):
        name, val = m.group(1), m.group(2)
        if '\x00' in val or not pure(val):
            return m.group(0)
        key = norm(val)
        found.setdefault(key, set()).add(path)
        changed[0] = True
        return name + '="{{ __(\'' + esc(key) + '\') }}"'

    out = TEXT_NODE.sub(text_node, masked)
    out = ATTR.sub(attr, out)
    out = restore(out, store)
    if apply and changed[0] and out != src:
        open(path, 'w', encoding='utf-8').write(out)


def files(args):
    for a in args:
        if os.path.isdir(a):
            for dp, _, fn in os.walk(a):
                for f in sorted(fn):
                    if f.endswith('.blade.php'):
                        yield os.path.join(dp, f)
        elif a.endswith('.blade.php'):
            yield a


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else '--dry-run'
    targets = list(files(sys.argv[2:]))
    found, mixed = {}, []
    for f in targets:
        process(f, mode == '--apply', found, mixed)
    if mode == '--apply':
        cat = json.load(open(FR_JSON, encoding='utf-8')) if os.path.exists(FR_JSON) else {}
        for k in found:
            cat.setdefault(k, k)
        with open(FR_JSON, 'w', encoding='utf-8') as fh:
            json.dump(dict(sorted(cat.items())), fh, ensure_ascii=False, indent=4)
            fh.write('\n')
    if mode == '--list':
        for k in sorted(found):
            print(k)
    print(f'# fichiers : {len(targets)} · textes purs distincts : {len(found)} · '
          f'segments mêlés à traiter à la main : {len(mixed)}', file=sys.stderr)
    if mode == '--mixed':
        for p, t in mixed:
            print(os.path.relpath(p, APP), '|', t)


if __name__ == '__main__':
    main()
