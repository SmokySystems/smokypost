"""Print 'id|English' for every string, the list that translation files are written against.
Usage: python3 tools/translations/source.py   (needs php)"""
import hashlib, json, os, subprocess
ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
entries = json.loads(subprocess.check_output(['php', os.path.join(ROOT, 'tools/extract.php'), ROOT, 'smokypost']))
seen = set()
for e in entries:
    i = hashlib.md5(e['msgid'].encode()).hexdigest()[:6]
    assert i not in seen, 'id collision ' + i
    seen.add(i)
    s = e['msgid'].replace('\n', '\\n')
    if e['plural']:
        s += '  ¦  ' + e['plural'].replace('\n', '\\n')
    print(i + '|' + s)
