#!/usr/bin/env python3
"""
MX-CONNECT — rendre traduisibles les phrases mêlées à des variables (multilingue M2b, 2026-11-29).

Usage :
  python3 devtools/i18n/convertir-variables.py --dry-run <fichiers ou dossiers…>   # montre ce qui serait fait
  python3 devtools/i18n/convertir-variables.py --apply   <fichiers ou dossiers…>

Ce que l'outil convertit : un nœud de texte « >… <» qui mêle du texte français et des échos
simples {{ expression }} (sans directive @, sans {!! !!}, sans commentaire), par exemple
  >Projets de {{ $mutual->name }}<
  →  >{{ __('Projets de :name', ['name' => $mutual->name]) }}<
Le nom de chaque variable est déduit de l'expression (name, count, date, amount, code…), numéroté
en cas de doublon. Les segments qui contiennent une directive (@if…) sont laissés tels quels et
listés : ils se traitent à la main. Les clés sont ajoutées à lang/fr.json (valeur = clé).
Toujours relire le git diff après --apply.
"""
import json, os, re, sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from importlib import import_module

ext = import_module('extraire-blade')  # noqa: E402  (même dossier)

ECHO = re.compile(r'\{\{\s*(.*?)\s*\}\}', re.S)
LETTER = ext.LETTER


def var_name(expr: str) -> str:
    e = expr.strip()
    if re.search(r'seconds', e):
        return 'seconds'
    if re.search(r'^round\(', e):
        return 'size'
    if re.search(r'\?\?\s*0$|^\$i \+ 1$', e):
        return 'count' if '??' in e else 'number'
    if re.search(r'^number_format\(', e):
        return 'count'
    if re.search(r'^\$fmt\(|money\(|->format\(\$|Currency|currency->format', e):
        return 'amount'
    if re.search(r'currency->code', e):
        return 'currency'
    if re.search(r'format\(|translatedFormat|diffForHumans', e):
        return 'date'
    if re.search(r'count\(|->count\(\)|_count\b|->total\(\)', e):
        return 'count'
    m = re.findall(r'(?:->|\[\')([A-Za-z_]+)(?:\'\])?', e)
    if m:
        last = m[-1]
        if last in ('name', 'first_name'):
            return 'name'
        if last in ('reference', 'ref', 'member_code', 'code'):
            return 'reference'
        if last.endswith('_count'):
            return 'count'
        return re.sub(r'[^a-z_]', '', last.lower()) or 'value'
    m = re.match(r'\$([A-Za-z_]+)$', e)
    if m:
        return m.group(1).lower()
    return 'value'


def convert(raw: str):
    """raw = texte brut du nœud (avec échos). Renvoie (nouveau, clé) ou None."""
    if '@' in re.sub(r'\{\{.*?\}\}', '', raw, flags=re.S) or '{!!' in raw or '{{--' in raw:
        return None
    echoes = ECHO.findall(raw)
    if not echoes:
        return None
    # Un écho qui produit lui-même du texte (« ? 's' : '' », « ', ' . $x ») se traite à la main.
    if any(re.search(r"'[^']*(?:[A-Za-zÀ-ÿ]{3,} |, | [A-Za-zÀ-ÿ(]{2,})[^']*'", e) or re.search(r"\s\?\s", e) for e in echoes):
        return None
    text_only = ECHO.sub('', raw)
    if not LETTER.search(text_only.replace('MX-CONNECT', '')) or '__(' in raw:
        return None
    names, used, parts = [], {}, []
    pos = 0
    key = ''
    for m in ECHO.finditer(raw):
        key += raw[pos:m.start()]
        base = var_name(m.group(1))
        n = used.get(base, 0) + 1
        used[base] = n
        name = base if n == 1 else f'{base}{n}'
        key += ':' + name
        parts.append((name, m.group(1).strip()))
        pos = m.end()
    key += raw[pos:]
    lead = raw[:len(raw) - len(raw.lstrip())]
    trail = raw[len(raw.rstrip()):]
    key = ext.norm(key)
    args = ', '.join(f"'{n}' => {e}" for n, e in parts)
    return lead + "{{ __('" + ext.esc(key) + "', [" + args + "]) }}" + trail, key


def process(path, apply, found, manual):
    src = open(path, encoding='utf-8').read()
    # On masque les blocs à ne pas toucher, SAUF les échos {{ }} que l'on veut voir dans le texte.
    store, echoes = [], []

    def keep(m):
        if m.group(0).startswith('{{') and not m.group(0).startswith('{{--'):
            echoes.append(m.group(0))
            return f'\x01{len(echoes) - 1}\x01'
        store.append(m.group(0))
        return f'\x00{len(store) - 1}\x00'
    masked = ext.SKIP_BLOCKS.sub(keep, src)
    changed = [False]
    unecho = lambda t: re.sub(r'\x01(\d+)\x01', lambda k: echoes[int(k.group(1))], t)

    def node(m):
        raw = m.group(1)
        if '\x00' in raw:
            if '\x01' in raw and LETTER.search(re.sub(r'\x00\d+\x00|\x01\d+\x01', '', raw)):
                manual.append((path, ext.norm(unecho(re.sub(r'\x00(\d+)\x00', lambda k: store[int(k.group(1))], raw)))))
            return m.group(0)
        raw = unecho(raw)
        r = convert(raw)
        if not r:
            return m.group(0)
        new, key = r
        found.setdefault(key, set()).add(path)
        changed[0] = True
        return '>' + new + '<'

    out = ext.TEXT_NODE.sub(node, masked)
    out = unecho(out)
    out = re.sub(r'\x00(\d+)\x00', lambda k: store[int(k.group(1))], out)
    if changed[0]:
        if apply:
            open(path, 'w', encoding='utf-8').write(out)


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else '--dry-run'
    found, manual = {}, []
    for f in ext.files(sys.argv[2:]):
        process(f, mode == '--apply', found, manual)
    for k in sorted(found):
        print('+', k)
    for p, t in manual:
        print('MAIN', os.path.relpath(p, ext.APP), '|', t[:200])
    if mode == '--apply':
        cat = json.load(open(ext.FR_JSON, encoding='utf-8'))
        for k in found:
            cat.setdefault(k, k)
        with open(ext.FR_JSON, 'w', encoding='utf-8') as fh:
            json.dump(dict(sorted(cat.items())), fh, ensure_ascii=False, indent=4)
            fh.write('\n')
    print(f'# converties : {len(found)} · à la main : {len(manual)}', file=sys.stderr)


if __name__ == '__main__':
    main()
