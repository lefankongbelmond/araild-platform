#!/usr/bin/env python3
"""
MX-CONNECT — rendre traduisibles les messages français écrits dans le code PHP (multilingue M2b).

Usage :
  python3 devtools/i18n/messages-php.py --dry-run <fichiers ou dossiers…>   # liste ce qui serait fait
  python3 devtools/i18n/messages-php.py --apply   <fichiers ou dossiers…>

Repère, hors commentaires et hors tableaux de constantes (« const X = [ … ] », déjà traduits à
l'affichage via App\\Support\\TranslatableLabels), les chaînes entre apostrophes qui ressemblent à
une phrase française destinée à l'écran : messages flash (->with('ok', '…')), erreurs
(withErrors, withMessages, abort(…, 403, '…'), AccountingRefusal('…')), messages de validation
('champ.règle' => '…'), et les entoure de __('…').
Laissées de côté et listées (« À LA MAIN ») : chaînes entre guillemets avec variables ("… {$x}") et
concaténations ('… ' . $x) — à réécrire en __('… :x', ['x' => …]).
Toujours relire le git diff après --apply.
"""
import os, re, sys

LIT = re.compile(r"(?<![\w\]\)])'((?:[^'\\]|\\.)*)'")
CONTEXT = re.compile(
    r"->with\(\s*'(ok|error|status|success|info|warning)'\s*,"
    r"|withErrors\(|withMessages\(|abort(?:_if|_unless)?\(|AccountingRefusal\(|Refusal\(|(?:->|::)?refuse\("
    r"|'[\w\*]+(?:\.[\w\*]+)+'\s*=>\s*'"
    r"|\$(msg|message|error|flash|fail)\s*="
    r"|=>\s*'[^']*'\s*,?\s*$"
    r"|^\s*return\s+'|^\s*[?:]\s+'|flash\(\s*'(ok|error|info)'"
)
SKIP_LINE = re.compile(r'^\s*(//|\*|/\*|#)|Log::|report\(|__\(|trans\(|logger\(|->where|DB::|Schema::')


def sentence(t: str) -> bool:
    if not re.search(r'[a-zà-ÿ]', t) or ' ' not in t.strip():
        return False
    if re.match(r'^[a-z_]+(\.[a-z_]+)+$', t) or t.startswith(('http', '/', '#', 'mx-', 'SELECT', 'select ')):
        return False
    first = t.strip()[0]
    return first.isupper() or first in '«(' or t.strip().endswith(('.', '!', '?', ')'))


SKIP_FILES = ('LegalService.php', 'LinkPreviewService.php')   # documents légaux = M6 ; en-tête technique


def process(path, apply, out):
    if path.endswith(SKIP_FILES):
        return
    lines = open(path, encoding='utf-8').read().split('\n')
    in_const = 0
    changed = False
    for i, line in enumerate(lines):
        if re.search(r'\bconst\s+[A-Z_]+\s*=\s*\[', line) and not line.rstrip().endswith('];'):
            in_const = 1
            continue
        if in_const:
            if re.search(r'^\s*\];', line):
                in_const = 0
            continue
        if SKIP_LINE.search(line):
            continue
        if re.search(r'"[^"]*[A-Za-zÀ-ÿ]{3,} [^"]*(\{\$|\$[a-z])[^"]*"', line) and CONTEXT.search(line):
            out.append(('MAIN', path, i + 1, line.strip()))
            continue
        if not CONTEXT.search(line):
            continue

        def rep(m):
            nonlocal changed
            t = m.group(1)
            if not sentence(t):
                return m.group(0)
            # clé de tableau (« 'texte' => ») : jamais
            after = line[m.end():m.end() + 4]
            if after.lstrip().startswith('=>'):
                return m.group(0)
            if line[:m.start()].rstrip().endswith('.') or line[m.end():].lstrip().startswith('.'):
                out.append(('MAIN', path, i + 1, line.strip()))
                return m.group(0)
            changed = True
            out.append(('+', path, i + 1, t))
            return "__('" + t + "')"
        lines[i] = LIT.sub(rep, line)
    if changed and apply:
        open(path, 'w', encoding='utf-8').write('\n'.join(lines))


def files(args):
    for a in args:
        if os.path.isdir(a):
            for dp, _, fn in os.walk(a):
                for f in sorted(fn):
                    if f.endswith('.php'):
                        yield os.path.join(dp, f)
        elif a.endswith('.php'):
            yield a


def main():
    mode = sys.argv[1]
    out = []
    for f in files(sys.argv[2:]):
        process(f, mode == '--apply', out)
    for kind, p, n, t in out:
        print(kind, f'{p}:{n}', '|', t[:180])
    print(f"# entourées : {sum(1 for o in out if o[0] == '+')} · à la main : {sum(1 for o in out if o[0] == 'MAIN')}", file=sys.stderr)


if __name__ == '__main__':
    main()
