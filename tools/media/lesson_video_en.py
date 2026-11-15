"""English version of the question-200 lesson, with flite narration (ffmpeg's flite filter)."""
import os, re, sys, shutil, subprocess, wave
import numpy as np
from playwright.sync_api import sync_playwright
import lesson_video_he as L
from lesson_video_he import M, mi, mn, mo, p, frac, sup

W, H, FPS, SR = L.W, L.H, L.FPS, 44100
ROOT = os.environ.get('LESSON_OUT', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'out'))
os.makedirs(ROOT, exist_ok=True)
FR = os.path.join(ROOT, 'frames_en')
TTS = os.path.join(ROOT, 'tts_en')
T = lambda s: ('t', s)
X = lambda s: ('m', s)
x0 = M(mi('x'), mo('='), mn('0'))
x2 = M(mi('x'), mo('='), mn('2'))
S_res = M(mi('S'), mo('='), frac(sup(mi('e'), mn('4')), mn('2')), mo('&#x2212;'), mn('6.5'), mo('&#x2248;'), mn('20.80'))
e2x = M(L.E2X)

SCENES = []
def scene(key, title, lines, events, mode='lesson'):
    SCENES.append(dict(key=key, title=title, lines=lines, events=events, mode=mode))

scene('s00', '', [], [dict(say='Hello! In this lesson we solve a question from the four unit math bagrut exam, January 1999. '
                                'We will find a tangent line, and then compute an area using an integral.', cap='', hold=1.5)], mode='title')

scene('s01', 'The question', [
    T('Given the function:'), X(L.f_def),
    T('<b>a.</b> Find the equation of the tangent to the graph at the point where ' + x0 + '.'),
    T('<b>b.</b> Compute the area bounded by the graph, the tangent and the line ' + x2 + '.'),
], [
    dict(show=2, say='We are given the function f of x equals e to the power 2 x, plus 6.'),
    dict(anim=dict(curve=(0, 1)), dur=1.6, say='This is an exponential function, shifted 6 units up. Notice how fast it grows as x increases.'),
    dict(show=3, say='In part a, we find the equation of the tangent line at x equals zero.'),
    dict(show=4, say='In part b, we compute the area bounded by the graph, the tangent, and the line x equals 2.'),
])

scene('s02', 'Part a – the slope of the tangent', [
    T('To write the equation of a line we need a <b>slope</b> and a <b>point</b>.'),
    T('The slope of the tangent equals the derivative at the point of tangency. By the chain rule:'), X(L.chain),
    T('So the derivative of the function is:'), X(L.f_der),
    T('Substitute ' + x0 + ':'), X(L.m_val),
], [
    dict(show=1, set=dict(curve=1), say='To write the equation of a line, we need two things: a slope, and a point on the line.'),
    dict(show=3, say='The slope of the tangent equals the value of the derivative at the point of tangency. '
                     'Recall the chain rule: the derivative of e to the a x, is a times e to the a x.'),
    dict(show=5, say='So the derivative of e to the 2 x, is 2 e to the 2 x. And the derivative of the constant 6 is zero.'),
    dict(show=7, say='Substitute x equals zero. e to the zero is 1, so the slope m equals 2.'),
])

scene('s03', 'Part a – the point and the tangent line', [
    T('The y-coordinate of the point of tangency:'), X(L.y_val),
    T('The point of tangency:'), X(L.pt),
    T('Point–slope form of a line:'), X(L.line_gen), X(L.line_sub),
    T('<b>The tangent line:</b>'), X(L.line_fin),
], [
    dict(show=2, set=dict(curve=1), say='Now the point. Substitute x equals zero into the function: e to the zero, plus 6, which is 7.'),
    dict(show=4, anim=dict(point=(0, 1)), dur=0.6, say='So the point of tangency is zero, 7.'),
    dict(show=7, say='Substitute the slope 2, and the point zero, 7, into the point slope formula: y minus 7 equals 2 times, x minus zero.'),
    dict(show=9, anim=dict(tangent=(0, 1)), dur=1.4, box=8, say='Rearranging, the tangent line is y equals 2 x plus 7. That is the answer to part a.'),
])

scene('s04', 'Part b – setting up the area', [
    T('Denote the tangent line by <i>g</i>:'), X(L.g_def),
    T('The region is bounded by the graph, the tangent and the line ' + x2 + '.'),
    T('For ' + M(mn('0'), mo('&#x2264;'), mi('x'), mo('&#x2264;'), mn('2')) + ' the graph of ' + M(mi('f')) + ' lies above the tangent, so:'),
    X(L.S_def),
], [
    dict(show=2, set=dict(curve=1, point=1, tangent=1), say='Now part b. Let us call the tangent line g of x, equal to 2 x plus 7.'),
    dict(show=3, anim=dict(xline=(0, 1)), dur=0.8, say='The line x equals 2 closes the region on the right. On the left, the tangent touches the graph at x equals zero.'),
    dict(show=4, anim=dict(area=(0, 1)), dur=1.2, say='This shaded region is the area we need. The graph of f is above it, and the tangent is below it.'),
    dict(show=5, say='So the area is the integral from zero to 2, of the upper function minus the lower function: f of x, minus g of x.'),
])

scene('s05', 'Part b – computing the integral', [
    X(L.S_def), X(L.S_sub), X(L.S_simp),
    T('An antiderivative of ' + e2x + ':'), X(L.prim),
    X(L.anti),
], [
    dict(show=1, set=dict(curve=1, point=1, tangent=1, xline=1, area=1), say='Let us write the integral again.'),
    dict(show=2, say='Substitute both functions: e to the 2 x, plus 6, minus 2 x, minus 7.'),
    dict(show=3, say='Combine like terms: 6 minus 7 is minus 1.'),
    dict(show=5, say='An antiderivative of e to the 2 x, is e to the 2 x, over 2. You can check it: its derivative gives back e to the 2 x.'),
    dict(show=6, say='So the antiderivative of the whole expression is: e to the 2 x over 2, minus x squared, minus x, evaluated from zero to 2.'),
])

scene('s06', 'Part b – plugging in the limits', [
    X(L.anti), X(L.plug), X(L.res),
    T('<b>The required area:</b> about 20.80 square units.'),
], [
    dict(show=1, set=dict(curve=1, point=1, tangent=1, xline=1, area=1), say='Substitute the upper limit 2, and subtract the value at the lower limit, zero.'),
    dict(show=2, say='At x equals 2, we get e to the 4 over 2, minus 4, minus 2. At x equals zero, we get one half.'),
    dict(show=3, box=2, say='Simplifying: e to the 4 over 2, minus six and a half, which is about twenty point eight.'),
    dict(show=4, say='That is the shaded area in the graph: about twenty point eight square units.'),
])

scene('s07', 'Summary', [
    T('<b>a.</b> The tangent at the point ' + M(p(mn('0'), mo(','), mn('7'))) + ':'), X(L.line_fin),
    T('<b>b.</b> The area:'), X(S_res),
    T('<b>Key ideas:</b> the slope of the tangent = the derivative at the point. The area between two graphs = the integral of (upper − lower).'),
], [
    dict(show=2, set=dict(curve=1, point=1, tangent=1, xline=1, area=1), say='To sum up: in part a, we found the tangent line, y equals 2 x plus 7.'),
    dict(show=4, say='In part b, we found that the area is about twenty point eight square units.'),
    dict(show=5, say='Two key ideas to remember: the derivative at a point gives the slope of the tangent. '
                     'And the integral of the upper function minus the lower function gives the area between them.'),
])

scene('s08', '', [], [dict(say="That's it! Now try a similar question on the site. Good luck!", cap='', hold=1.2)], mode='end')


# ---------- page: the Hebrew page, switched to English / left-to-right ----------
PAGE = L.PAGE
PAGE = PAGE.replace('<html lang="he">', '<html lang="en">')
PAGE = PAGE.replace('<span>שיעור וידאו</span>', '<span>Video lesson</span>')
PAGE = PAGE.replace('<div class="meta"><b>קוד שאלה 200</b> בגרות 4 יחידות · ינואר 1999 · חישוב שטחים בעזרת אינטגרל</div>',
                    '<div class="meta">Bagrut 4 units · January 1999 · Areas with integrals <b>Question code 200</b></div>')
PAGE = re.sub(r'<div class="card t1">.*?</div>\s*<div class="card t2">.*?</div>(?=\s*<script>)',
              '<div class="card t1"><h1>JustAsk</h1><h2>Tangent line &amp; area with an integral</h2>'
              '<p>Bagrut math exam, 4 units · January 1999</p><p>The function ' + L.f_def + '</p>'
              '<div class="pill">Question code = 200</div></div>'
              '<div class="card t2"><h1>Well done!</h1><h2>Try a similar question on the site</h2><p>geo.yagur.site/justask</p>'
              '<div class="pill">Question code = 200</div></div>', PAGE, flags=re.S)
PAGE = PAGE.replace('</style>', '''
.meta,.text,.cap,.card{direction:ltr}
.meta b{margin-left:10px;margin-right:0}
.stitle{right:auto;left:928px}
.text{right:auto;left:928px}
.cap{font-size:31px}
</style>''')


def spoken(s):
    s = re.sub(r'\b2 x\b', 'two x', s)
    s = re.sub(r'\bx\b', 'ex', s)
    s = re.sub(r'\be to\b', 'ee to', s)
    s = s.replace('e to the', 'ee to the')
    s = re.sub(r'\be\b', 'ee', s)
    return s


def tts():
    shutil.rmtree(TTS, ignore_errors=True); os.makedirs(TTS)
    k = 0
    for sc in SCENES:
        for ev in sc['events']:
            k += 1
            txt = os.path.join(TTS, f'{k:02d}.txt'); wav = os.path.join(TTS, f'{k:02d}.wav')
            open(txt, 'w').write(spoken(ev['say']))
            subprocess.run(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', f'flite=textfile={txt}:voice=slt',
                            '-af', 'atempo=0.94,highpass=f=80,lowpass=f=7000,aresample=44100', '-ac', '1', wav], check=True)
            with wave.open(wav) as w:
                ev['audio'] = (wav, w.getnframes() / w.getframerate())


def render():
    shutil.rmtree(FR, ignore_errors=True); os.makedirs(FR)
    page = os.path.join(ROOT, 'page_en.html')
    open(page, 'w', encoding='utf-8').write(PAGE)
    plan, cues = [], []
    clock = 0.0
    with sync_playwright() as pw:
        b = pw.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox', '--font-render-hinting=none'])
        pg = b.new_page(viewport={'width': W, 'height': H})
        pg.goto('file://' + page); pg.wait_for_timeout(500)
        n = 0
        for sc in SCENES:
            pg.evaluate('s=>setScene(s)', dict(mode=sc['mode'], title=sc['title'], lines=L.lines_html(sc['lines'])))
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
                state['caption'] = ev.get('cap', ev['say'])
                newshow = ev.get('show', state['show'])
                anim = ev.get('anim', {}); adur = ev.get('dur', 0)
                ev_start = clock
                if newshow > state['show'] + 1:
                    state['show'] = newshow - 1
                if newshow > state['show'] or anim:
                    nf = max(int((adur or 0.4) * FPS), 1)
                    state['show'] = max(state['show'], newshow)
                    for i in range(1, nf + 1):
                        t = L.ease(i / nf)
                        state['fade'] = min(1, i / max(1, int(0.4 * FPS)))
                        for kk, (a, bb) in anim.items():
                            state[kk] = a + (bb - a) * t
                        shot(1 / FPS); clock += 1 / FPS
                state['fade'] = 1
                # speech starts with the event; the still holds until it ends plus a pause
                wav, alen = ev['audio']
                hold = max(ev.get('hold', 0) + alen, alen + 0.7) - (clock - ev_start)
                hold = max(hold, 1.0)
                cues.append((ev_start + 0.25, wav))
                shot(hold); clock += hold
            plan.append(items)
        b.close()
    return plan, cues, clock


def encode(plan, cues, total):
    parts = []
    for si, items in enumerate(plan):
        lst = os.path.join(ROOT, f'en{si:02d}.txt')
        with open(lst, 'w') as fh:
            for fn, d in items:
                fh.write(f"file '{fn}'\nduration {d:.4f}\n")
            fh.write(f"file '{items[-1][0]}'\n")
        dur = sum(d for _, d in items)
        tmp = os.path.join(ROOT, f'en{si:02d}.tmp.mp4'); out = os.path.join(ROOT, f'en{si:02d}.mp4')
        vf = f'fps={FPS},format=yuv420p,fade=t=in:st=0:d=0.35,fade=t=out:st={dur-0.35:.3f}:d=0.35'
        subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', lst, '-vf', vf,
                        '-t', f'{dur:.3f}', '-c:v', 'libx264', '-crf', '20', '-preset', 'veryfast', '-pix_fmt', 'yuv420p', '-r', str(FPS), tmp], check=True)
        os.replace(tmp, out); parts.append(out)
    cl = os.path.join(ROOT, 'all_en.txt')
    open(cl, 'w').write(''.join(f"file '{q}'\n" for q in parts))
    v = os.path.join(ROOT, 'video_en_noaudio.mp4')
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', cl, '-c', 'copy', v], check=True)
    # narration track: each sentence placed at its event's start
    track = np.zeros(int((total + 1) * SR), dtype=np.float32)
    for t0, wav in cues:
        with wave.open(wav) as w:
            a = np.frombuffer(w.readframes(w.getnframes()), dtype=np.int16).astype(np.float32) / 32768
        i = int(t0 * SR); track[i:i + len(a)] += a[:len(track) - i]
    track = track / max(1e-6, np.abs(track).max()) * 0.85
    nar = os.path.join(ROOT, 'narration_en.wav')
    with wave.open(nar, 'w') as w:
        w.setnchannels(1); w.setsampwidth(2); w.setframerate(SR)
        w.writeframes((track * 32767).astype(np.int16).tobytes())
    final = os.path.join(ROOT, 'lesson_q200_en.mp4')
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', v, '-i', nar, '-map', '0:v', '-map', '1:a', '-c:v', 'copy',
                    '-c:a', 'aac', '-b:a', '128k', '-ac', '2', '-shortest', '-movflags', '+faststart', final], check=True)
    print('done', final, round(total, 1))


if __name__ == '__main__':
    tts()
    if sys.argv[1:] == ['preview']:
        open(os.path.join(ROOT, 'page_en.html'), 'w', encoding='utf-8').write(PAGE)
        with sync_playwright() as pw:
            b = pw.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
            pg = b.new_page(viewport={'width': W, 'height': H})
            pg.goto('file://' + os.path.join(ROOT, 'page_en.html')); pg.wait_for_timeout(400)
            for sc in SCENES:
                pg.evaluate('s=>setScene(s)', dict(mode=sc['mode'], title=sc['title'], lines=L.lines_html(sc['lines'])))
                pg.evaluate('o=>st(o)', dict(show=len(sc['lines']), fade=1, caption=sc['events'][-1].get('cap', sc['events'][-1]['say']),
                                             curve=1, point=1, tangent=1, xline=1, area=1, box=-1))
                pg.screenshot(path=os.path.join(ROOT, f'prev_en_{sc["key"]}.jpg'), type='jpeg', quality=80)
            b.close()
        print(sum(ev['audio'][1] for sc in SCENES for ev in sc['events']))
    else:
        encode(*render())
