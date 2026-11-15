"""Sentence-level translation of the legacy Hebrew HTML content.

The HTML of a question / hint / solution step / theory item is cut into text runs at block
boundaries (<br>, <p>, <td>, <li> ...). Inside a run every formula (<math>...</math>), image
and inline tag (<a ...>, <b>, <u>, </a> ...) is replaced by a numbered placeholder ⟦1⟧, ⟦2⟧ ...
so the same sentence with different formulas becomes one key, and the formulas are copied
back untouched. A translation memory (translations/<lang>.json) maps key -> translated key.

  python3 tools/content_i18n.py todo <seed.sqlite db> <lang> <out.json> [--subject slug ...]
        lists the runs that still need translating
"""
import json, os, re, sys

HEB = re.compile(r'[֐-׿]')
BLOCK = re.compile(r'(<br\s*/?>|</br>|</?(?:p|div|table|tbody|tr|td|th|li|ol|ul|center|details|summary|h[1-6])\b[^>]*>)', re.I)
TOKEN = re.compile(r'(<math\b.*?</math>|<[^>]+>)', re.S | re.I)
MEM_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'translations')


def runs(html):
    """Yield the pieces of html: block separators and text runs (in order)."""
    return BLOCK.split(html or '')


def to_key(run):
    """'נתונה <math>..</math> ...' -> ('נתונה ⟦1⟧ ...', [token1, ...]) with collapsed whitespace."""
    tokens = []
    def sub(m):
        tokens.append(m.group(0))
        return '⟦%d⟧' % len(tokens)
    key = TOKEN.sub(sub, run)
    lead = re.match(r'\s*', key).group(0)
    trail = re.search(r'\s*$', key).group(0)
    return re.sub(r'\s+', ' ', key).strip(), tokens, lead, trail


def needs(run):
    key, _, _, _ = to_key(run)
    return bool(HEB.search(key))


def load_memory(lang):
    p = os.path.join(MEM_DIR, lang + '.json')
    return json.load(open(p, encoding='utf-8')) if os.path.exists(p) else {}


def save_memory(lang, mem):
    os.makedirs(MEM_DIR, exist_ok=True)
    p = os.path.join(MEM_DIR, lang + '.json')
    json.dump(mem, open(p, 'w', encoding='utf-8'), ensure_ascii=False, indent=0, sort_keys=True)


# Hebrew words inside formulas, stored visually reversed by the legacy editor (e.g. "וא" is "או" = "or")
MATH_WORDS = {
    'וא': 'or', 'ו': 'and', 'םגו': 'and', 'םא': 'if', 'אל': 'not', 'וכו': 'etc.',
    'מ"ס': 'cm', 'ק"מס': 'cm³', 'ש"מק': 'km/h', 'םימעפ': 'times', 'זפרט': 'trapezoid',
    'לשמל': 'for example', 'םסוח': 'bounded', 'םוסח': 'bounded', 'ףקיה': 'perimeter',
    'םילקש': 'shekels', 'לקש': 'shekel', 'תפטעמ': 'envelope', 'תועש': 'hours', 'ןמותמ': 'octagon',
    'תיבויח': 'positive', 'הלוע': 'increasing', 'תותיכ': 'classes', 'ןמיס': 'sign',
    'םיאתמ': 'corresponding', 'םייגוז יא': 'odd', 'םידבוע': 'workers', 'ןייצמ': 'denotes',
    'שרושה': 'the root', 'ךירעמ': 'exponent', 'הקזחה': 'the power', 'רפסמ': 'number',
    'לוגיע': 'rounding', 'הרזג': 'derivative',
}
_MW = re.compile('|'.join(re.escape(w) for w in sorted(MATH_WORDS, key=len, reverse=True)))


def translate_math(html):
    """Replace the Hebrew words inside <math> elements (English pages only)."""
    def fix(m):
        return _MW.sub(lambda w: MATH_WORDS[w.group(0)], m.group(0))
    return re.sub(r'<math\b.*?</math>', fix, html or '', flags=re.S)


def translate(html, mem):
    """Return (translated html, missing keys). Translation is complete when missing is empty."""
    out, missing = [], []
    for i, piece in enumerate(runs(html)):
        if i % 2 == 1 or not needs(piece):          # separators and runs without Hebrew stay as they are
            out.append(piece)
            continue
        key, tokens, lead, trail = to_key(piece)
        tr = mem.get(key)
        if tr is None:
            missing.append(key)
            out.append(piece)
            continue
        tr = re.sub(r'⟦(\d+)⟧', lambda m: tokens[int(m.group(1)) - 1] if int(m.group(1)) <= len(tokens) else '', tr)
        out.append(lead + tr + trail)
    return translate_math(''.join(out)), missing


def keys_of(html):
    return [to_key(p)[0] for i, p in enumerate(runs(html)) if i % 2 == 0 and needs(p)]


if __name__ == '__main__' and sys.argv[1:2] == ['todo']:
    import sqlite3
    db, lang, out = sys.argv[2:5]
    slugs = sys.argv[sys.argv.index('--subject') + 1:] if '--subject' in sys.argv else None
    c = sqlite3.connect(db)
    mem = load_memory(lang)
    if slugs:
        sids = [r[0] for r in c.execute('SELECT id FROM subjects WHERE slug IN (%s)' % ','.join('?' * len(slugs)), slugs)]
        ph = ','.join(map(str, sids))
        qids = [r[0] for r in c.execute('SELECT question_id FROM question_subjects WHERE subject_id IN (%s)' % ph)]
        tids = set(r[0] for r in c.execute('SELECT theory_id FROM subject_theory WHERE subject_id IN (%s)' % ph))
    else:
        qids = [r[0] for r in c.execute('SELECT id FROM questions')]
        tids = set(r[0] for r in c.execute('SELECT id FROM theory_items'))
    ql = ','.join(map(str, qids)) or '0'
    tids |= set(r[0] for r in c.execute('SELECT theory_id FROM question_theory WHERE question_id IN (%s)' % ql))
    tl = ','.join(map(str, tids)) or '0'
    htmls = []
    for sql in ['SELECT body_html FROM question_parts WHERE question_id IN (%s)' % ql,
                'SELECT final_answer_html FROM question_parts WHERE question_id IN (%s)' % ql,
                'SELECT h.body_html FROM hints h JOIN question_parts p ON p.id = h.part_id WHERE p.question_id IN (%s)' % ql,
                'SELECT s.body_html FROM solution_steps s JOIN question_parts p ON p.id = s.part_id WHERE p.question_id IN (%s)' % ql,
                'SELECT title FROM theory_items WHERE id IN (%s)' % tl,
                'SELECT body_html FROM theory_items WHERE id IN (%s)' % tl]:
        htmls += [r[0] for r in c.execute(sql)]
    todo = []
    for h in htmls:
        for k in keys_of(h):
            if k not in mem and k not in todo:
                todo.append(k)
    json.dump(todo, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=0)
    print(len(todo), 'runs to translate ->', out)
