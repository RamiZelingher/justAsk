<?php
// Development area: add the 2006-2008 bagrut exams (questionnaires D, E, F, G) from the Units45 2010 database.
// Data: db/import_2010.json (built by tools/legacy2010/build_import.py). Adds rows only; existing ids stay the same.
// Before importing, the whole database is saved to db/backup_before_2010_<date>.sql.
@set_time_limit(0);
@ini_set('memory_limit', '768M');
require __DIR__ . '/_admin.php';
admin_guard();

$file = dirname(__DIR__) . '/db/import_2010.json';
$data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
$done = (bool)q("SELECT 1 FROM exams WHERE legacy_id = 'D2006WA'", [], true);
$max = [];
foreach (['exams', 'questions', 'question_parts', 'hints', 'solution_steps', 'theory_items'] as $t) {
    $max[$t] = (int)q("SELECT COALESCE(MAX(id), 0) AS m FROM `$t`", [], true)['m'];
}
$ok_ids = $data && !array_diff_assoc($data['check'], $max);

/** Save every table of the database as INSERT statements. Returns [file name, rows]. */
function backup_db() {
    $pdo = db();
    $name = 'backup_before_2010_' . date('Y-m-d_His') . '.sql';
    $fh = fopen(dirname(__DIR__) . '/db/' . $name, 'w');
    fwrite($fh, "-- JustAsk database backup before the 2006-2008 import, " . date('Y-m-d H:i') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $rows = 0;
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $st = $pdo->query("SELECT * FROM `$t`", PDO::FETCH_ASSOC);
        foreach ($st as $r) {
            $vals = array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote($v); }, array_values($r));
            fwrite($fh, "INSERT INTO `$t` (`" . implode('`, `', array_keys($r)) . "`) VALUES (" . implode(', ', $vals) . ");\n");
            $rows++;
        }
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
    return [$name, $rows];
}

$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['go'] ?? '') === '1') {
    if (!csrf_ok()) $err = 'הטופס פג תוקף. נסה שוב.';
    elseif (!$data) $err = 'הקובץ db/import_2010.json חסר.';
    elseif ($done) $err = 'הבחינות כבר נוספו.';
    elseif (!$ok_ids) $err = 'מסד הנתונים שונה ממה שהייבוא מצפה לו, ולכן הייבוא לא בוצע.';
    else {
        [$bfile, $brows] = backup_db();
        $pdo = db();
        try {
            $pdo->exec('SET NAMES utf8mb4');
            $pdo->beginTransaction();
            $n = 0;
            foreach ($data['statements'] as $sql) { $pdo->exec($sql); $n++; }
            $pdo->commit();
            $done = true;
            $msg = sprintf('הייבוא הצליח: בוצעו %d פקודות. גיבוי של מסד הנתונים לפני הייבוא נשמר ב-db/%s (%d רשומות).', $n, $bfile, $brows);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'הייבוא נכשל ובוטל (שום דבר לא השתנה). ' . $e->getMessage();
        }
    }
}

// PDF of each 2006-2008 question (files/q642.pdf ... q852.pdf, made with tools/media/question_pdf.py on 21 Nov 2026)
$nopdf = $done ? q("SELECT q.id FROM questions q JOIN exams e ON e.id = q.exam_id WHERE e.year BETWEEN 2006 AND 2008 AND q.pdf_file IS NULL") : [];
$nopdf = array_values(array_filter($nopdf, function ($r) { return is_file(dirname(__DIR__) . '/files/q' . (int)$r['id'] . '.pdf'); }));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['go'] ?? '') === 'pdf' && csrf_ok() && $nopdf) {
    foreach ($nopdf as $r) q("UPDATE questions SET pdf_file = ? WHERE id = ?", ['q' . (int)$r['id'] . '.pdf', (int)$r['id']]);
    $msg = sprintf('קובצי ה-PDF קושרו ל-%d שאלות.', count($nopdf));
    $nopdf = [];
}
$npdf = $done ? (int)q("SELECT COUNT(*) AS n FROM questions q JOIN exams e ON e.id = q.exam_id WHERE e.year BETWEEN 2006 AND 2008 AND q.pdf_file IS NOT NULL", [], true)['n'] : 0;

$cnt = q("SELECT e.units, COUNT(DISTINCT e.id) AS exams, COUNT(q.id) AS questions
            FROM exams e LEFT JOIN questions q ON q.exam_id = e.id
           WHERE e.year BETWEEN 2006 AND 2008 GROUP BY e.units ORDER BY e.units");
admin_header('בחינות 2006–2008');
?>
<h1>הוספת הבחינות של 2006–2008</h1>
<p class="lead">40 בחינות בגרות (שאלונים ד', ה' – 4 יח"ל; ו', ז' – 5 יח"ל), 211 שאלות, מתוך מסד הנתונים של המערכת הישנה מ-2010.
השאלות הקיימות, התרגומים ונתוני המשתמשים לא משתנים.</p>
<?php if ($msg): ?><div class="panel" style="background:#e7f5ed;color:#1d7a4c"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="panel" style="background:#fdeceb;color:#a12b2b"><?= h($err) ?></div><?php endif; ?>
<div class="panel">
<?php if ($done): ?>
  <p><b>הבחינות נמצאות במסד הנתונים.</b></p>
  <ul><?php foreach ($cnt as $c): ?><li><?= (int)$c['units'] ?> יח"ל: <?= (int)$c['exams'] ?> בחינות, <?= (int)$c['questions'] ?> שאלות</li><?php endforeach; ?></ul>
  <p>קובצי PDF: <?= $npdf ?> שאלות מקושרות.</p>
  <?php if ($nopdf): ?>
  <form method="post"><?= csrf_field() ?><button class="btn" name="go" value="pdf">קשר <?= count($nopdf) ?> קובצי PDF לשאלות</button></form>
  <?php endif; ?>
  <p><a href="../index.php">לאתר ←</a></p>
<?php elseif (!$data): ?>
  <p>הקובץ <code>db/import_2010.json</code> חסר.</p>
<?php elseif (!$ok_ids): ?>
  <p>מסד הנתונים שונה ממה שהייבוא מצפה לו (מספר הרשומות האחרון בטבלאות):</p>
  <ul><?php foreach ($data['check'] as $t => $v): ?><li><?= h($t) ?>: צפוי <?= (int)$v ?>, בפועל <?= (int)$max[$t] ?></li><?php endforeach; ?></ul>
<?php else: ?>
  <p>לפני הייבוא כל מסד הנתונים יישמר כגיבוי בתיקייה <code>db</code>. הייבוא רץ כפעולה אחת: אם משהו נכשל, שום דבר לא משתנה.</p>
  <form method="post"><?= csrf_field() ?><button class="btn" name="go" value="1">הוסף את הבחינות</button></form>
<?php endif; ?>
</div>
<?php admin_footer();
