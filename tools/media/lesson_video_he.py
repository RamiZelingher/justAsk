"""Lesson video for question 200: page states rendered with headless Chrome, assembled with ffmpeg."""
import os, json, math, subprocess, shutil, sys
from playwright.sync_api import sync_playwright

W, H, FPS = 1920, 1080, 30
ROOT = os.environ.get('LESSON_OUT', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'out'))
os.makedirs(ROOT, exist_ok=True)
FR = os.path.join(ROOT, 'frames')

# ---------- MathML helpers ----------
def mi(s): return f'<mi>{s}</mi>'
def mn(s): return f'<mn>{s}</mn>'
def mo(s): return f'<mo>{s}</mo>'
def r(*a): return '<mrow>' + ''.join(a) + '</mrow>'
def sup(b, e): return f'<msup>{b}{e}</msup>'
def sub(b, e): return f'<msub>{b}{e}</msub>'
def frac(a, b): return f'<mfrac>{a}{b}</mfrac>'
def M(*a): return '<math>' + ''.join(a) + '</math>'
def p(*a): return r(mo('('), *a, mo(')'))
E2X = sup(mi('e'), r(mn('2'), mi('x')))
FX = r(mi('f'), p(mi('x')))
GX = r(mi('g'), p(mi('x')))
INT = '<msubsup><mo>&#x222B;</mo><mn>0</mn><mn>2</mn></msubsup>'
DX = r(mi('d'), mi('x'))
EV = '<msubsup><mo stretchy="true">|</mo><mn>0</mn><mn>2</mn></msubsup>'

f_def = M(FX, mo('='), E2X, mo('+'), mn('6'))
f_der = M(r(mi('f'), mo('&#x2032;'), p(mi('x'))), mo('='), mn('2'), E2X)
m_val = M(mi('m'), mo('='), r(mi('f'), mo('&#x2032;'), p(mn('0'))), mo('='), mn('2'), sup(mi('e'), mn('0')), mo('='), mn('2'))
y_val = M(sub(mi('y'), mn('1')), mo('='), r(mi('f'), p(mn('0'))), mo('='), sup(mi('e'), mn('0')), mo('+'), mn('6'), mo('='), mn('7'))
pt = M(p(mn('0'), mo(','), mn('7')))
line_gen = M(mi('y'), mo('&#x2212;'), sub(mi('y'), mn('1')), mo('='), mi('m'), p(mi('x'), mo('&#x2212;'), sub(mi('x'), mn('1'))))
line_sub = M(mi('y'), mo('&#x2212;'), mn('7'), mo('='), mn('2'), p(mi('x'), mo('&#x2212;'), mn('0')))
line_fin = M(mi('y'), mo('='), mn('2'), mi('x'), mo('+'), mn('7'))
g_def = M(GX, mo('='), mn('2'), mi('x'), mo('+'), mn('7'))
S_def = M(mi('S'), mo('='), INT, r(mo('['), FX, mo('&#x2212;'), GX, mo(']')), DX)
S_sub = M(mo('='), INT, p(E2X, mo('+'), mn('6'), mo('&#x2212;'), mn('2'), mi('x'), mo('&#x2212;'), mn('7')), DX)
S_simp = M(mo('='), INT, p(E2X, mo('&#x2212;'), mn('2'), mi('x'), mo('&#x2212;'), mn('1')), DX)
anti = M(mo('='), r(p(frac(E2X, mn('2')), mo('&#x2212;'), sup(mi('x'), mn('2')), mo('&#x2212;'), mi('x')), EV))
plug = M(mo('='), p(frac(sup(mi('e'), mn('4')), mn('2')), mo('&#x2212;'), mn('4'), mo('&#x2212;'), mn('2')),
         mo('&#x2212;'), p(frac(mn('1'), mn('2')), mo('&#x2212;'), mn('0'), mo('&#x2212;'), mn('0')))
res = M(mo('='), frac(sup(mi('e'), mn('4')), mn('2')), mo('&#x2212;'), mn('6.5'), mo('&#x2248;'), r('<mn mathcolor="#c2410c">20.80</mn>'))
chain = M(r(sup(mi('e'), r(mi('a'), mi('x'))), mo('&#x2032;')), mo('='), mi('a'), sup(mi('e'), r(mi('a'), mi('x'))))
prim = M(INT.replace('<msubsup><mo>&#x222B;</mo><mn>0</mn><mn>2</mn></msubsup>', '<mo>&#x222B;</mo>'), E2X, DX, mo('='), frac(E2X, mn('2')), mo('+'), mi('C'))

# ---------- scenes ----------
# each scene: title, list of lines (kind, html), list of events.
# event: dict(show=lines count, caption, hold seconds, anim={'curve':(a,b)...}, frames)
SCENES = []
def scene(key, title, lines, events, mode='lesson'):
    SCENES.append(dict(key=key, title=title, lines=lines, events=events, mode=mode))

T = lambda s: ('t', s)
X = lambda s: ('m', s)

scene('s00', '', [], [
    dict(caption='', hold=4.5, set=dict()),
], mode='title')

scene('s01', 'השאלה', [
    T('נתונה הפונקציה:'), X(f_def),
    T('<b>א.</b> מצאו את משוואת המשיק לגרף הפונקציה בנקודה שבה <span style="white-space:nowrap">' + M(mi('x'), mo('='), mn('0')) + '.</span>'),
    T('<b>ב.</b> חשבו את השטח המוגבל על־ידי גרף הפונקציה, המשיק והישר <span style="white-space:nowrap">' + M(mi('x'), mo('='), mn('2')) + '.</span>'),
], [
    dict(show=2, caption='נתחיל בהצגת הפונקציה ובציור הגרף שלה.', hold=2.5),
    dict(anim=dict(curve=(0, 1)), dur=1.6, caption='הגרף של f(x) עולה במהירות: זו פונקציה מעריכית שהוזזה 6 יחידות למעלה.', hold=4),
    dict(show=3, caption='בסעיף א נמצא משוואת משיק בנקודה x = 0.', hold=3.5),
    dict(show=4, caption='בסעיף ב נחשב שטח בעזרת אינטגרל.', hold=4),
], )

scene('s02', 'סעיף א׳ – השיפוע של המשיק', [
    T('כדי לכתוב משוואת ישר צריך <b>שיפוע</b> ו<b>נקודה</b>.'),
    T('השיפוע של המשיק שווה לנגזרת בנקודת ההשקה. לפי כלל השרשרת:'), X(chain),
    T('לכן הנגזרת של הפונקציה היא:'), X(f_der),
    T('נציב ' + M(mi('x'), mo('='), mn('0')) + ':'), X(m_val),
], [
    dict(show=1, caption='כדי לכתוב משוואת ישר צריך שני דברים: שיפוע ונקודה.', hold=4, set=dict(curve=1)),
    dict(show=3, caption='שיפוע המשיק שווה לערך הנגזרת בנקודת ההשקה. כאן נשתמש בכלל השרשרת.', hold=5),
    dict(show=5, caption='נגזור: הנגזרת של e בחזקת 2x היא 2 כפול e בחזקת 2x, והנגזרת של 6 היא אפס.', hold=5),
    dict(show=7, caption='נציב x = 0 ונקבל שיפוע m = 2.', hold=4.5),
])

scene('s03', 'סעיף א׳ – נקודת ההשקה ומשוואת המשיק', [
    T('שיעור ה־y של נקודת ההשקה:'), X(y_val),
    T('נקודת ההשקה:'), X(pt),
    T('נציב בנוסחת ישר לפי שיפוע ונקודה:'), X(line_gen), X(line_sub),
    T('<b>משוואת המשיק:</b>'), X(line_fin),
], [
    dict(show=2, caption='נמצא את שיעור ה־y של הנקודה: נציב x = 0 בפונקציה ונקבל 7.', hold=4.5, set=dict(curve=1)),
    dict(show=4, anim=dict(point=(0, 1)), dur=0.6, caption='נקודת ההשקה היא (0, 7).', hold=3),
    dict(show=7, caption='נציב את השיפוע m = 2 ואת הנקודה (0, 7) בנוסחת הישר.', hold=4.5),
    dict(show=9, anim=dict(tangent=(0, 1)), dur=1.4, caption='קיבלנו את משוואת המשיק: y = 2x + 7.', hold=4.5, box=8),
])

scene('s04', 'סעיף ב׳ – הגדרת השטח', [
    T('נסמן את המשיק:'), X(g_def),
    T('השטח מוגבל על־ידי הגרף, המשיק והישר ' + M(mi('x'), mo('='), mn('2')) + '.'),
    T('בתחום ' + M(mn('0'), mo('&#x2264;'), mi('x'), mo('&#x2264;'), mn('2')) + ' הגרף של ' + M(mi('f')) + ' נמצא מעל המשיק, לכן:'),
    X(S_def),
], [
    dict(show=2, caption='נסמן את המשיק ב־g(x).', hold=3, set=dict(curve=1, point=1, tangent=1)),
    dict(show=3, anim=dict(xline=(0, 1)), dur=0.8, caption='הישר x = 2 סוגר את השטח מימין, והמשיק נוגע בגרף ב־x = 0.', hold=4),
    dict(show=4, anim=dict(area=(0, 1)), dur=1.2, caption='זה השטח שעלינו לחשב. הגרף של f נמצא מעליו, והמשיק מתחתיו.', hold=4.5),
    dict(show=5, caption='לכן השטח הוא האינטגרל מ־0 עד 2 של הפרש הפונקציות: העליונה פחות התחתונה.', hold=5),
])

scene('s05', 'סעיף ב׳ – חישוב האינטגרל', [
    X(S_def), X(S_sub), X(S_simp),
    T('פונקציה קדומה של ' + M(E2X) + ':'), X(prim),
    X(anti),
], [
    dict(show=1, caption='נכתוב שוב את האינטגרל.', hold=2.5, set=dict(curve=1, point=1, tangent=1, xline=1, area=1)),
    dict(show=2, caption='נציב את שתי הפונקציות.', hold=3.5),
    dict(show=3, caption='נכנס איברים דומים: 6 פחות 7 נותן מינוס 1.', hold=4),
    dict(show=5, caption='הפונקציה הקדומה של e בחזקת 2x היא e בחזקת 2x חלקי 2.', hold=4.5),
    dict(show=6, caption='נמצא פונקציה קדומה לכל הביטוי ונציב את הגבולות 0 ו־2.', hold=4.5),
])

scene('s06', 'סעיף ב׳ – הצבת הגבולות', [
    X(anti), X(plug), X(res),
    T('<b>השטח המבוקש:</b> בערך 20.80 יחידות שטח.'),
], [
    dict(show=1, caption='נציב את הגבול העליון 2 ונחסר את הערך בגבול התחתון 0.', hold=3.5, set=dict(curve=1, point=1, tangent=1, xline=1, area=1)),
    dict(show=2, caption='ב־x = 2: e בחזקת 4 חלקי 2, פחות 4, פחות 2. ב־x = 0: חצי.', hold=5),
    dict(show=3, caption='נפשט: e בחזקת 4 חלקי 2 פחות 6.5, כלומר בערך 20.80.', hold=4.5, box=2),
    dict(show=4, caption='זה השטח הצבוע בגרף.', hold=3.5),
])

scene('s07', 'סיכום', [
    T('<b>א.</b> משוואת המשיק בנקודה ' + M(p(mn('0'), mo(','), mn('7'))) + ':'), X(line_fin),
    T('<b>ב.</b> השטח:'), X(M(mi('S'), mo('='), frac(sup(mi('e'), mn('4')), mn('2')), mo('&#x2212;'), mn('6.5'), mo('&#x2248;'), mn('20.80'))),
    T('<b>מה למדנו:</b> שיפוע המשיק = הנגזרת בנקודה. שטח בין שני גרפים = אינטגרל של (העליון − התחתון).'),
], [
    dict(show=2, caption='לסיכום, בסעיף א מצאנו את המשיק y = 2x + 7.', hold=3.5, set=dict(curve=1, point=1, tangent=1, xline=1, area=1)),
    dict(show=4, caption='בסעיף ב חישבנו שטח של בערך 20.80 יחידות.', hold=3.5),
    dict(show=5, caption='שני הרעיונות המרכזיים: הנגזרת נותנת את שיפוע המשיק, והאינטגרל של ההפרש נותן את השטח.', hold=5.5),
])

scene('s08', '', [], [dict(caption='', hold=4, set=dict())], mode='end')

# ---------- page ----------
GRAPH = {'x0': -0.45, 'x1': 2.35, 'y0': -4, 'y1': 66, 'w': 760, 'h': 720}
def gx(x): return (x - GRAPH['x0']) / (GRAPH['x1'] - GRAPH['x0']) * GRAPH['w']
def gy(y): return GRAPH['h'] - (y - GRAPH['y0']) / (GRAPH['y1'] - GRAPH['y0']) * GRAPH['h']
def f(x): return math.exp(2 * x) + 6
xs = [i / 300 * 2.47 - 0.42 for i in range(301)]
xs = [x for x in xs if f(x) <= 66]
curve = 'M' + ' L'.join(f'{gx(x):.1f},{gy(f(x)):.1f}' for x in xs)
ax = [i / 100 * 2 for i in range(101)]
area = 'M' + ' L'.join(f'{gx(x):.1f},{gy(f(x)):.1f}' for x in ax) + ' L' + f'{gx(2):.1f},{gy(11):.1f} L{gx(0):.1f},{gy(7):.1f} Z'
ticks = ''.join(f'<line x1="{gx(t):.1f}" y1="{gy(0)-6:.1f}" x2="{gx(t):.1f}" y2="{gy(0)+6:.1f}" class="ax"/><text x="{gx(t):.1f}" y="{gy(0)+30:.1f}" class="tk">{t}</text>' for t in (1, 2))
ticks += ''.join(f'<line x1="{gx(0)-6:.1f}" y1="{gy(t):.1f}" x2="{gx(0)+6:.1f}" y2="{gy(t):.1f}" class="ax"/><text x="{gx(0)-12:.1f}" y="{gy(t)+7:.1f}" class="tk" text-anchor="end">{t}</text>' for t in (20, 40, 60))
svg = f'''<svg id="g" width="{GRAPH['w']}" height="{GRAPH['h']}" viewBox="0 0 {GRAPH['w']} {GRAPH['h']}">
<defs><clipPath id="cc"><rect id="ccr" x="0" y="0" width="0" height="{GRAPH['h']}"/></clipPath></defs>
<path id="area" d="{area}" fill="#f59e0b" fill-opacity="0.35" stroke="none" opacity="0"/>
<line x1="0" y1="{gy(0):.1f}" x2="{GRAPH['w']}" y2="{gy(0):.1f}" class="ax"/>
<line x1="{gx(0):.1f}" y1="0" x2="{gx(0):.1f}" y2="{GRAPH['h']}" class="ax"/>
<text x="{GRAPH['w']-14}" y="{gy(0)-12:.1f}" class="lb">x</text><text x="{gx(0)+12:.1f}" y="26" class="lb">y</text>{ticks}
<line id="xl" x1="{gx(2):.1f}" y1="{gy(0):.1f}" x2="{gx(2):.1f}" y2="{gy(0):.1f}" stroke="#7c3aed" stroke-width="4" stroke-dasharray="12 8"/>
<text id="xlt" x="{gx(2)+10:.1f}" y="{gy(30):.1f}" class="lb" fill="#7c3aed" opacity="0">x = 2</text>
<path id="cv" d="{curve}" fill="none" stroke="#2453c9" stroke-width="5" clip-path="url(#cc)"/>
<text id="cvt" x="{gx(1.05):.1f}" y="{gy(36):.1f}" class="lb" fill="#2453c9" opacity="0">f(x)</text>
<line id="tg" x1="{gx(-0.45):.1f}" y1="{gy(6.1):.1f}" x2="{gx(-0.45):.1f}" y2="{gy(6.1):.1f}" stroke="#16a34a" stroke-width="5"/>
<text id="tgt" x="{gx(1.35):.1f}" y="{gy(13.5):.1f}" class="lb" fill="#16a34a" opacity="0">y = 2x + 7</text>
<circle id="pt" cx="{gx(0):.1f}" cy="{gy(7):.1f}" r="11" fill="#c2410c" stroke="#fff" stroke-width="3" opacity="0"/>
<text id="ptt" x="{gx(0)+18:.1f}" y="{gy(7)-14:.1f}" class="lb" fill="#c2410c" opacity="0">(0, 7)</text>
<text id="st" x="{gx(1.55):.1f}" y="{gy(20):.1f}" class="lb big" fill="#b45309" opacity="0">S</text>
</svg>'''
GEOM = dict(tg=(gx(-0.45), gy(6.1), gx(2.25), gy(11.5)), xl=(gx(2), gy(0), gx(2), gy(f(2))), w=GRAPH['w'])

def lines_html(lines):
    out = []
    for i, (k, s) in enumerate(lines):
        out.append(f'<div class="ln {"mm" if k == "m" else "tt"}" data-i="{i}">{s}</div>')
    return ''.join(out)

CSS = '''
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:1920px;height:1080px;overflow:hidden;background:#f6f7fb;font-family:'DejaVu Sans',sans-serif;color:#1b2333}
math{font-family:'Latin Modern Math';font-size:1.18em}
.top{position:absolute;top:0;left:0;right:0;height:92px;background:#fff;border-bottom:1px solid #e2e6ee;display:flex;align-items:center;justify-content:space-between;padding:0 56px}
.brand{font-size:40px;font-weight:bold;color:#2453c9}
.brand span{font-size:22px;color:#5d6678;font-weight:normal;margin-left:22px;display:inline-block}
.meta{font-size:22px;color:#5d6678;direction:rtl}
.meta b{background:#2453c9;color:#fff;border-radius:999px;padding:4px 16px;margin-left:10px;font-weight:bold}
.stitle{position:absolute;top:120px;right:72px;font-size:44px;font-weight:bold;color:#2453c9}
.text{position:absolute;top:200px;right:72px;width:930px;height:700px;direction:rtl}
.ln{font-size:33px;line-height:1.5;margin-bottom:14px;opacity:0;transition:none}
.ln.mm{direction:ltr;text-align:center;font-size:38px;background:#fff;border:1px solid #e2e6ee;border-radius:12px;padding:8px 18px}
.ln.cur{color:#000}
.ln.boxed{border:3px solid #16a34a;background:#eef9f1}
.gwrap{position:absolute;left:56px;top:150px;width:800px;height:760px;background:#fff;border:1px solid #e2e6ee;border-radius:16px;padding:20px}
.ax{stroke:#8a92a3;stroke-width:2}
.tk{font-size:20px;fill:#5d6678;text-anchor:middle;font-family:'DejaVu Sans'}
.lb{font-size:28px;font-family:'DejaVu Sans';font-weight:bold}
.lb.big{font-size:56px}
.cap{position:absolute;left:0;right:0;bottom:0;height:128px;background:#1b2333;color:#fff;direction:rtl;display:flex;align-items:center;justify-content:center;padding:0 90px;font-size:34px;line-height:1.35;text-align:center}
.cap:empty{display:none}
.card{position:absolute;inset:0;display:none;flex-direction:column;align-items:center;justify-content:center;direction:rtl;background:linear-gradient(135deg,#2453c9,#1b3a8f);color:#fff;text-align:center}
.card h1{font-size:96px;margin-bottom:24px}
.card h2{font-size:52px;font-weight:normal;margin-bottom:40px}
.card p{font-size:34px;opacity:.9;margin:6px}
.card .pill{display:inline-block;background:#fff;color:#2453c9;border-radius:999px;padding:8px 30px;font-weight:bold;font-size:34px;margin-top:30px}
body.title .card.t1,body.end .card.t2{display:flex}
'''

PAGE = f'''<!DOCTYPE html><html lang="he"><head><meta charset="utf-8"><style>{CSS}</style></head><body>
<div class="top"><div class="brand">JustAsk<span>שיעור וידאו</span></div><div class="meta"><b>קוד שאלה 200</b> בגרות 4 יחידות · ינואר 1999 · חישוב שטחים בעזרת אינטגרל</div></div>
<div class="stitle" id="stitle"></div>
<div class="text" id="text"></div>
<div class="gwrap">{svg}</div>
<div class="cap" id="cap"></div>
<div class="card t1"><h1>JustAsk</h1><h2>משיק ושטח בעזרת אינטגרל</h2><p>בגרות במתמטיקה 4 יחידות · ינואר 1999</p><p>הפונקציה {f_def}</p><div class="pill">קוד שאלה = 200</div></div>
<div class="card t2"><h1>סיימנו!</h1><h2>נסו לבד שאלה דומה באתר</h2><p>geo.yagur.site/justask</p><div class="pill">קוד שאלה = 200</div></div>
<script>
const G={json.dumps(GEOM)};
function setScene(s){{document.body.className=s.mode;document.getElementById('stitle').textContent=s.title;document.getElementById('text').innerHTML=s.lines;}}
function st(o){{
  const lines=[...document.querySelectorAll('.ln')];
  lines.forEach((l,i)=>{{l.style.opacity=i<o.show-1?1:(i==o.show-1?o.fade:0);l.classList.toggle('boxed',o.box===i)}});
  document.getElementById('cap').textContent=(o.caption||'').replace(/[A-Za-z0-9(][A-Za-z0-9 =+.,()\\-]*[A-Za-z0-9)]|[A-Za-z0-9]/g,m=>'\u2066'+m+'\u2069');
  document.getElementById('ccr').setAttribute('width',G.w*o.curve);
  document.getElementById('cvt').setAttribute('opacity',o.curve>=1?1:0);
  ['pt','ptt'].forEach(id=>document.getElementById(id).setAttribute('opacity',o.point));
  const t=G.tg;const k=o.tangent;const tg=document.getElementById('tg');tg.setAttribute('x2',t[0]+(t[2]-t[0])*k);tg.setAttribute('y2',t[1]+(t[3]-t[1])*k);
  document.getElementById('tgt').setAttribute('opacity',k>=1?1:0);
  const x=G.xl;const xl=document.getElementById('xl');xl.setAttribute('y2',x[1]+(x[3]-x[1])*o.xline);document.getElementById('xlt').setAttribute('opacity',o.xline>=1?1:0);
  document.getElementById('area').setAttribute('opacity',o.area);document.getElementById('st').setAttribute('opacity',o.area);
}}
</script></body></html>'''


def ease(t): return t * t * (3 - 2 * t)


def render():
    shutil.rmtree(FR, ignore_errors=True)
    os.makedirs(FR)
    open(os.path.join(ROOT, 'page.html'), 'w', encoding='utf-8').write(PAGE)
    plan = []   # per scene: list of (jpg, duration)
    with sync_playwright() as pw:
        b = pw.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox', '--font-render-hinting=none'])
        pg = b.new_page(viewport={'width': W, 'height': H})
        pg.goto('file://' + os.path.join(ROOT, 'page.html'))
        pg.wait_for_timeout(500)
        n = 0
        for sc in SCENES:
            pg.evaluate('s=>setScene(s)', dict(mode=sc['mode'], title=sc['title'], lines=lines_html(sc['lines'])))
            state = dict(show=0, fade=1, caption='', curve=0, point=0, tangent=0, xline=0, area=0, box=-1)
            items = []
            def shot(dur):
                nonlocal n
                pg.evaluate('o=>st(o)', state)
                fn = os.path.join(FR, f'{n:05d}.jpg'); n += 1
                pg.screenshot(path=fn, type='jpeg', quality=92)
                items.append((fn, dur))
            for ev in sc['events']:
                state.update(ev.get('set', {}))
                state['box'] = ev.get('box', -1)
                state['caption'] = ev['caption']
                newshow = ev.get('show', state['show'])
                anim = ev.get('anim', {})
                adur = ev.get('dur', 0)
                if newshow > state['show'] + 1:      # reveal earlier lines at once, fade the last one
                    state['show'] = newshow - 1; state['fade'] = 1
                if newshow > state['show'] or anim:
                    nf = max(int((adur or 0.4) * FPS), 1)
                    if newshow > state['show']:
                        state['show'] = newshow
                    for i in range(1, nf + 1):
                        t = ease(i / nf)
                        state['fade'] = min(1, i / max(1, int(0.4 * FPS)))
                        for k, (a, bb) in anim.items():
                            state[k] = a + (bb - a) * t
                        shot(1 / FPS)
                state['fade'] = 1
                shot(ev['hold'])
            plan.append(items)
        b.close()
    return plan


def encode(plan):
    parts = []
    for si, items in enumerate(plan):
        lst = os.path.join(ROOT, f'sc{si:02d}.txt')
        with open(lst, 'w') as fh:
            for fn, d in items:
                fh.write(f"file '{fn}'\nduration {d:.4f}\n")
            fh.write(f"file '{items[-1][0]}'\n")
        total = sum(d for _, d in items)
        tmp = os.path.join(ROOT, f'sc{si:02d}.tmp.mp4'); out = os.path.join(ROOT, f'sc{si:02d}.mp4')
        vf = f'fps={FPS},format=yuv420p,fade=t=in:st=0:d=0.35,fade=t=out:st={total-0.35:.3f}:d=0.35'
        subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', lst, '-vf', vf,
                        '-t', f'{total:.3f}', '-c:v', 'libx264', '-crf', '20', '-preset', 'veryfast', '-pix_fmt', 'yuv420p', '-r', str(FPS), tmp], check=True)
        os.replace(tmp, out); parts.append(out)
    cl = os.path.join(ROOT, 'all.txt')
    open(cl, 'w').write(''.join(f"file '{p}'\n" for p in parts))
    v = os.path.join(ROOT, 'video_noaudio.mp4')
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', cl, '-c', 'copy', v], check=True)
    final = os.path.join(ROOT, 'lesson_q200.mp4')
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', v, '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=stereo',
                    '-c:v', 'copy', '-c:a', 'aac', '-shortest', '-movflags', '+faststart', final], check=True)
    print('done', final)


if __name__ == '__main__':
    if sys.argv[1:] == ['preview']:
        # render only the busiest states for a look
        open(os.path.join(ROOT, 'page.html'), 'w', encoding='utf-8').write(PAGE)
        with sync_playwright() as pw:
            b = pw.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
            pg = b.new_page(viewport={'width': W, 'height': H})
            pg.goto('file://' + os.path.join(ROOT, 'page.html')); pg.wait_for_timeout(400)
            for sc in SCENES:
                pg.evaluate('s=>setScene(s)', dict(mode=sc['mode'], title=sc['title'], lines=lines_html(sc['lines'])))
                full = dict(show=len(sc['lines']), fade=1, caption=sc['events'][-1]['caption'], curve=1, point=1, tangent=1, xline=1, area=1, box=-1)
                if sc['key'] in ('s01', 's02'): full.update(point=0, tangent=0, xline=0, area=0)
                pg.evaluate('o=>st(o)', full)
                pg.screenshot(path=os.path.join(ROOT, f'prev_{sc["key"]}.jpg'), type='jpeg', quality=80)
            b.close()
    else:
        encode(render())
