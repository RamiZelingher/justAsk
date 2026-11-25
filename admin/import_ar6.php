<?php
// Development area: Arabic batch 5 — the last 4 four-unit exams (summer 2008 A and B, 22 questions) and the four-unit theory items (398).
// Data: db/import_ar6.json (machine translation, hints, solution steps and five-unit exams 2004-2008).
// Can be run again: every statement inserts or updates (ON DUPLICATE KEY / INSERT IGNORE). Runs in one transaction.
@set_time_limit(0);
require __DIR__ . '/_admin.php';
admin_guard();

$file = dirname(__DIR__) . '/db/import_ar6.json';
$data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
$stat = function () {
    return [
        'q'  => (int)q("SELECT COUNT(*) AS n FROM question_languages WHERE lang = 'ar'", [], true)['n'],
        'tr' => (int)q("SELECT COUNT(*) AS n FROM translations WHERE lang = 'ar'", [], true)['n'],
        'th' => (int)q("SELECT COUNT(*) AS n FROM translations WHERE lang = 'ar' AND entity = 'theory' AND field = 'title'", [], true)['n'],
        'sn' => (int)q("SELECT COUNT(*) AS n FROM subject_names WHERE lang = 'ar'", [], true)['n'],
    ];
};
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['go'] ?? '') === '1') {
    if (!csrf_ok()) $err = 'הטופס פג תוקף. נסה שוב.';
    elseif (!$data) $err = 'הקובץ db/import_ar6.json חסר.';
    else {
        $pdo = db();
        try {
            $pdo->exec('SET NAMES utf8mb4');
            $pdo->beginTransaction();
            $n = 0;
            foreach ($data['statements'] as $sql) { $pdo->exec($sql); $n++; }
            $pdo->commit();
            $msg = sprintf('הייבוא הצליח: בוצעו %d פקודות.', $n);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'הייבוא נכשל ובוטל (שום דבר לא השתנה). ' . $e->getMessage();
        }
    }
}
$s = $stat();
admin_header('ערבית: 30 שאלוני 5 יח"ל (2004–2008)');
?>
<h1>ערבית: 30 שאלוני 5 יח"ל (2004–2008)</h1>
<p class="lead">תרגום מלא לערבית של 30 בחינות 5 יח"ל (שאלונים ו' וז', 2004–2008, 156 שאלות) עם כל הרמזים ושלבי הפתרון. תרגום מכונה, לא בדוק.</p>
<?php if ($msg): ?><div class="panel" style="background:#e7f5ed;color:#1d7a4c"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="panel" style="background:#fdeceb;color:#a12b2b"><?= h($err) ?></div><?php endif; ?>
<div class="panel">
  <ul>
    <li>שאלות שמוצגות בערבית: <?= $s['q'] ?></li>
    <li>פריטי רקע תאורטי בערבית: <?= $s['th'] ?></li>
    <li>שמות נושאים בערבית: <?= $s['sn'] ?></li>
    <li>שורות תרגום בערבית: <?= $s['tr'] ?></li>
  </ul>
<?php if (!$data): ?>
  <p>הקובץ <code>db/import_ar6.json</code> חסר.</p>
<?php else: ?>
  <p>הייבוא מוסיף או מעדכן תרגומים בלבד (<?= count($data['statements']) ?> פקודות) ואפשר להריץ אותו שוב.</p>
  <form method="post"><?= csrf_field() ?><button class="btn" name="go" value="1">הוסף את התרגום לערבית</button></form>
<?php endif; ?>
  <p><a href="totalexams.php">לסיכום המבחנים ←</a></p>
</div>
<?php admin_footer();
