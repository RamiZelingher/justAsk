"""Printable page of one question (question, hints, full solution) for a PDF.

usage: python3 question_pdf.py 200 he|en   -> q200_he.html; then print it to PDF with Chrome:
  google-chrome --headless --no-pdf-header-footer --print-to-pdf=q200.pdf q200_he.html
"""
import sqlite3, re, sys, base64, html, os

QID = int(sys.argv[1]) if len(sys.argv) > 1 else 200
LANG = sys.argv[2] if len(sys.argv) > 2 else 'he'
HERE = os.path.dirname(os.path.abspath(__file__))
DB = os.environ.get('JUSTASK_SQLITE', os.path.join(HERE, 'test.db'))   # made by tools/make_sqlite_test_db.py
MEDIA = os.environ.get('JUSTASK_MEDIA', os.path.join(HERE, '..', '..', 'media'))
c = sqlite3.connect(DB)
def tr(entity, eid, field, orig):
    if LANG == 'he':
        return orig
    row = c.execute("SELECT html FROM translations WHERE entity=? AND entity_id=? AND field=? AND lang=?", (entity, eid, field, LANG)).fetchone()
    return row[0] if row else orig

q = c.execute("""SELECT q.id, q.title, e.title, e.session, e.year, e.units FROM questions q LEFT JOIN exams e ON e.id = q.exam_id
                 WHERE q.id = ?""", (QID,)).fetchone()
subj = c.execute("""SELECT sn.name FROM question_subjects qs JOIN subject_names sn
                    ON sn.subject_id = qs.subject_id AND sn.lang = ? WHERE qs.question_id = ?""", (LANG, QID)).fetchone()
parts = c.execute("SELECT id, part_no, body_html, final_answer_html FROM question_parts WHERE question_id = ? ORDER BY part_no", (QID,)).fetchall()


def img_data(m):
    fn = m.group(1)[len('/media/'):]   # keeps subfolders such as 2006/QD2006SA01.gif
    p = os.path.join(MEDIA, fn)
    if not os.path.exists(p):
        return m.group(0)
    ext = fn.rsplit('.', 1)[-1].lower()
    mime = {'gif': 'image/gif', 'png': 'image/png', 'jpg': 'image/jpeg', 'jpeg': 'image/jpeg'}.get(ext, 'image/gif')
    return 'src="data:%s;base64,%s"' % (mime, base64.b64encode(open(p, 'rb').read()).decode())


def fix(h):
    h = re.sub(r'src="(/media/[^"]+)"', img_data, h or '')
    h = re.sub(r'<a class="th" href="[^"]*">(.*?)</a>', r'<span class="th">\1</span>', h, flags=re.S)
    return h


LBL = dict(he=dict(q='השאלה', h='רמזים', s='פתרון מלא', a='תשובה סופית', code='קוד שאלה = %d', exam='בגרות %s',
                   tag='שאלות בגרות במתמטיקה עם פתרונות מודרכים', foot='JustAsk · שאלה %d · הפתרון המלא והאינטראקטיבי באתר: question.php?id=%d',
                   dir='rtl', ttl='שאלה'),
           en=dict(q='The question', h='Hints', s='Full solution', a='Final answer', code='Question code = %d', exam='Bagrut %s',
                   tag='Math bagrut questions with guided solutions', foot='JustAsk · Question %d · Full interactive solution on the site: question.php?id=%d&amp;lang=en',
                   dir='ltr', ttl='Question'),
           ar=dict(q='السؤال', h='تلميحات', s='الحل الكامل', a='الإجابة النهائية', code='رمز السؤال = %d', exam='بجروت %s',
                   tag='أسئلة بجروت في الرياضيات مع حلول موجّهة', foot='JustAsk · السؤال %d · الحل الكامل والتفاعلي في الموقع: question.php?id=%d&amp;lang=ar',
                   dir='rtl', ttl='سؤال'))[LANG]
out = []
for pid, pno, body, final in parts:
    body = tr('part', pid, 'body', body); final = tr('part', pid, 'answer', final)
    hints = c.execute("SELECT id, body_html FROM hints WHERE part_id = ? ORDER BY CAST(seq AS INT)", (pid,)).fetchall()
    def real(m):   # drop placeholder math blocks such as "&nbsp; ."
        return m and re.sub(r'<[^>]+>|&nbsp;|[\s.]', '', m) != ''
    steps = c.execute("SELECT id, body_html, math_html FROM solution_steps WHERE part_id = ? ORDER BY CAST(seq AS INT)", (pid,)).fetchall()
    sec = ['<section class="q"><h2>%s</h2><div class="box qtext">%s</div></section>' % (LBL['q'], fix(body))]
    hints = [(tr('hint', i, 'body', h),) for i, h in hints]
    steps = [(tr('step', i, 'body', b), tr('step', i, 'math', m) if m else m) for i, b, m in steps]
    if hints:
        sec.append('<section><h2>%s</h2>%s</section>' % (LBL['h'], ''.join(
            '<div class="step hint"><span class="n">%d</span><div class="sb">%s</div></div>' % (i + 1, fix(h))
            for i, (h,) in enumerate(hints))))
    if steps:
        sec.append('<section class="sol"><h2>%s</h2>%s</section>' % (LBL['s'], ''.join(
            '<div class="step"><span class="n">%d</span><div class="sb">%s%s</div></div>'
            % (i + 1, fix(b), ('<div class="mb">%s</div>' % fix(m)) if real(m) else '')
            for i, (b, m) in enumerate(steps))))
    if final:
        sec.append('<section><h2>%s</h2><div class="box final">%s</div></section>' % (LBL['a'], fix(final)))
    out.append(''.join(sec))

MONTHS = {'ינואר': 'January', 'פברואר': 'February', 'מרץ': 'March', 'מאי': 'May', 'יוני': 'June', 'יולי': 'July',
          'אוגוסט': 'August', 'דצמבר': 'December', 'חורף': 'Winter', 'קיץ': 'Summer', 'מועד ב': 'Moed B', 'יחידות': 'units'}
# same wording as exam_label() in inc/i18n.php
SESSIONS = {'קיץ מועד א': 'Summer (1st sitting)', 'קיץ מועד ב': 'Summer (2nd sitting)', 'קיץ מועד מיוחד': 'Summer (special sitting)'}
ex = q[2] or ''
# Arabic: same wording as exam_label() in inc/i18n.php
AR = {'ינואר': 'كانون الثاني', 'פברואר': 'شباط', 'מאי': 'أيار', 'יוני': 'حزيران', 'יולי': 'تموز', 'אוגוסט': 'آب',
      'דצמבר': 'كانون الأول', 'קיץ': 'صيف', 'חורף': 'شتاء', 'קיץ מועד א': 'صيف (موعد أ)', 'קיץ מועד ב': 'صيف (موعد ب)',
      'קיץ מועד מיוחד': 'صيف (موعد خاص)'}
if LANG == 'ar' and q[4]:
    ex = (('%s وحدات، ' % q[5]) if q[5] else '') + ('%s %s' % (AR.get(q[3] or '', q[3] or ''), q[4])).strip()
elif LANG != 'he':
    sess, year, units = q[3] or '', q[4], q[5]
    if year:
        sess = SESSIONS.get(sess, MONTHS.get(sess, sess))
        ex = ('%s units, ' % units if units else '') + ('%s %s' % (sess, year)).strip()
    else:
        for k, v in MONTHS.items():
            ex = ex.replace(k, v)
        ex = re.sub(r'(units) (\w+ \d{4})', r'\1, \2', ex)
title = ex and (LBL['exam'] % ex) or ''
page = f"""<!DOCTYPE html><html lang="{LANG}" dir="{LBL['dir']}"><head><meta charset="utf-8">
<title>JustAsk · {LBL['ttl']} {QID}</title>
<style>
@page {{ size: A4; margin: 14mm 15mm 14mm; }}
* {{ box-sizing: border-box; }}
body {{ font-family: 'DejaVu Sans', 'FreeSans', sans-serif; font-size: 11.5pt; line-height: 1.65; color: #1b2333; margin: 0; }}
math {{ font-size: 1.08em; direction: ltr; }}
.head {{ display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2.5px solid #2453c9; padding-bottom: 8px; margin-bottom: 14px; }}
.brand {{ font-size: 22pt; font-weight: bold; color: #2453c9; letter-spacing: .5px; }}
.brand small {{ display: block; font-size: 9pt; font-weight: normal; color: #5d6678; letter-spacing: 0; }}
.meta {{ text-align: end; font-size: 9.5pt; color: #5d6678; }}
.tags {{ margin: 0 0 12px; }}
.tag {{ display: inline-block; background: #e8eefc; color: #2453c9; border-radius: 999px; padding: 2px 12px; font-size: 9.5pt; margin-inline-end: 6px; }}
.tag.code {{ background: #2453c9; color: #fff; font-weight: bold; }}
h2 {{ font-size: 13pt; color: #2453c9; margin: 18px 0 8px; padding-bottom: 3px; border-bottom: 1px solid #e2e6ee; break-after: avoid; }}
.box {{ border: 1px solid #e2e6ee; border-radius: 8px; padding: 10px 14px; background: #fafbfe; }}
.box {{ display: flow-root; }}   /* keeps floated tables (align=right) inside the box */
.qtext table {{ width: 100%; }} .qtext td {{ vertical-align: top; }}
img.fig, img {{ max-width: 100%; }}
.hints li {{ margin-bottom: 6px; break-inside: avoid; }}
.step {{ display: flex; gap: 10px; margin-bottom: 10px; }}
.mb, .step .sb > table {{ break-inside: avoid; }}
img.fig {{ zoom: 1.5; image-rendering: auto; }}\n.hint .n {{ background: #f0a020; }}\n.step .n {{ flex: 0 0 24px; height: 24px; border-radius: 50%; background: #2453c9; color: #fff; text-align: center; font-size: 9.5pt; line-height: 24px; margin-top: 2px; }}
.step .sb {{ flex: 1; }}
.mb {{ margin: 6px 0 2px; padding: 6px 12px; background: #f3f6fd; border-inline-start: 3px solid #2453c9; border-radius: 4px; direction: ltr; text-align: center; overflow-x: auto; }}
.th {{ color: #2453c9; border-bottom: 1px dotted #2453c9; }}
.final {{ background: #eef9f1; border-color: #bfe5c9; }}
.foot {{ break-before: avoid; margin-top: 10px; padding-top: 6px; border-top: 1px solid #e2e6ee; font-size: 8.5pt; color: #8a92a3; text-align: center; }}
</style></head><body>
<div class="head"><div class="brand">JustAsk<small>{LBL['tag']}</small></div>
<div class="meta">geo.yagur.site/justask</div></div>
<div class="tags"><span class="tag code">{LBL['code'] % QID}</span><span class="tag">{html.escape(title)}</span>{('<span class="tag">%s</span>' % html.escape(subj[0])) if subj else ''}</div>
{''.join(out)}
<div class="foot">{LBL['foot'] % (QID, QID)}</div>
</body></html>"""
open(os.path.join(HERE, f'q{QID}_{LANG}.html'), 'w', encoding='utf-8').write(page)
print('ok', len(page))
