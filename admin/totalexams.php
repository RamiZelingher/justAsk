<?php
// Summary of the content by language and Bagrut level (4 / 5 units): exams, questions and theory items.
// Hebrew = all published questions; other languages = questions listed in question_languages for that language,
// theory items that have a translated title. Questions = questions of a Bagrut exam of that level (as on exams.php); exams = exams with at least one counted question.
require __DIR__ . '/_admin.php';
admin_guard();

$curricula = q("SELECT c.id, c.units FROM curricula c WHERE c.units IS NOT NULL" . fr_off_sql('c') . " ORDER BY c.sort_order");
$names = [];                                            // [curriculum id][lang] => name
foreach (q("SELECT curriculum_id, lang, name FROM curriculum_names") as $n) $names[$n['curriculum_id']][$n['lang']] = $n['name'];
$unitsLabel = ['he' => '%d יח"ל', 'en' => '%d units', 'ar' => '%d وحدات'];
function cur_name($names, $unitsLabel, $c, $lang) {
    $n = $names[$c['id']][$lang] ?? $names[$c['id']]['he'] ?? '';
    // the English/Arabic names (e.g. Pre-Calculus) do not say the level, so add it
    if ($lang !== 'he' && isset($names[$c['id']][$lang]) && strpos($n, (string)$c['units']) === false) $n .= ' (' . sprintf($unitsLabel[$lang] ?? '%d units', $c['units']) . ')';
    return $n;
}
$rows = [];
$FILES = dirname(__DIR__) . '/files';
foreach (LANGS as $lang => [$lname]) {
    if ($lang === 'fr') continue;                       // the French track has no 4/5-unit Bagrut
    $qcond = $lang === 'he' ? '' : " AND EXISTS (SELECT 1 FROM question_languages ql WHERE ql.question_id = qq.id AND ql.lang = " . db()->quote($lang) . ")";
    $tcond = $lang === 'he' ? '' : " AND EXISTS (SELECT 1 FROM translations tt WHERE tt.entity = 'theory' AND tt.entity_id = st.theory_id AND tt.field = 'title' AND tt.lang = " . db()->quote($lang) . ")";
    foreach ($curricula as $c) {
        // same rule as exams.php: questions that belong to a Bagrut exam of this level
        $qs = q("SELECT COUNT(*) AS nq, COUNT(DISTINCT e.id) AS ne FROM exams e JOIN questions qq ON qq.exam_id = e.id
                  WHERE qq.status = 'published' AND e.units = ?" . $qcond, [$c['units']], true);
        // questions that have a PDF file in this language (files/qN.pdf in Hebrew, files/qN_<lang>.pdf in the others)
        $np = 0;
        foreach (q("SELECT qq.pdf_file FROM exams e JOIN questions qq ON qq.exam_id = e.id
                     WHERE qq.status = 'published' AND e.units = ? AND qq.pdf_file IS NOT NULL" . $qcond, [$c['units']]) as $p) {
            $f = $p['pdf_file'];
            if ($lang !== 'he' && ($dot = strrpos($f, '.')) !== false) $f = substr($f, 0, $dot) . '_' . $lang . substr($f, $dot);
            if (preg_match('/^[\w.-]+$/', $f) && is_file($FILES . '/' . $f)) $np++;
        }
        $th = q("SELECT COUNT(DISTINCT st.theory_id) AS n FROM subject_theory st JOIN subjects s ON s.id = st.subject_id
                  WHERE s.curriculum_id = ?" . $tcond, [$c['id']], true);
        $rows[] = ['lang' => $lname, 'cur' => cur_name($names, $unitsLabel, $c, $lang), 'dir' => $lang === 'he' || $lang === 'ar' ? 'rtl' : 'ltr', 'ne' => (int)$qs['ne'], 'nq' => (int)$qs['nq'], 'np' => $np, 'nt' => (int)$th['n']];
    }
}
$tot = ['ne' => array_sum(array_column($rows, 'ne')), 'nq' => array_sum(array_column($rows, 'nq')), 'np' => array_sum(array_column($rows, 'np')), 'nt' => array_sum(array_column($rows, 'nt'))];

$asof = date('d/m/Y H:i');

/** The summary table (used on the page and in the PDF). */
function totals_table($rows, $tot) {
    ob_start(); ?>
<table class="ftable totals">
  <thead><tr><th>שפה</th><th>נושא נלמד</th><th>מספר בחינות</th><th>מספר שאלות (עם קובץ PDF)</th><th>מספר משפטי תאוריה</th></tr></thead>
  <tbody>
<?php foreach ($rows as $r): ?>
    <tr><td><?= h($r['lang']) ?></td><td dir="<?= $r['dir'] ?>"><?= h($r['cur']) ?></td><td><?= $r['ne'] ?></td><td><?= $r['nq'] ?> <span class="muted">(<?= $r['np'] ?> PDF)</span></td><td><?= $r['nt'] ?></td></tr>
<?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="2">סה"כ</th><th><?= $tot['ne'] ?></th><th><?= $tot['nq'] ?> (<?= $tot['np'] ?> PDF)</th><th><?= $tot['nt'] ?></th></tr></tfoot>
</table>
<?php return ob_get_clean();
}
$NOTE = 'בסוגריים: כמה מהשאלות יש להן קובץ PDF בשפה הזאת (בשפות אחרות האתר מציג את ה-PDF העברי כשאין קובץ מתורגם). שורת הסה"כ היא סכום השורות: בחינה או שאלה שמוצגות בכמה שפות נספרות פעם אחת בכל שפה.';

// ?pdf=1 : the same table as a PDF download, printed by headless Chrome / Edge (as in tools.php)
$pdfError = '';
if (($_GET['pdf'] ?? '') === '1') {
    $chrome = null;
    foreach (['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
              'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'] as $f) if (is_file($f)) { $chrome = $f; break; }
    if (!$chrome) foreach (glob('C:/Users/*/AppData/Local/Google/Chrome/Application/chrome.exe') ?: [] as $f) { $chrome = $f; break; }
    if (!$chrome) $pdfError = 'לא נמצא Chrome או Edge ליצירת PDF. אפשר להשתמש בכפתור "הדפסה" ולבחור "שמירה כ-PDF".';
    else {
        $tmp = str_replace('\\', '/', sys_get_temp_dir()) . '/justask-totals-' . bin2hex(random_bytes(4));
        $html = '<!DOCTYPE html><html lang="he" dir="rtl"><head><meta charset="utf-8"><title>סיכום בחינות</title><style>
body{font-family:Arial,"Segoe UI",sans-serif;color:#1d2433;margin:28px;font-size:13px}
h1{font-size:20px;margin:0 0 4px}.date{color:#5a6478;margin:0 0 16px}
table{border-collapse:collapse;width:100%}th,td{border:1px solid #d5dae6;padding:7px 9px;text-align:right}
td:nth-child(n+3),th:nth-child(n+3){text-align:center}thead th{background:#e9edf5}
tfoot th{background:#f1f3f8;border-top:2px solid #b9c0d0}.muted{color:#5a6478}.note{color:#5a6478;font-size:11px;margin-top:12px}
</style></head><body><h1>JustAsk · סיכום בחינות, שאלות ותאוריה</h1><p class="date">נכון לתאריך: ' . h($asof) . '</p>'
              . totals_table($rows, $tot) . '<p class="note">' . h($NOTE) . '</p></body></html>';
        file_put_contents("$tmp.html", $html);
        $cmd = [$chrome, '--headless=new', '--disable-gpu', '--no-pdf-header-footer', '--user-data-dir=' . sys_get_temp_dir() . '/justask-chrome',
                '--print-to-pdf=' . str_replace('/', '\\', "$tmp.pdf"), 'file:///' . "$tmp.html"];
        $p = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (is_resource($p)) {
            fclose($pipes[0]);
            $t0 = time();
            while (proc_get_status($p)['running'] && time() - $t0 < 60) { stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); usleep(150000); }
            fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
        }
        @unlink("$tmp.html");
        if (is_file("$tmp.pdf") && filesize("$tmp.pdf") > 0) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="totalexams_' . date('Y-m-d') . '.pdf"');
            header('Content-Length: ' . filesize("$tmp.pdf"));
            readfile("$tmp.pdf"); @unlink("$tmp.pdf");
            exit;
        }
        $pdfError = 'יצירת קובץ ה-PDF נכשלה. אפשר להשתמש בכפתור "הדפסה" ולבחור "שמירה כ-PDF".';
    }
}

admin_header('סיכום בחינות'); ?>
<h1>סיכום בחינות, שאלות ותאוריה</h1>
<p class="lead">לכל שפה ולכל מסלול: מספר הבחינות, מספר השאלות ומספר פריטי התאוריה שמוצגים באתר בשפה הזאת.
בעברית נספרות כל השאלות שפורסמו; בשפות האחרות רק שאלות ופריטי תאוריה שתורגמו.</p>
<?php if ($pdfError): ?><div class="panel err"><?= h($pdfError) ?></div><?php endif; ?>
<div class="totals-bar">
  <span class="asof">נכון לתאריך: <strong><?= h($asof) ?></strong></span>
  <span><a class="btn" href="?pdf=1">&#11015; הורדת PDF</a> <button type="button" class="btn ghost" onclick="window.print()">הדפסה</button></span>
</div>
<div class="panel"><?= totals_table($rows, $tot) ?></div>
<p class="muted"><?= h($NOTE) ?></p>
<?php admin_footer();
