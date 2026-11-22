"""Regenerate languages/: .pot plus, for every locale in LOCALES, .po, .mo and the JSON file for editor.js.
Usage: python3 tools/build-languages.py  (needs php)
Translations live in tools/translations/<module>.py as T = {msgid: "text"} (plural entries: msgid -> [form0, form1, ...])."""
import hashlib, importlib, json, os, re, struct, subprocess, sys
sys.path.insert(0, os.path.dirname(__file__))
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'translations'))
from locales import LOCALES, PLURALS
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DOMAIN = 'smokypost'
LANG = os.path.join(ROOT, 'languages')
entries = json.loads(subprocess.check_output(['php', os.path.join(ROOT, 'tools/extract.php'), ROOT, DOMAIN]))

def q(s):
    s = s.replace('\\', '\\\\').replace('"', '\\"').replace('\t', '\\t')
    if '\n' not in s.rstrip('\n'):
        return '"' + s.replace('\n', '\\n') + '"'
    lines = s.split('\n')
    parts = [l + '\\n' for l in lines[:-1]] + ([lines[-1]] if lines[-1] else [])
    return '""\n' + '\n'.join('"' + p + '"' for p in parts)

HEAD = '# Copyright (C) 2026 SmokySystems\n# This file is distributed under the GPL-2.0-or-later.\nmsgid ""\nmsgstr ""\n"Project-Id-Version: SmokyPost\\n"\n"MIME-Version: 1.0\\n"\n"Content-Type: text/plain; charset=UTF-8\\n"\n"Content-Transfer-Encoding: 8bit\\n"\n"X-Domain: smokypost\\n"\n'
def block(e, tr):
    out = ''
    if e['comment']:
        out += '#. ' + e['comment'] + '\n'
    out += '#: ' + ' '.join(e['refs']) + '\n'
    if '%' in e['msgid']:
        out += '#, php-format\n'
    out += 'msgid ' + q(e['msgid']) + '\n'
    if e['plural']:
        out += 'msgid_plural ' + q(e['plural']) + '\n'
        t = tr if isinstance(tr, list) else ['', '']
        out += 'msgstr[0] ' + q(t[0]) + '\nmsgstr[1] ' + q(t[1]) + '\n'
    else:
        out += 'msgstr ' + q(tr if isinstance(tr, str) else '') + '\n'
    return out

open(os.path.join(LANG, DOMAIN + '.pot'), 'w', encoding='utf-8').write(HEAD + ''.join('\n' + block(e, None) for e in entries))

def sid(msgid):
    return hashlib.md5(msgid.encode()).hexdigest()[:6]

def load(module):
    """tools/translations/<module>.py (dict T) or <module>.txt (one 'id|text' per line; plural forms split by ' ¦ ')."""
    base = os.path.join(os.path.dirname(__file__), 'translations', module)
    if os.path.exists(base + '.py'):
        return importlib.import_module(module).T
    by_id = {sid(e['msgid']): e for e in entries}
    T = {}
    for ln, line in enumerate(open(base + '.txt', encoding='utf-8').read().split('\n'), 1):
        if not line.strip() or line.startswith('#'):
            continue
        key, _, text = line.partition('|')
        key = key.strip()
        if key not in by_id:
            print('  %s.txt line %d: unknown id %s' % (module, ln, key))
            continue
        text = text.strip().replace('\\n', '\n')
        e = by_id[key]
        T[e['msgid']] = text.split(' ¦ ') if e['plural'] else text
    return T

TOKEN = re.compile(r'%(?:\d+\$)?[sdf]|</?[a-zA-Z][^>]*>|\{[a-z_]+\}')

def tokens(text):
    return sorted(TOKEN.findall(text))

def forms(tr):
    return tr if isinstance(tr, list) else [tr]

def check(code, T):
    """Problems of one translation table: missing strings, wrong plural count, changed placeholders or tags."""
    problems = []
    n = PLURALS[LOCALES[code]['plural']][0]
    for e in entries:
        tr = T.get(e['msgid'])
        if not tr:
            problems.append('MISSING: ' + e['msgid'])
            continue
        if e['plural'] and (not isinstance(tr, list) or len(tr) != n):
            problems.append('PLURAL FORMS (need %d): %s' % (n, e['msgid']))
            continue
        if not e['plural'] and isinstance(tr, list):
            problems.append('UNEXPECTED PLURAL: ' + e['msgid'])
            continue
        for i, f in enumerate(forms(tr)):
            src = e['plural'] if (e['plural'] and i > 0 and n == 2) else e['msgid']
            # Only compare placeholders for singular-style forms; plural forms may drop the number in some languages.
            if tokens(src) != tokens(f) and not (e['plural'] and '%d' not in f):
                problems.append('PLACEHOLDERS differ: %s -> %s' % (e['msgid'], f))
    return problems

def build(code, T):
    nplurals, rule = PLURALS[LOCALES[code]['plural']]
    plural_header = 'nplurals=%d; plural=%s;' % (nplurals, rule)
    po_head = HEAD + '"Language: %s\\n"\n"Plural-Forms: %s\\n"\n' % (code, plural_header)
    def block_t(e, tr):
        if e['plural']:
            t = tr if isinstance(tr, list) else [''] * nplurals
            out = ''
            if e['comment']:
                out += '#. ' + e['comment'] + '\n'
            out += '#: ' + ' '.join(e['refs']) + '\n'
            if '%' in e['msgid']:
                out += '#, php-format\n'
            out += 'msgid ' + q(e['msgid']) + '\nmsgid_plural ' + q(e['plural']) + '\n'
            for i in range(nplurals):
                out += 'msgstr[%d] ' % i + q(t[i] if i < len(t) else '') + '\n'
            return out
        return block(e, T.get(e['msgid']))
    open(os.path.join(LANG, '%s-%s.po' % (DOMAIN, code)), 'w', encoding='utf-8').write(po_head + ''.join('\n' + block_t(e, T.get(e['msgid'])) for e in entries))
    # MO
    pairs = [(b'', ('Content-Type: text/plain; charset=UTF-8\nLanguage: %s\nPlural-Forms: %s\n' % (code, plural_header)).encode())]
    for e in entries:
        tr = T.get(e['msgid'])
        if not tr:
            continue
        if e['plural']:
            pairs.append(((e['msgid'] + '\0' + e['plural']).encode(), '\0'.join(tr).encode()))
        else:
            pairs.append((e['msgid'].encode(), tr.encode()))
    pairs.sort()
    n = len(pairs); o_tab = 28; t_tab = o_tab + n * 8; pos = t_tab + n * 8
    ids = b''; strs = b''; oo = []; tt = []
    for k, _ in pairs:
        oo.append((len(k), pos)); pos += len(k) + 1; ids += k + b'\0'
    for _, v in pairs:
        tt.append((len(v), pos)); pos += len(v) + 1; strs += v + b'\0'
    mo = struct.pack('<7I', 0x950412de, 0, n, o_tab, t_tab, 0, t_tab + n * 8)
    mo += b''.join(struct.pack('<2I', *x) for x in oo) + b''.join(struct.pack('<2I', *x) for x in tt) + ids + strs
    open(os.path.join(LANG, '%s-%s.mo' % (DOMAIN, code)), 'wb').write(mo)
    # JSON for each script (WordPress looks for <domain>-<locale>-<md5 of relative path>.json)
    by_file = {}
    for e in entries:
        if not e['js'] or not T.get(e['msgid']):
            continue
        for ref in e['refs']:
            f = ref.rsplit(':', 1)[0]
            if f.endswith('.js'):
                by_file.setdefault(f, {})[e['msgid']] = forms(T[e['msgid']])
    for f, msgs in by_file.items():
        msgs[''] = {'domain': 'messages', 'lang': code, 'plural-forms': plural_header}
        data = {'translation-revision-date': '2026-11-21 00:00+0000', 'generator': 'SmokyPost build-languages', 'domain': 'messages', 'locale_data': {'messages': msgs}}
        name = '%s-%s-%s.json' % (DOMAIN, code, hashlib.md5(f.encode()).hexdigest())
        json.dump(data, open(os.path.join(LANG, name), 'w', encoding='utf-8'), ensure_ascii=False)
    return n - 1, len(by_file)

# Remove generated files of locales that are no longer listed.
wanted = set(LOCALES)
for fn in os.listdir(LANG):
    m = re.match(r'^%s-([A-Za-z_]+?)(?:-[0-9a-f]{32}\.json|\.po|\.mo)$' % DOMAIN, fn)
    if m and m.group(1) not in wanted:
        os.remove(os.path.join(LANG, fn))

failed = False
total = 0
for code, info in LOCALES.items():
    T = load(info['module'])
    problems = check(code, T)
    done, js = build(code, T)
    total += 1
    print('%-12s %d strings, %d translated, %d JSON file(s)%s' % (code, len(entries), done, js, '' if not problems else '  <-- %d PROBLEM(S)' % len(problems)))
    for p in problems:
        print('    ' + p)
        failed = True
print(total, 'locales')
if failed:
    sys.exit(1)
