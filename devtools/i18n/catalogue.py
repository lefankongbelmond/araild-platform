#!/usr/bin/env python3
"""
MX-CONNECT — catalogue des textes traduisibles (multilingue, 2026-11-28).

  python3 devtools/i18n/catalogue.py sync [labels.json]
      Parcourt app/, resources/views/, routes/ ; ajoute à lang/fr.json chaque texte __('…') littéral
      (hors clés de groupe « fichier.clé »), plus les libellés du registre TranslatableLabels exportés
      dans labels.json (facultatif). Aucune clé n'est jamais supprimée.

  python3 devtools/i18n/catalogue.py missing <code> [fichier]
      Écrit (ou affiche) la liste JSON des textes de lang/fr.json absents de lang/<code>.json.

  python3 devtools/i18n/catalogue.py merge <code> <traductions.json>
      Ajoute à lang/<code>.json les traductions {texte français: traduction} fournies, en vérifiant
      que les variables (:nom) sont identiques. Refuse toute traduction dont les variables diffèrent.
"""
import json, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
APP = os.path.join(ROOT, 'production', 'mxconnect')
LANG = os.path.join(APP, 'lang')
CALL = re.compile(r"""(?:__|@lang|trans)\(\s*(['"])((?:\\.|(?!\1).)*)\1""", re.S)
GROUP_KEY = re.compile(r'^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]*)+$')
VARS = re.compile(r':([A-Za-z_]+)')


def load(code):
    p = os.path.join(LANG, code + '.json')
    return json.load(open(p, encoding='utf-8')) if os.path.exists(p) else {}


def save(code, data):
    with open(os.path.join(LANG, code + '.json'), 'w', encoding='utf-8') as f:
        json.dump(dict(sorted(data.items())), f, ensure_ascii=False, indent=4)
        f.write('\n')


def unescape(s, quote):
    return s.replace('\\' + quote, quote).replace('\\\\', '\\')


def scan():
    keys = set()
    for d in ('app', 'resources/views', 'routes'):
        for dp, _, fn in os.walk(os.path.join(APP, d)):
            for f in fn:
                if not f.endswith('.php'):
                    continue
                src = open(os.path.join(dp, f), encoding='utf-8').read()
                for m in CALL.finditer(src):
                    k = unescape(m.group(2), m.group(1))
                    if not GROUP_KEY.match(k):
                        keys.add(k)
    return keys


def main():
    cmd = sys.argv[1]
    if cmd == 'sync':
        fr = load('fr')
        before = len(fr)
        keys = scan()
        if len(sys.argv) > 2:
            keys |= set(json.load(open(sys.argv[2], encoding='utf-8')))
        for k in keys:
            fr.setdefault(k, k)
        save('fr', fr)
        print(f'lang/fr.json : {before} → {len(fr)} textes')
    elif cmd == 'missing':
        fr, tr = load('fr'), load(sys.argv[2])
        miss = [k for k in sorted(fr) if not tr.get(k)]
        out = json.dumps(miss, ensure_ascii=False, indent=1)
        if len(sys.argv) > 3:
            open(sys.argv[3], 'w', encoding='utf-8').write(out)
        print(f'{sys.argv[2]} : {len(miss)} texte(s) à traduire', file=sys.stderr)
    elif cmd == 'merge':
        code, src = sys.argv[2], json.load(open(sys.argv[3], encoding='utf-8'))
        fr, tr = load('fr'), load(code)
        bad = []
        for k, v in src.items():
            if k not in fr:
                bad.append(('clé inconnue', k))
                continue
            if sorted(VARS.findall(k)) != sorted(VARS.findall(v)):
                bad.append(('variables', k))
                continue
            tr[k] = v
        save(code, tr)
        print(f'{code} : {len(src) - len(bad)} ajoutée(s), {len(bad)} refusée(s)')
        for why, k in bad:
            print('  !', why, ':', k[:100])
    else:
        print(__doc__)


if __name__ == '__main__':
    main()
