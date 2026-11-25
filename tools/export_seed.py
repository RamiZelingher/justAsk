"""Export one subject and selected questions from the legacy Units45 export (.gsa)
into db/seed.sql for MySQL, and copy the images they use into static/media.

Usage:
  python3 tools/export_seed.py <units45.gsa> <legacy image folder> [--french <declic1.gsa>] [--dialect mysql|sqlite]

Legacy inline references are converted to plain HTML:
  *E12345*            -> embedded MathML
  *IQGE012_1*         -> <img src="/media/QGE012_1.gif">
  *SP9DGE410(text)*   -> <a class="th" href="/theory/S9DGE410">text</a>
"""
import json, os, re, shutil, sys
sys.path.insert(0, os.path.dirname(__file__))
from gsa import read_records, decode_val

# ---- What to export (stage 1: all subject names, one subject's questions) ----
# all questions of the 2006 backup; each goes to the subjects it was linked to in the legacy database
QUESTIONS = None
LEGACY_ICONS = {'intersol.gif', 'search.gif', 'book01.gif', 'note05.gif'}

gsa_path, img_dir = sys.argv[1], sys.argv[2]
dialect = sys.argv[sys.argv.index('--dialect') + 1] if '--dialect' in sys.argv else 'mysql'
french_path = sys.argv[sys.argv.index('--french') + 1] if '--french' in sys.argv else None
import html as _html
from subject_names import HE, FR, CURRICULA
from content_i18n import load_memory, translate as tr_html, translate_math
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MEDIA_OUT = os.path.join(ROOT, 'media')

_, recs, _, _ = read_records(gsa_path)
D = {r: v for r, v in recs}
# images: a folder (walked recursively, e.g. Graphics/2005/x.gif), or a text file listing "relative/path<TAB>bytes"
# (used when the pictures stay on the user's computer and are copied there directly)
img_sizes = {}
if os.path.isdir(img_dir):
    for dp, _, fs in os.walk(img_dir):
        for f in fs:
            rel = os.path.relpath(os.path.join(dp, f), img_dir).replace(os.sep, '/')
            img_sizes[rel] = os.path.getsize(os.path.join(dp, f))
else:
    for line in open(img_dir, encoding='utf-8'):
        if '\t' in line:
            rel, size = line.rstrip('\n').split('\t')
            img_sizes[rel] = int(size)
img_files = {f.lower(): f for f in img_sizes}


def find_img(fn):
    """Image file for a legacy name: case-insensitive; the 2006-2008 data also has names starting with '/'
    and names without an extension, which the old site served as .gif."""
    f = (fn or '').strip().lstrip('/').lower()
    return img_files.get(f) or (img_files.get(f + '.gif') if f and '.' not in f.rsplit('/', 1)[-1] else None)


def node(ref):
    v = D.get(ref)
    return None if v is None else decode_val(v)


def children(parent):
    pat = re.compile(re.escape(parent[:-1]) + r',(\d+)\)$')
    return sorted(int(m.group(1)) for r in D for m in [pat.match(r)] if m)


def obj(oid):
    return node('^JustAskooObjectD("%s")' % oid)


def pointer_target(oid):
    v = node('^JustAskooObjectD("%s","Topics",7,"ObjectTopicReferences",1)' % oid)
    return v[0] if isinstance(v, list) and v else None


def clean_mathml(m):
    m = re.sub(r"<mstyle\s+(?:math)?color=['\"]([^'\"]+)['\"]>", r'<mstyle style="color:\1">', m)
    def fence(mo):
        a = mo.group(1)
        op = re.search(r"open=['\"]([^'\"]*)['\"]", a); cl = re.search(r"close=['\"]([^'\"]*)['\"]", a)
        return '<mrow><mo>%s</mo>%s<mo>%s</mo></mrow>' % (op.group(1) if op else '(', mo.group(2), cl.group(1) if cl else ')')
    for _ in range(5):
        m = re.sub(r'<mfenced([^>]*)>((?:(?!<mfenced).)*?)</mfenced>', fence, m, flags=re.S)
    return m.replace('&quad;', '&emsp;')


def is_placeholder_math(m):
    return re.sub(r'<[^>]+>', '', m or '').strip() in ('', 'New', '.', ',')


media, theory_wanted, missing_media = set(), set(), set()
flash_used = set()


class Ctx:
    def __init__(self):
        self.theory = set()


def render(text, ctx):
    if not text:
        return ''
    out, i, n = [], 0, len(text)
    while i < n:
        if text[i] != '*':
            out.append(text[i]); i += 1; continue
        m = re.match(r'\*([A-Za-z0-9_./\-]+)(\(|\*)', text[i:])
        if not m:
            out.append('*'); i += 1; continue
        oid = m.group(1)
        if m.group(2) == '*':
            out.append(render_obj(oid, ctx)); i += m.end(); continue
        j = i + m.end(); level, k = 1, i + m.end()
        while k < n:
            if text[k] == '(':
                level += 1
            elif text[k] == ')':
                level -= 1
                if level == 0 and k + 1 < n and text[k + 1] == '*':
                    break
            k += 1
        out.append(link(oid, render(text[j:k], ctx), ctx))
        i = k + 2
    return ''.join(out)


def link(oid, label_html, ctx):
    target = pointer_target(oid) or oid
    if obj(target) is None:
        return label_html
    ctx.theory.add(target); theory_wanted.add(target)
    return '<a class="th" href="/theory/%s">%s</a>' % (target, label_html)


ANIM_LABEL = 'הנפשה'


def has_flash(oid, depth=0):
    """True when object oid is (or embeds) a Flash movie."""
    o = obj(oid)
    if not isinstance(o, list) or len(o) < 8 or depth > 3:
        return False
    if o[7] == '8' and (o[2] or '').lower().endswith('.swf'):
        return True
    return any(has_flash(m, depth + 1) for m in re.findall(r'\*([A-Za-z0-9_./\-]+)\*', o[0] or ''))


def render_obj(oid, ctx):
    o = obj(oid)
    if not isinstance(o, list) or len(o) < 8:
        return ''
    t = o[7]
    tgt = pointer_target(oid)
    if tgt and has_flash(tgt):
        # pointer to an animation page (shown in the old site as an icon opening a popup):
        # show the animation in place, folded until the reader opens it
        to = obj(tgt)
        return '<details class="anim"><summary>%s</summary>%s</details>' % (
            ANIM_LABEL, tidy(render(to[0], ctx)))
    if t == '2':
        return clean_mathml(o[0])
    if t == '3':
        fn = o[2] or o[4]
        if (fn or '').lower() in LEGACY_ICONS:
            # the old site's note/example icon opened a popup with a sibling text object
            # (*IS9DAG020N* -> S9DAG020N): show that text in place
            sib = obj(oid[1:]) if oid.startswith('I') else None
            if NOTES and isinstance(sib, list) and len(sib) > 7 and sib[7] == '1' and (sib[0] or '').strip():
                return '<div class="tnote">%s<!--/tnote--></div>' % tidy(render(sib[0], ctx))
            # the old site showed an icon that opened a popup with the real picture (item 4)
            fn = o[4] if o[4] and o[4].lower() not in LEGACY_ICONS | {'309a.gif'} else ''
            if not fn:
                return ''
        real = find_img(fn)
        if not real:
            missing_media.add(fn)
            return ''
        media.add(real)
        return '<img class="fig" src="/media/%s" alt="">' % real
    if t == '1':
        if o[0].startswith(':1:'):
            tgt = pointer_target(oid)
            return link(oid, (obj(tgt) or [''] * 7)[6] or '', ctx) if tgt else ''
        return render(o[0], ctx)
    if t == '8' and (o[2] or '').lower().endswith('.swf'):
        # Flash animation / applet, played in the browser by Ruffle (see inc/layout.php)
        fn = o[2].split('/')[-1]
        flash_used.add(fn)
        h, w = int(o[5] or 300), int(o[8] or 400)
        def img(mo):   # pictures loaded by the movie: serve them from media/ like the other figures
            real = img_files.get(mo.group(1).lower())
            if real:
                media.add(real)
                return 'media/' + real
            missing_media.add(mo.group(1))
            return mo.group(0)
        data = re.sub(r'img/graphics/([^",<>]+)', img, o[0] or '', flags=re.I)
        return '<div class="swf" data-swf="%s" data-w="%d" data-h="%d" data-xml="%s"></div>' % (
            _html.escape(fn), w, h, _html.escape(data, quote=True))
    return ''  # live-math / file objects are not supported in the new site


NOTES = False   # True while rendering theory bodies; False: leave the note/example texts out (fallback body for translations)
NOTE_HEAD = r'(?:דוגמא|דוגמה|דוגמאות|הערה|הערות)(?:\s*\d+)?'


def fix_notes(h):
    """Theory bodies: move note/example text out of its bold heading, drop headings left empty."""
    h = re.sub(r'(<u>\s*<b>\s*' + NOTE_HEAD + r'\s*:?)\s*(<div class="tnote">.*?)(</b>\s*</u>)',
               lambda m: m.group(1).rstrip() + m.group(3) + '\n' + m.group(2).strip(), h, flags=re.S)
    h = re.sub(r'(<u>\s*<b>\s*' + NOTE_HEAD + r'\s*:?)\s*(?=<div class="tnote">)',
               lambda m: m.group(1).rstrip() + '</b></u>\n', h)   # heading never closed in the source
    # a heading with nothing after it (up to the next heading or the end)
    empty = r'(?:\s|<br\s*/?>|&nbsp;)*'
    for _ in range(3):
        h = re.sub(r'(?:<br\s*/?>\s*)?<u>\s*<b>\s*' + NOTE_HEAD + r'\s*:?\s*</b>\s*</u>' + empty +
                   r'(?=<u>|$)', '', h, flags=re.S)
    h = re.sub(r'(?:\s*<br\s*/?>)+\s*$', '', h)
    return h.replace('<!--/tnote-->', '').strip()


def tidy(h):
    h = re.sub(r'\s(width|height)="?\d+%?"?', '', h)
    h = h.replace('<div align="left"></div>', '')
    return h.strip()


# ---- Collect --------------------------------------------------------------
# questions -> legacy subjects
q_subjects = {}
for r in D:
    m = re.match(r'\^Relationship\("Question","Subject","([^"]+)","?([^")]+)"?\)$', r)
    if m:
        q_subjects.setdefault(m.group(1), set()).add(m.group(2))
QUESTIONS = sorted(set(m.group(1) for r in D for m in [re.match(r'\^JustAskooQuestionD\("([^"]+)"\)$', r)] if m))
NEW_SESSIONS = {'SA': 'קיץ מועד א', 'SB': 'קיץ מועד ב', 'SC': 'קיץ מועד מיוחד', 'WA': 'חורף'}
SESSION_ORDER = {'ינואר': 1, 'חורף': 1, 'מאי': 2, 'יוני': 3, 'קיץ': 3, 'קיץ מועד א': 3, 'יולי': 4, 'קיץ מועד ב': 4, 'קיץ מועד מיוחד': 4.5, 'דצמבר': 5}

exams, questions = {}, []
for qid in QUESTIONS:
    head = node('^JustAskooQuestionD("%s")' % qid)
    subs = q_subjects.get(qid, set())
    units = int(head[0]) if str(head[0]).isdigit() else (int(sorted(subs)[0][0]) if subs else 4)
    src_id = str(head[7])
    m_q = re.match(r'([DEFG])\d{4}[SW][ABC]$', src_id)
    if m_q:   # new-program questionnaires: D, E = 4 units, F, G = 5 units (the legacy units field is unreliable there)
        units = 4 if m_q.group(1) in 'DE' else 5
    if src_id not in exams:
        src = node('^JustAskooSourceD(%s)' % (src_id if src_id.isdigit() else '"%s"' % src_id))
        if src:
            title = src[2]
            ym = re.search(r'(19|20)\d\d', title)
            sess = re.search(r'יחידות\s+(.+?)\s+(?:19|20)\d\d', title)
            sess = sess.group(1) if sess else None
            if not sess:   # new program: "שאלון ז' קיץ 2005 - מועד ב'"
                sess = 'חורף' if 'חורף' in title else ('קיץ מועד ב' if 'מועד ב' in title else ('קיץ מועד מיוחד' if 'מועד מיוחד' in title else ('קיץ מועד א' if 'קיץ' in title else None)))
            exams[src_id] = dict(title=title, units=units, year=int(ym.group(0)) if ym else None, session=sess)
        else:   # 2004-2005 exams (new program), e.g. D2004SA = questionnaire D, summer 2004, sitting A
            m = re.match(r'([A-Z])(\d{4})([SW][ABC])$', src_id)
            year, sess = (int(m.group(2)), NEW_SESSIONS.get(m.group(3))) if m else (None, None)
            title = '%d יחידות %s %s, שאלון %s' % (units, sess or '', year or '', m.group(1) if m else src_id)
            exams[src_id] = dict(title=re.sub(r'\s+', ' ', title), units=units, year=year, session=sess)
    ctx = Ctx()
    parts = []
    pbase = '^JustAskooQuestionD("%s","QuestionParts")' % qid
    for p in children(pbase):
        part = node(pbase[:-1] + ',%d)' % p)
        sref = pbase[:-1] + ',%d,"Solutions",1)' % p
        sol = node(sref)
        hints = [tidy(render(node(sref[:-1] + ',"SolutionDirections",%d)' % k)[0], ctx))
                 for k in children(sref[:-1] + ',"SolutionDirections")')]
        steps = []
        for k in children(sref[:-1] + ',"SolutionSteps")'):
            s = node(sref[:-1] + ',"SolutionSteps",%d)' % k)
            steps.append((tidy(render(s[2], ctx)), '' if is_placeholder_math(s[3]) else clean_mathml(s[3])))
        parts.append(dict(no=p, body=tidy(render(part[0], ctx)),
                          answer=tidy(render(sol[7], ctx)) if sol else '', hints=hints, steps=steps))
    questions.append(dict(legacy=qid, exam=src_id, no=head[8] or None, units=units,
                          parts=parts, theory=set(ctx.theory), subjects=subs))

# theory linked to subjects in the legacy database (definitions, theorems, methods, known results)
TOPIC_KIND = {1: 'definition', 2: 'theorem', 3: 'method', 4: 'other'}
obj_subjects, obj_topic = {}, {}
for r in D:
    m = re.match(r'\^Relationship\("Object","Subject","([^"]+)","?([^")]+)"?\)$', r)
    if m:
        obj_subjects.setdefault(m.group(1).strip(), set()).add(m.group(2))
    m = re.match(r'\^Relationship\("Object","Topic","([^"]+)",(\d+)\)$', r)
    if m:
        obj_topic[m.group(1).strip()] = int(m.group(2))
obj_subjects = {o: s for o, s in obj_subjects.items() if obj(o) is not None and obj_topic.get(o) in TOPIC_KIND}
theory_wanted |= set(obj_subjects)

# theory items, rendered until no new links appear
theory = {}
while True:
    todo = sorted(theory_wanted - set(theory))
    if not todo:
        break
    for tid in todo:
        o = obj(tid)
        kind = TOPIC_KIND.get(obj_topic.get(tid)) or ({'D': 'definition', 'T': 'theorem', 'M': 'method'}.get(tid[2:3], 'other') if tid.startswith('S9') else 'other')
        NOTES = True
        theory[tid] = dict(kind=kind, title=(o[6] if len(o) > 6 else '') or tid, body=fix_notes(tidy(render(o[0], Ctx()))))
        NOTES = False
        theory[tid]['plain'] = fix_notes(tidy(render(o[0], Ctx())))

# ---- Write SQL --------------------------------------------------------------
def q(v):
    if v is None:
        return 'NULL'
    if isinstance(v, int):
        return str(v)
    s = str(v).replace("'", "''")
    if dialect == 'mysql':
        s = s.replace('\\', '\\\\')
    return "'%s'" % s


rows = []
def ins(table, cols, vals):
    rows.append('INSERT INTO %s (%s) VALUES (%s);' % (table, ', '.join(cols), ', '.join(q(v) for v in vals)))

LANGS = [('he', 'עברית', 'rtl', 1), ('en', 'English', 'ltr', 2), ('fr', 'Français', 'ltr', 3)]
for code, name, d, so in LANGS:
    ins('languages', ['code', 'name', 'dir', 'sort_order'], [code, name, d, so])
cur_ids = {}
for n, (code, clang, units, so, names) in enumerate(CURRICULA, 1):
    cur_ids[code] = n
    ins('curricula', ['id', 'code', 'content_lang', 'units', 'sort_order'], [n, code, clang, units, so])
    for lang, (nm, desc) in names.items():
        ins('curriculum_names', ['curriculum_id', 'lang', 'name', 'description'], [n, lang, nm, desc])


def subject_tree(records):
    out = {}
    for r, v in records:
        m = re.fullmatch(r'\^JustAskooSubjectD\("?([^")]+)"?\)', r)
        if m:
            l = decode_val(v)
            out[m.group(1)] = (_html.unescape(l[1]).strip(), (l[2] if len(l) > 2 else '') or '')
    return out


def slugify(t):
    return re.sub(r'[^a-z0-9]+', '-', t.lower()).strip('-')


trees = []  # (curriculum code, legacy id, parent legacy id, {lang: name}, original lang, units)
for lid, (name, parent) in sorted(subject_tree(recs).items()):
    units = 4 if lid.startswith('4') else 5
    he, en, fr = HE[name]
    trees.append(('bagrut-%d' % units, lid, parent, {'he': he, 'en': en, 'fr': fr}, 'he', units))
if french_path:
    _, frecs, _, _ = read_records(french_path)
    for lid, (name, parent) in sorted(subject_tree(frecs).items()):
        fr, en, he = FR[lid]
        trees.append(('fr-1s', lid, parent, {'he': he, 'en': en, 'fr': fr}, 'fr', None))

subject_ids, slugs = {}, set()
for n, (cur, lid, parent, names, orig, units) in enumerate(trees, 1):
    subject_ids[(cur, lid)] = n
# study order = place among siblings (same curriculum and parent), starting from the legacy order
study_order, _counter = {}, {}
for i, (cur, lid, parent, names, orig, units) in enumerate(trees):
    key = (cur, parent or '')
    _counter[key] = _counter.get(key, 0) + 1
    study_order[i] = _counter[key]
# insert parents before their children (MySQL checks the parent link on every row)
order = sorted(range(len(trees)), key=lambda i: (bool(trees[i][2]), i))
for i in order:
    n = i + 1
    cur, lid, parent, names, orig, units = trees[i]
    slug = ('%s-%s' % (units, slugify(names['en']))) if units else 'fr-' + slugify(names['en'])
    if slug in slugs:
        slug += '-' + lid.lower()
    slugs.add(slug)
    ins('subjects', ['id', 'curriculum_id', 'parent_id', 'legacy_id', 'units', 'slug', 'study_order'],
        [n, cur_ids[cur], subject_ids.get((cur, parent)), lid, units, slug, study_order[i]])
    for lang in ('he', 'en', 'fr'):
        ins('subject_names', ['subject_id', 'lang', 'name', 'is_original', 'is_reviewed'],
            [n, lang, names[lang], 1 if lang == orig else 0, 0])
exam_ids = {}
def exam_key(e):
    return (e['year'] or 0, SESSION_ORDER.get(e['session'] or '', 9), e['units'] or 0, e['title'])
for n, (lid, e) in enumerate(sorted(exams.items(), key=lambda x: exam_key(x[1])), 1):
    exam_ids[lid] = n
    ins('exams', ['id', 'legacy_id', 'title', 'source_type', 'units', 'year', 'session'], [n, lid, e['title'], 'bagrut', e['units'], e['year'], e['session']])
# English (and later other) translations of the content, from the translation memory tools/translations/<lang>.json
MEMS = {lang: load_memory(lang) for lang in ('en',)}
MEMS = {k: v for k, v in MEMS.items() if v}
n_tr = {}
def add_tr(entity, eid, lang, **fields):
    for f, text in fields.items():
        ins('translations', ['entity', 'entity_id', 'field', 'lang', 'html'], [entity, eid, f, lang, text])
    n_tr[(entity, lang)] = n_tr.get((entity, lang), 0) + 1

theory_ids = {}
for n, (tid, t) in enumerate(sorted(theory.items()), 1):
    theory_ids[tid] = n
    ins('theory_items', ['id', 'legacy_id', 'kind', 'title', 'body_html'], [n, tid, t['kind'], t['title'], t['body']])
    for lang, mem in MEMS.items():
        title, m1 = tr_html(t['title'], mem)
        body, m2 = tr_html(t['body'], mem)
        if m2:   # note/example texts not translated yet: the translated body without them
            body, m2 = tr_html(t['plain'], mem)
        if not m1 and not m2:
            add_tr('theory', n, lang, title=title, body=body)
part_id = hint_id = step_id = 0
questions.sort(key=lambda x: (exam_key(exams[x['exam']]), x['no'] or 0, x['legacy']))
subj_pos = {}
for n, qq in enumerate(questions, 1):
    e = exams[qq['exam']]
    ins('questions', ['id', 'legacy_id', 'exam_id', 'question_no', 'units', 'title', 'status'],
        [n, qq['legacy'], exam_ids[qq['exam']], qq['no'], qq['units'], 'בגרות %s, שאלה %s' % (e['title'], qq['no']), 'published'])
    for lid in sorted(qq['subjects']):
        sid = subject_ids.get(('bagrut-%s' % lid[0], lid))
        if sid:
            subj_pos[sid] = subj_pos.get(sid, 0) + 1
            ins('question_subjects', ['question_id', 'subject_id', 'position'], [n, sid, subj_pos[sid]])
    for tid in sorted(qq['theory']):
        ins('question_theory', ['question_id', 'theory_id'], [n, theory_ids[tid]])
    done = {lang: True for lang in MEMS}
    for p in qq['parts']:
        part_id += 1
        ins('question_parts', ['id', 'question_id', 'part_no', 'body_html', 'final_answer_html'], [part_id, n, p['no'], p['body'], p['answer']])
        for lang, mem in MEMS.items():
            body, m1 = tr_html(p['body'], mem)
            ans, m2 = tr_html(p['answer'], mem)
            if m1 or m2:
                done[lang] = False
            else:
                add_tr('part', part_id, lang, body=body, answer=ans)
        for k, h in enumerate(p['hints'], 1):
            hint_id += 1
            ins('hints', ['id', 'part_id', 'seq', 'body_html'], [hint_id, part_id, k, h])
            for lang, mem in MEMS.items():
                body, m = tr_html(h, mem)
                if m:
                    done[lang] = False
                else:
                    add_tr('hint', hint_id, lang, body=body)
        for k, (body, math) in enumerate(p['steps'], 1):
            step_id += 1
            ins('solution_steps', ['id', 'part_id', 'seq', 'body_html', 'math_html'], [step_id, part_id, k, body, math or None])
            for lang, mem in MEMS.items():
                tb, m = tr_html(body, mem)
                if m:
                    done[lang] = False
                else:
                    add_tr('step', step_id, lang, body=tb, math=translate_math(math) if math else '')
    for lang, ok in done.items():
        if ok:   # every part, hint and step of the question is translated: show it on the pages of this language
            ins('question_languages', ['question_id', 'lang'], [n, lang])
            n_tr[('question', lang)] = n_tr.get(('question', lang), 0) + 1

# theory of each subject: legacy links (main topics) + theory used by the subject's questions (subtopics)
subj_theory = {}
for tid, subs in obj_subjects.items():
    for lid in subs:
        units = 4 if lid.startswith('4') else 5
        sid = subject_ids.get(('bagrut-%d' % units, lid))
        if sid:
            subj_theory.setdefault(sid, set()).add(tid)
for qq in questions:
    for lid in qq['subjects']:
        sid = subject_ids.get(('bagrut-%s' % lid[0], lid))
        if sid:
            subj_theory.setdefault(sid, set()).update(qq['theory'])
KIND_ORDER = {'definition': 1, 'theorem': 2, 'method': 3, 'other': 4}
n_links = 0
for sid in sorted(subj_theory):
    items = sorted(subj_theory[sid], key=lambda t: (KIND_ORDER[theory[t]['kind']], t))
    for pos, tid in enumerate(items, 1):
        ins('subject_theory', ['subject_id', 'theory_id', 'position'], [sid, theory_ids[tid], pos])
        n_links += 1

os.makedirs(MEDIA_OUT, exist_ok=True)
for fn in sorted(media):
    if os.path.isdir(img_dir):
        os.makedirs(os.path.dirname(os.path.join(MEDIA_OUT, fn)), exist_ok=True)
        shutil.copyfile(os.path.join(img_dir, fn), os.path.join(MEDIA_OUT, fn))
    ext = fn.rsplit('.', 1)[-1].lower()
    ins('media', ['filename', 'mime_type', 'bytes'], [fn, {'jpg': 'image/jpeg', 'png': 'image/png'}.get(ext, 'image/gif'), img_sizes[fn]])

# question PDF / video lesson files (tools/question_files.json: {question id: {"pdf": file, "video": file}})
qf_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'question_files.json')
if os.path.exists(qf_path):
    for qid, f in sorted(json.load(open(qf_path)).items(), key=lambda kv: int(kv[0])):
        rows.append("UPDATE questions SET pdf_file = %s, video_file = %s WHERE id = %d;" % (
            "'%s'" % f['pdf'] if f.get('pdf') else 'NULL', "'%s'" % f['video'] if f.get('video') else 'NULL', int(qid)))

# site settings (tools/site_settings.json): footer version, feedback address
ss_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'site_settings.json')
if os.path.exists(ss_path):
    for k, v in json.load(open(ss_path)).items():
        ins('settings', ['name', 'value'], [k, v])

head = '-- Seed data: %d subjects in 3 languages, %d questions (generated by tools/export_seed.py)\n' % (len(trees), len(questions))
if dialect == 'mysql':
    head += 'USE justask;\nSET NAMES utf8mb4;\n'
out = os.path.join(ROOT, 'db', 'seed.sql' if dialect == 'mysql' else 'seed.sqlite.sql')
open(out, 'w', encoding='utf-8').write(head + '\n'.join(rows) + '\n')
print('translations', dict((k[0] + '/' + k[1], v) for k, v in n_tr.items()))
print('questions', len(questions), 'parts', part_id, 'hints', hint_id, 'steps', step_id,
      'theory', len(theory), 'subject_theory', n_links, 'images', len(media), '->', out)
if missing_media:
    print('MISSING IMAGES:', ' '.join(sorted(missing_media)))
print('FLASH FILES USED:', len(flash_used), ' '.join(sorted(flash_used)), file=sys.stderr)
