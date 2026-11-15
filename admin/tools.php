<?php
// Development area: site settings, PDF / video files of questions, and the conversion tools in tools/.
require __DIR__ . '/_admin.php';
admin_guard();

$ROOT = dirname(__DIR__);
$TOOLS = $ROOT . '/tools';
$FILES = $ROOT . '/files';
$msgs = []; $errors = [];

function json_read($path) { $j = is_file($path) ? json_decode((string)file_get_contents($path), true) : null; return is_array($j) ? $j : []; }
function json_write($path, $data) {
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n") !== false;
}
function ini_bytes($v) {
    $v = trim((string)$v); $n = (float)$v; $u = strtolower(substr($v, -1));
    return (int)($u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n)));
}
function size_label($b) { return $b >= 1048576 ? round($b / 1048576, 1) . 'MB' : ($b >= 1024 ? round($b / 1024) . 'KB' : $b . 'B'); }

$hasCols = (bool)q("SHOW COLUMNS FROM questions LIKE 'pdf_file'");
$hasSettings = (bool)q("SHOW TABLES LIKE 'settings'");

// ---------- actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'פג תוקף הטופס. טענו את הדף מחדש ונסו שוב.';
    } elseif (($_POST['act'] ?? '') === 'settings') {
        $ver = trim((string)($_POST['version'] ?? ''));
        $mail = trim((string)($_POST['feedback_email'] ?? ''));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ver)) $errors[] = 'הגרסה צריכה להיות חודש ושנה.';
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) $errors[] = 'כתובת האימייל לא תקינה.';
        if (!$hasSettings) $errors[] = 'אין במסד טבלת settings. הריצו את install.php?force=1.';
        if (!$errors) {
            foreach (['version' => $ver, 'feedback_email' => $mail] as $k => $v) {
                q("REPLACE INTO settings (name, value) VALUES (?, ?)", [$k, $v]);
            }
            $ss = json_read("$TOOLS/site_settings.json");
            $ss['version'] = $ver; $ss['feedback_email'] = $mail;
            json_write("$TOOLS/site_settings.json", $ss);
            $msgs[] = 'ההגדרות נשמרו במסד ובקובץ tools/site_settings.json.';
        }
    } elseif (in_array($_POST['act'] ?? '', ['qfiles', 'qremove'], true)) {
        $qid = (int)($_POST['qid'] ?? 0);
        $q = $qid ? q("SELECT id, pdf_file, video_file FROM questions WHERE id = ?", [$qid], true) : null;
        if (!$hasCols) $errors[] = 'אין במסד את השדות pdf_file / video_file. הריצו את install.php?force=1.';
        elseif (!$q) $errors[] = 'אין שאלה עם הקוד הזה.';
        if (!$errors && $_POST['act'] === 'qremove') {
            $kind = $_POST['kind'] === 'video' ? 'video' : 'pdf';
            q("UPDATE questions SET {$kind}_file = NULL WHERE id = ?", [$qid]);
            $qf = json_read("$TOOLS/question_files.json");
            unset($qf[(string)$qid][$kind]);
            if (empty($qf[(string)$qid])) unset($qf[(string)$qid]);
            json_write("$TOOLS/question_files.json", $qf);
            $msgs[] = "הקישור ל" . ($kind === 'pdf' ? 'קובץ ה-PDF' : 'שיעור הווידאו') . " של שאלה $qid הוסר. הקבצים עצמם נשארו בתיקייה files.";
        } elseif (!$errors) {
            if (!is_dir($FILES)) @mkdir($FILES, 0775, true);
            $slots = ['pdf_he' => ['pdf', '', 'application/pdf'], 'pdf_en' => ['pdf', '_en', 'application/pdf'],
                      'video_he' => ['mp4', '', 'video/mp4'], 'video_en' => ['mp4', '_en', 'video/mp4']];
            $saved = [];
            foreach ($slots as $field => [$ext, $suffix, $mime]) {
                $f = $_FILES[$field] ?? null;
                if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) continue;
                if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) { $errors[] = "הקובץ {$f['name']} גדול מהמותר בהגדרות PHP."; continue; }
                if ($f['error'] !== UPLOAD_ERR_OK) { $errors[] = "העלאת {$f['name']} נכשלה (שגיאה {$f['error']})."; continue; }
                if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== $ext) { $errors[] = "{$f['name']}: צריך קובץ .$ext"; continue; }
                $name = "q{$qid}{$suffix}.$ext";
                if (move_uploaded_file($f['tmp_name'], "$FILES/$name")) $saved[] = [$ext === 'pdf' ? 'pdf' : 'video', $suffix, $name];
                else $errors[] = "לא הצלחתי לשמור את $name בתיקייה files.";
            }
            if ($saved) {
                $qf = json_read("$TOOLS/question_files.json");
                foreach ($saved as [$kind, $suffix, $name]) {
                    $base = "q{$qid}." . ($kind === 'pdf' ? 'pdf' : 'mp4');      // the field holds the Hebrew name; _en is found by the site
                    q("UPDATE questions SET {$kind}_file = ? WHERE id = ?", [$base, $qid]);
                    $qf[(string)$qid][$kind] = $base;
                }
                ksort($qf, SORT_NUMERIC);
                json_write("$TOOLS/question_files.json", $qf);
                $msgs[] = 'נשמרו: ' . implode(', ', array_column($saved, 2)) . " (שאלה $qid).";
            } elseif (!$errors) {
                $errors[] = 'לא נבחר אף קובץ.';
            }
        }
    }
}


// ---------- running the tools on this computer ----------
/** Python 3 on this computer: config.php 'python', the py launcher, python on PATH, or a usual install folder. */
function find_python() {
    $cands = [];
    if (cfg('python')) $cands[] = [(string)cfg('python')];
    $cands[] = ['py', '-3']; $cands[] = ['python']; $cands[] = ['python3'];
    foreach (array_merge(glob('C:/Users/*/AppData/Local/Programs/Python/Python3*/python.exe') ?: [],
                         glob('C:/Program Files/Python3*/python.exe') ?: [], glob('C:/Python3*/python.exe') ?: []) as $f) $cands[] = [$f];
    foreach ($cands as $c) {
        [$code, $out] = run_cmd(array_merge($c, ['--version']), [], 20);
        if ($code === 0 && preg_match('/Python 3\.(\d+)/', $out)) return [$c, trim($out)];
    }
    return [null, ''];
}
/** Chrome for printing pages to PDF. */
function find_chrome() {
    foreach (['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
              'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'] as $f) {
        if (is_file($f)) return $f;
    }
    foreach (glob('C:/Users/*/AppData/Local/Google/Chrome/Application/chrome.exe') ?: [] as $f) return $f;
    return null;
}
/** Run a command (array, no shell) in the site folder; returns [exit code, output, seconds]. */
function run_cmd(array $cmd, array $env = [], $timeout = 600) {
    $t0 = microtime(true);
    $envAll = array_merge(getenv(), ['PYTHONIOENCODING' => 'utf-8', 'PYTHONUTF8' => '1'], $env);
    $p = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $envAll, ['bypass_shell' => true]);
    if (!is_resource($p)) return [-1, 'לא הצלחתי להפעיל: ' . implode(' ', $cmd), 0];
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    $out = '';
    while (true) {
        $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $st = proc_get_status($p);
        if (!$st['running']) break;
        if (microtime(true) - $t0 > $timeout) { proc_terminate($p); $out .= "\n[הופסק אחרי $timeout שניות]"; break; }
        usleep(150000);
    }
    $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($p);
    if (isset($st) && !$st['running'] && $st['exitcode'] >= 0) $code = $st['exitcode'];
    return [$code, $out, round(microtime(true) - $t0, 1)];
}

$GSA_DEFAULT = 'F:/גיבוי רמי 10.11.2026/rami-a/Backup/units45.gsa';
$run = null;   // [title, exit code, output, seconds, extra html]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'run' && csrf_ok()) {
    @set_time_limit(900);
    [$py, $pyVer] = find_python();
    $tool = (string)($_POST['tool'] ?? '');
    if (!$py) {
        $run = ['Python', -1, "לא נמצא Python 3 במחשב.\nהתקינו מ-python.org, או כתבו את הנתיב שלו ב-config.php, למשל:\n'python' => 'C:/Users/rami/AppData/Local/Programs/Python/Python312/python.exe',", 0, ''];
    } elseif ($tool === 'export') {
        $gsa = trim((string)($_POST['gsa'] ?? $GSA_DEFAULT));
        $sqlite = ($_POST['dialect'] ?? '') === 'sqlite';
        if (!preg_match('/\.gsa$/i', $gsa) || !is_file($gsa)) $run = ['export_seed.py', -1, "קובץ הגיבוי לא נמצא: $gsa\n(האם הכונן מחובר?)", 0, ''];
        else $run = array_merge(['export_seed.py' . ($sqlite ? ' (SQLite)' : '')],
                                run_cmd(array_merge($py, ['tools/export_seed.py', $gsa, 'db/_graphics_list.txt'], $sqlite ? ['--dialect', 'sqlite'] : [])), ['']);
    } elseif ($tool === 'sqlite') {
        $run = array_merge(['make_sqlite_test_db.py'], run_cmd(array_merge($py, ['tools/make_sqlite_test_db.py', 'test.db'])), ['']);
    } elseif ($tool === 'todo') {
        $run = array_merge(['content_i18n.py todo'], run_cmd(array_merge($py, ['tools/content_i18n.py', 'todo', 'test.db', 'en', 'todo.json'])), ['']);
    } elseif ($tool === 'show') {
        $a = max(0, (int)($_POST['from'] ?? 0)); $b = max($a + 1, min($a + 500, (int)($_POST['to'] ?? 50)));
        $run = array_merge(["tr_show.py $a $b"], run_cmd(array_merge($py, ['tools/tr_show.py', (string)$a, (string)$b]), ['TODO' => 'todo.json']), ['']);
    } elseif ($tool === 'pdf') {
        $qid = (int)($_POST['qid'] ?? 0); $lang = ($_POST['lang'] ?? 'he') === 'en' ? 'en' : 'he';
        $root = dirname(__DIR__);
        if (!$qid || !q("SELECT 1 FROM questions WHERE id = ?", [$qid], true)) $run = ['question_pdf.py', -1, 'אין שאלה עם הקוד הזה.', 0, ''];
        elseif (!is_file("$root/test.db")) $run = ['question_pdf.py', -1, "חסר test.db. הריצו קודם ייצוא SQLite ואחריו make_sqlite_test_db.py.", 0, ''];
        else {
            [$c1, $o1, $s1] = run_cmd(array_merge($py, ['tools/media/question_pdf.py', (string)$qid, $lang]),
                                      ['JUSTASK_SQLITE' => "$root/test.db", 'JUSTASK_MEDIA' => "$root/media"]);
            $html = "$root/tools/media/q{$qid}_{$lang}.html";
            $name = "q{$qid}" . ($lang === 'en' ? '_en' : '') . '.pdf';
            $extra = '';
            if ($c1 === 0 && is_file($html)) {
                $chrome = find_chrome();
                if (!$chrome) { $c1 = -1; $o1 .= "\nלא נמצא Chrome או Edge להדפסה ל-PDF."; }
                else {
                    if (!is_dir("$root/files")) @mkdir("$root/files", 0775, true);
                    $prof = sys_get_temp_dir() . '/justask-chrome';
                    [$c2, $o2, $s2] = run_cmd([$chrome, '--headless=new', '--disable-gpu', '--no-pdf-header-footer', '--user-data-dir=' . $prof,
                                               '--print-to-pdf=' . str_replace('/', '\\', "$root/files/$name"), 'file:///' . str_replace('\\', '/', $html)], [], 120);
                    $o1 .= "\n--- Chrome ---\n" . trim($o2); $s1 += $s2;
                    if (is_file("$root/files/$name") && filemtime("$root/files/$name") >= time() - 300) {
                        $c1 = 0;
                        $base = "q{$qid}.pdf";
                        if ($lang === 'he' || !q("SELECT 1 FROM questions WHERE id = ? AND pdf_file IS NOT NULL", [$qid], true)) {
                            q("UPDATE questions SET pdf_file = ? WHERE id = ?", [$base, $qid]);
                            $qf = json_read("$TOOLS/question_files.json"); $qf[(string)$qid]['pdf'] = $base; ksort($qf, SORT_NUMERIC);
                            json_write("$TOOLS/question_files.json", $qf);
                        }
                        $extra = '<a class="btn small" target="_blank" href="../files/' . h($name) . '?v=' . time() . '">פתיחת ' . h($name) . '</a>';
                    } else { $c1 = $c2 ?: -1; $o1 .= "\nקובץ ה-PDF לא נוצר."; }
                }
            }
            $run = ["question_pdf.py $qid $lang", $c1, $o1, $s1, $extra];
        }
    } else {
        $run = ['?', -1, 'כלי לא מוכר.', 0, ''];
    }
    if ($run && isset($pyVer) && $pyVer) $run[2] = "[$pyVer]\n" . $run[2];
}

// ---------- data for the page ----------
$settings = $hasSettings ? array_column(q("SELECT name, value FROM settings"), 'value', 'name') : [];
$withFiles = $hasCols ? q("SELECT q.id, q.pdf_file, q.video_file, e.title AS exam_title, q.question_no FROM questions q
                             LEFT JOIN exams e ON e.id = q.exam_id
                            WHERE q.pdf_file IS NOT NULL OR q.video_file IS NOT NULL ORDER BY q.id") : [];
$mem = "$TOOLS/translations/en.json";
$memCount = is_file($mem) ? count(json_read($mem)) : 0;
$seed = "$ROOT/db/seed.sql";
$cov = [
    'שאלות' => [(int)q("SELECT COUNT(DISTINCT question_id) n FROM question_languages WHERE lang = 'en'", [], true)['n'], (int)q("SELECT COUNT(*) n FROM questions", [], true)['n']],
    'רקע תאורטי' => [(int)q("SELECT COUNT(*) n FROM translations WHERE entity = 'theory' AND field = 'title' AND lang = 'en'", [], true)['n'], (int)q("SELECT COUNT(*) n FROM theory_items", [], true)['n']],
];
$upMax = min(ini_bytes(ini_get('upload_max_filesize')), ini_bytes(ini_get('post_max_size')));

$tools = [
    ['בניית המסד', [
        ['export_seed.py', 'ממיר את גיבוי המערכת הישנה (units45.gsa) לקובץ db/seed.sql: נושאים, שאלות, רמזים, שלבי פתרון, רקע תאורטי, תרגומים, קובצי השאלות וההגדרות.',
         'python3 tools/export_seed.py "F:/גיבוי רמי 10.11.2026/rami-a/Backup/units45.gsa" db/_graphics_list.txt', 'export'],
        ['install.php', 'טוען את db/schema.sql ו-db/seed.sql למסד הנתונים. מוחק ובונה מחדש את כל הטבלאות.', '../install.php?force=1'],
        ['make_sqlite_test_db.py', 'בונה את test.db (מסד SQLite לבדיקות, לספירת משפטים שלא תורגמו ולקובצי PDF). צריך קודם ייצוא בגרסת SQLite.',
         'python3 tools/make_sqlite_test_db.py test.db', 'sqlite'],
        ['gsa.py · subject_names.py', 'קורא קובצי גיבוי של Caché; שמות הנושאים בעברית, באנגלית ובצרפתית. משמשים את הייצוא.', ''],
    ]],
    ['תרגום התוכן', [
        ['content_i18n.py todo', 'רשימת המשפטים שעוד לא תורגמו לאנגלית.', 'python3 tools/content_i18n.py todo test.db en todo.json', 'todo'],
        ['tr_show.py', 'הצגת משפטים מהרשימה, לפי טווח.', 'TODO=todo.json python3 tools/tr_show.py 0 50', 'show'],
        ['tr_add.py', 'הוספת תרגומים לזיכרון התרגום, עם בדיקה שהנוסחאות (⟦1⟧, ⟦2⟧…) נשמרו. צריך קובץ תרגומים, ולכן מריצים אותו מחלון פקודות (או מבקשים מ-Claude לתרגם).', 'TODO=todo.json python3 tools/tr_add.py 0 batch.json'],
    ]],
    ['קובצי PDF ושיעורי וידאו', [
        ['media/question_pdf.py', 'קובץ PDF לשאלה (שאלה, רמזים, פתרון מלא), מודפס ב-Chrome לתיקייה files ומקושר לשאלה. צריך את test.db.',
         'python3 tools/media/question_pdf.py 200 he && google-chrome --headless --no-pdf-header-footer --print-to-pdf=files/q200.pdf tools/media/q200_he.html', 'pdf'],
        ['media/lesson_video_he.py', 'שיעור וידאו לשאלה 200 בעברית, עם כתוביות. צריך Playwright ו-ffmpeg, ולכן לא רץ מכאן.', 'python3 tools/media/lesson_video_he.py'],
        ['media/lesson_video_en.py', 'שיעור וידאו לשאלה 200 באנגלית, עם קריינות (flite). צריך Playwright, numpy ו-ffmpeg עם flite, ולכן לא רץ מכאן.', 'python3 tools/media/lesson_video_en.py'],
    ]],
];

admin_header('כלי המרה והגדרות'); ?>
<div class="crumbs"><a href="index.php">פונקציות פיתוח</a> › כלי המרה והגדרות</div>
<section class="hero"><h1>כלי המרה והגדרות</h1>
<p>הגדרות האתר, קובצי PDF ושיעורי וידאו לשאלות, וכלי ההמרה שבתיקייה <code>tools</code>.</p></section>

<?php foreach ($msgs as $m): ?><p class="panel ok"><?= h($m) ?></p><?php endforeach; ?>
<?php foreach ($errors as $m): ?><p class="panel err"><?= h($m) ?></p><?php endforeach; ?>

<div class="stats">
  <div class="stat"><div class="stat-n"><?= number_format($memCount) ?></div><div class="stat-l">משפטים בזיכרון התרגום<br><span><?= is_file($mem) ? 'עודכן ' . date('d.m.Y H:i', filemtime($mem)) : 'הקובץ חסר' ?></span></div></div>
  <?php foreach ($cov as $label => [$done, $all]): ?>
  <div class="stat"><div class="stat-n"><?= $done ?> / <?= $all ?></div><div class="stat-l"><?= h($label) ?> מתורגמים לאנגלית<br><span><?= $all ? round(100 * $done / $all) : 0 ?>%</span></div></div>
  <?php endforeach; ?>
  <div class="stat"><div class="stat-n"><?= is_file($seed) ? size_label(filesize($seed)) : '—' ?></div><div class="stat-l">db/seed.sql<br><span><?= is_file($seed) ? date('d.m.Y H:i', filemtime($seed)) : 'הקובץ חסר' ?></span></div></div>
</div>

<h2>הגדרות האתר</h2>
<form method="post" class="panel form tform">
  <?= csrf_field() ?><input type="hidden" name="act" value="settings">
  <label>גרסה (מוצגת בתחתית כל דף)<input type="month" name="version" required value="<?= h($settings['version'] ?? '') ?>"></label>
  <label>אימייל למשוב ותיקונים<input type="email" name="feedback_email" required dir="ltr" value="<?= h($settings['feedback_email'] ?? '') ?>"></label>
  <button class="btn">שמירה</button>
  <p class="hint">נשמר במסד הנתונים ובקובץ <code>tools/site_settings.json</code>, כך שגם ייצוא הבא של המסד ישמור את הערכים.</p>
</form>

<h2>קובצי PDF ושיעורי וידאו לשאלות</h2>
<?php if ($withFiles): ?>
<div class="panel">
<table class="ttable ftable">
  <thead><tr><th>קוד שאלה</th><th>מקור</th><th>PDF</th><th>וידאו</th></tr></thead>
  <tbody>
  <?php foreach ($withFiles as $r): ?>
    <tr><td><a href="../question.php?id=<?= (int)$r['id'] ?>" target="_blank"><?= (int)$r['id'] ?></a></td>
      <td><?= h(trim(($r['exam_title'] ?? '') . ($r['question_no'] ? ', שאלה ' . (int)$r['question_no'] : ''))) ?></td>
      <?php foreach (['pdf', 'video'] as $kind): $f = $r[$kind . '_file']; ?>
      <td><?php if ($f):
            $dot = strrpos($f, '.'); $en = substr($f, 0, $dot) . '_en' . substr($f, $dot); ?>
        <a href="../files/<?= h($f) ?>" target="_blank"><?= h($f) ?></a><?= is_file("$FILES/$f") ? '' : ' <span class="bad">חסר</span>' ?>
        <?php if (is_file("$FILES/$en")): ?> · <a href="../files/<?= h($en) ?>" target="_blank">EN</a><?php endif; ?>
        <form method="post" class="inline" onsubmit="return confirm('להסיר את הקישור? הקבצים עצמם יישארו.')"><?= csrf_field() ?>
          <input type="hidden" name="act" value="qremove"><input type="hidden" name="qid" value="<?= (int)$r['id'] ?>"><input type="hidden" name="kind" value="<?= $kind ?>">
          <button class="linkbtn" title="הסרה">✕</button></form>
      <?php else: ?>–<?php endif; ?></td>
      <?php endforeach; ?></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="panel form tform">
  <?= csrf_field() ?><input type="hidden" name="act" value="qfiles">
  <label>קוד שאלה<input type="number" name="qid" min="1" required></label>
  <div class="fgrid">
    <label>PDF בעברית<input type="file" name="pdf_he" accept=".pdf,application/pdf"></label>
    <label>PDF באנגלית<input type="file" name="pdf_en" accept=".pdf,application/pdf"></label>
    <label>וידאו בעברית (mp4)<input type="file" name="video_he" accept=".mp4,video/mp4"></label>
    <label>וידאו באנגלית (mp4)<input type="file" name="video_en" accept=".mp4,video/mp4"></label>
  </div>
  <button class="btn">העלאה ושמירה</button>
  <p class="hint">הקבצים נשמרים בתיקייה <code>files</code> בשמות <code>q&lt;קוד&gt;.pdf</code>, <code>q&lt;קוד&gt;_en.pdf</code>, <code>q&lt;קוד&gt;.mp4</code> ו-<code>q&lt;קוד&gt;_en.mp4</code>, והשאלה מקבלת את הכפתורים באתר.
  גודל מרבי לקובץ לפי הגדרות PHP: <b><?= size_label($upMax) ?></b><?= $upMax < 20 * 1048576 ? ' – לסרטונים גדולים יותר צריך להגדיל את upload_max_filesize ו-post_max_size ב-php.ini של WAMP.' : '' ?></p>
</form>

<h2 id="run">כלי ההמרה</h2>
<p class="hint">הכפתור <b>הרצה</b> מפעיל את הכלי על המחשב הזה (Python של Windows) ומציג את הפלט כאן. לכלים בלי כפתור יש שורת פקודה להעתקה. הסבר מלא: <code>tools/README.md</code>.</p>
<?php if ($run): [$rt, $rc, $ro, $rs, $rx] = $run; ?>
<div class="panel runout <?= $rc === 0 ? 'ok' : 'err' ?>">
  <div class="runhead"><b dir="ltr"><?= h($rt) ?></b> <?= $rc === 0 ? 'הסתיים בהצלחה' : 'נכשל (קוד ' . (int)$rc . ')' ?> · <?= h($rs) ?> שניות <?= $rx ?></div>
  <pre dir="ltr"><?= h(mb_substr(trim($ro), -20000)) ?></pre>
</div>
<?php endif; ?>
<?php $status = ['test.db' => is_file($ROOT . '/test.db'), 'todo.json' => is_file($ROOT . '/todo.json'), 'db/seed.sqlite.sql' => is_file($ROOT . '/db/seed.sqlite.sql')]; ?>
<p class="hint">קבצי עבודה: <?php foreach ($status as $f => $ok): ?><code><?= h($f) ?></code> <?= $ok ? '✓' : '✗' ?> &nbsp; <?php endforeach; ?></p>
<?php foreach ($tools as [$group, $items]): ?>
<div class="panel tgroup">
  <h3><?= h($group) ?></h3>
  <?php foreach ($items as $it): [$name, $desc, $cmd] = $it; $rk = $it[3] ?? null; ?>
  <div class="trow">
    <div><b dir="ltr"><?= h($name) ?></b><p><?= h($desc) ?></p>
      <?php if ($cmd && substr($cmd, 0, 3) !== '../'): ?>
      <div class="cmd"><code dir="ltr"><?= h($cmd) ?></code><button type="button" class="linkbtn" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent);this.textContent='הועתק ✓'">העתקה</button></div>
      <?php endif; ?>
    </div>
    <?php if ($cmd && substr($cmd, 0, 3) === '../'): ?>
      <a class="btn small" href="<?= h($cmd) ?>">פתיחה</a>
    <?php elseif ($rk): ?>
      <form method="post" action="tools.php#run" class="runform" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='רץ…'">
        <?= csrf_field() ?><input type="hidden" name="act" value="run"><input type="hidden" name="tool" value="<?= h($rk) ?>">
        <?php if ($rk === 'export'): ?>
          <input type="text" name="gsa" dir="ltr" class="wide" value="<?= h($GSA_DEFAULT) ?>" title="קובץ הגיבוי">
          <select name="dialect"><option value="mysql">MySQL (seed.sql)</option><option value="sqlite">SQLite (לבדיקות)</option></select>
        <?php elseif ($rk === 'show'): ?>
          <input type="number" name="from" value="0" min="0" class="num"> – <input type="number" name="to" value="50" min="1" class="num">
        <?php elseif ($rk === 'pdf'): ?>
          <input type="number" name="qid" placeholder="קוד שאלה" min="1" required class="num">
          <select name="lang"><option value="he">עברית</option><option value="en">English</option></select>
        <?php endif; ?>
        <button class="btn small">הרצה</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php admin_footer();
