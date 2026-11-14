<?php
@set_time_limit(0);              // the full seed has ~23,000 rows
@ini_set('memory_limit', '512M');
// One-click installer: creates the database, the tables, and loads the questions.
// Open http://justask/install.php once. Running it again (install.php?force=1) rebuilds everything from db/*.sql.
header('Content-Type: text/html; charset=utf-8');
$cfg = require __DIR__ . '/config.php';
$lock = __DIR__ . '/install.lock';
$force = isset($_GET['force']);
$run = isset($_POST['go']);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl"><head><meta charset="utf-8"><title>התקנת JustAsk</title>
<link rel="stylesheet" href="style.css"></head>
<body><main class="wrap" style="max-width:720px">
<section class="hero"><h1>התקנת מסד הנתונים</h1></section>
<div class="panel">
<?php
if (file_exists($lock) && !$force && !$run) {
    echo '<p>מסד הנתונים כבר הותקן (' . htmlspecialchars(file_get_contents($lock)) . ').</p>';
    echo '<p><a href="index.php">לאתר ←</a></p>';
    echo '<p style="color:#888">להתקנה מחדש (מוחק ובונה מחדש את כל הטבלאות): <a href="install.php?force=1">install.php?force=1</a></p>';
} elseif (!$run) {
    printf('<p>ההתקנה תיצור את מסד הנתונים <b>%s</b> בשרת <b>%s:%d</b> (משתמש: %s), ותטען את הנושאים (בשלוש שפות) ואת השאלות.</p>',
        htmlspecialchars($cfg['db_name']), htmlspecialchars($cfg['db_host']), $cfg['db_port'], htmlspecialchars($cfg['db_user']));
    if ($force) echo '<p style="color:#b00">שים לב: הטבלאות הקיימות יימחקו וייבנו מחדש מהקבצים. נושאים שנוספו באזור הפיתוח יימחקו.</p>';
    echo '<form method="post"><button name="go" value="1" style="font:inherit;padding:10px 22px;border-radius:10px;border:0;background:#2453c4;color:#fff;cursor:pointer">התקן</button></form>';
} else {
    try {
        $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['db_host'], $cfg['db_port']),
                       $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        $db = str_replace('`', '', $cfg['db_name']);

        // schema: statements end with ";" at end of line; database name comes from config.php
        $schema = file_get_contents(__DIR__ . '/db/schema.sql');
        $schema = preg_replace('/^--.*$/m', '', $schema);
        $n1 = 0;
        foreach (preg_split('/;\s*\n/', $schema) as $sql) {
            $sql = trim($sql);
            if ($sql === '') continue;
            if (stripos($sql, 'CREATE DATABASE') === 0) $sql = "CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if (stripos($sql, 'USE ') === 0) $sql = "USE `$db`";
            $pdo->exec($sql); $n1++;
            if (stripos($sql, 'CREATE DATABASE') === 0) {
                // the database may already exist (hosting) with another default charset: Hebrew would be stored as "?"
                try { $pdo->exec("ALTER DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Exception $e) { }
            }
        }
        $n2 = 0;
        $pdo->exec("USE `$db`");
        $pdo->exec('SET NAMES utf8mb4');
        $pdo->beginTransaction();
        // statements may span lines (text contains line breaks): split only where a new statement starts
        $seed = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/db/seed.sql'));
        $seed = preg_replace('/^--[^\n]*\n/m', '', $seed);   // drop comment lines
        foreach (preg_split('/;\n(?=INSERT INTO |USE |SET |--|$)/', $seed) as $sql) {
            $sql = trim($sql);
            if (strpos($sql, 'INSERT INTO ') !== 0) continue;
            $pdo->exec($sql); $n2++;
        }
        $pdo->commit();
        $counts = [];
        foreach (['questions' => 'שאלות', 'hints' => 'רמזים', 'solution_steps' => 'שלבי פתרון', 'theory_items' => 'פריטי רקע תאורטי'] as $t => $label) {
            $counts[] = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . ' ' . $label;
        }
        file_put_contents($lock, date('Y-m-d H:i') . ', ' . $ver);
        echo '<p style="color:#1d7a4c;font-weight:600">ההתקנה הצליחה.</p>';
        echo '<p>שרת: ' . htmlspecialchars($ver) . '<br>נוצרו ' . $n1 . ' פקודות מבנה, נטענו ' . $n2 . ' רשומות: ' . implode(', ', $counts) . '.</p>';
        echo '<p><a href="index.php">לאתר ←</a></p>';
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        echo '<p style="color:#b00;font-weight:600">ההתקנה נכשלה.</p>';
        echo '<p style="direction:ltr;text-align:left;color:#555">' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>בדוק ש-WAMP פועל (הסמל ירוק), ושההגדרות ב-<code>config.php</code> נכונות. ב-MariaDB של WAMP הפורט הוא 3307.</p>';
    }
}
?>
</div></main></body></html>
