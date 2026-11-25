<?php
// English translation of the 211 questions from the 2006-2008 exams (imported from the Units45 2010 database).
// Data: db/import_en2010.json (built from tools/translations/en.json).
// Can be run again: every statement inserts or updates (ON DUPLICATE KEY / INSERT IGNORE). Runs in one transaction.
@set_time_limit(0);
require __DIR__ . '/_admin.php';
admin_guard();

$file = dirname(__DIR__) . '/db/import_en2010.json';
$data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
$stat = function () {
    return [
        'q'  => (int)q("SELECT COUNT(*) AS n FROM question_languages WHERE lang = 'en'", [], true)['n'],
        'tr' => (int)q("SELECT COUNT(*) AS n FROM translations WHERE lang = 'en'", [], true)['n'],
    ];
};
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['go'] ?? '') === '1') {
    if (!csrf_ok()) $err = 'הטופס פג תוקף. נסה שוב.';
    elseif (!$data) $err = 'הקובץ db/import_en2010.json חסר.';
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
admin_header('אנגלית לשאלות 2006–2008');
?>
<h1>תרגום לאנגלית – שאלות 2006–2008</h1>
<p class="lead">211 השאלות ממבחני 2006–2008 עם כל הרמזים ושלבי הפתרון. תרגום מכונה – כל השורות מסומנות כלא־בדוקות (is_reviewed = 0).</p>
<?php if ($msg): ?><div class="panel" style="background:#e7f5ed;color:#1d7a4c"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="panel" style="background:#fdeceb;color:#a12b2b"><?= h($err) ?></div><?php endif; ?>
<div class="panel">
  <ul>
    <li>שאלות שמוצגות באנגלית (בסך הכול): <?= $s['q'] ?></li>
    <li>שורות תרגום באנגלית: <?= $s['tr'] ?></li>
  </ul>
<?php if (!$data): ?>
  <p>הקובץ <code>db/import_en2010.json</code> חסר.</p>
<?php else: ?>
  <p>הייבוא מוסיף או מעדכן תרגומים בלבד (<?= count($data['statements']) ?> פקודות) ואפשר להריץ אותו שוב.</p>
  <form method="post"><?= csrf_field() ?><button class="btn" name="go" value="1">הוסף / עדכן את התרגום לאנגלית</button></form>
<?php endif; ?>
</div>
<?php admin_footer();
