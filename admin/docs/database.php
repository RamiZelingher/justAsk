<?php
require __DIR__ . '/../_admin.php';
admin_guard();

$docs = require __DIR__ . '/db_tables.php';
$pdo  = db();

// Live structure from MySQL: columns, foreign keys, row counts
$live = [];
foreach ($pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_KEY k, COLUMN_DEFAULT d, EXTRA x
                        FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION") as $r) {
    $live[$r['t']][$r['c']] = $r;
}
$fks = [];
foreach ($pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME rt, REFERENCED_COLUMN_NAME rc
                        FROM information_schema.KEY_COLUMN_USAGE
                       WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL") as $r) {
    $fks[$r['t']][$r['c']] = $r['rt'] . '.' . $r['rc'];
}
$counts = [];
foreach (array_keys($live) as $t) {
    try { $counts[$t] = (int)$pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $t) . '`')->fetchColumn(); }
    catch (Throwable $e) { $counts[$t] = null; }
}

$documented = [];
foreach ($docs as $group) foreach ($group as $t => $_) $documented[$t] = true;
/** Escape a Hebrew explanation and isolate the Latin words in it (names, values, file names), so they keep their order. */
function doc_text($s) {
    return preg_replace('~(?<![&#A-Za-z0-9_])[\^A-Za-z\x{C0}-\x{24F}](?:[A-Za-z0-9_\x{C0}-\x{24F}.\-/@\^?=…]*[A-Za-z0-9_\x{C0}-\x{24F}…])?(?:\x20[A-Za-z\x{C0}-\x{24F}][A-Za-z0-9_\x{C0}-\x{24F}]*)*~u', '<bdi class="lt">$0</bdi>', h($s));
}
$undoc_tables = array_diff(array_keys($live), array_keys($documented));

admin_header('תיעוד מסד הנתונים'); ?>
<section class="hero"><h1>תיעוד מסד הנתונים</h1>
<p>כל הטבלאות של האתר, עם הסבר לכל עמודה. הסוגים, המפתחות ומספר השורות נקראים עכשיו ממסד הנתונים עצמו (<code><?= h($pdo->query('SELECT DATABASE()')->fetchColumn()) ?></code>), כך שהדף תמיד מתאים למבנה הקיים. ההסברים נמצאים בקובץ <code>admin/docs/db_tables.php</code>.</p></section>

<nav class="panel dbtoc">
<?php foreach ($docs as $gname => $tables): ?>
  <div><strong><?= h($gname) ?></strong>
  <?php foreach ($tables as $t => $_): ?><a href="#t-<?= h($t) ?>"><?= h($t) ?></a><?php endforeach; ?>
  </div>
<?php endforeach; ?>
</nav>

<?php if ($undoc_tables): ?>
<div class="panel warn">טבלאות במסד הנתונים שאין להן תיעוד: <code><?= h(implode(', ', $undoc_tables)) ?></code></div>
<?php endif; ?>

<?php foreach ($docs as $gname => $tables): ?>
<h2 class="dbgroup"><?= h($gname) ?></h2>
<?php foreach ($tables as $t => $info):
    $cols = $live[$t] ?? null;
    $extra = $cols ? array_diff(array_keys($cols), array_keys($info['cols'])) : []; ?>
<section class="panel dbtable" id="t-<?= h($t) ?>">
  <h3><code><?= h($t) ?></code>
    <?php if ($cols === null): ?><span class="dbbadge bad">לא קיימת במסד הנתונים</span>
    <?php else: ?><span class="dbbadge"><?= $counts[$t] === null ? '?' : number_format($counts[$t]) ?> שורות</span><?php endif; ?>
  </h3>
  <p><?= doc_text($info['desc']) ?></p>
  <div class="dbwrap"><table class="ftable dbcols">
    <thead><tr><th>עמודה</th><th>סוג</th><th>מפתח</th><th>הסבר</th></tr></thead>
    <tbody>
    <?php foreach ($info['cols'] + array_fill_keys($extra, null) as $c => $desc):
        $l = $cols[$c] ?? null;
        $key = [];
        if ($l && $l['k'] === 'PRI') $key[] = 'ראשי';
        if ($l && $l['k'] === 'UNI') $key[] = 'ייחודי';
        if (isset($fks[$t][$c])) $key[] = '<span class="fk">→ <a href="#t-' . h(strtok($fks[$t][$c], '.')) . '">' . h($fks[$t][$c]) . '</a></span>'; ?>
      <tr<?= $l ? '' : ' class="missing"' ?>>
        <td dir="ltr"><code><?= h($c) ?></code></td>
        <td dir="ltr" class="ty"><?php if ($l): ?><?= h($l['ty']) ?><?= $l['n'] === 'YES' ? ' <span class="nul">NULL</span>' : '' ?><?php if ($l['x'] === 'auto_increment'): ?> <span class="nul">auto</span><?php endif; ?><?php else: ?>—<?php endif; ?></td>
        <td><?= implode('<br>', $key) ?></td>
        <td><?php if ($desc === null): ?><span class="nodoc">אין הסבר — להוסיף ב-db_tables.php</span>
            <?php else: ?><?= doc_text($desc) ?><?php if (!$l): ?> <span class="nodoc">(העמודה לא קיימת במסד הנתונים)</span><?php endif; ?><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; endforeach; ?>
<?php admin_footer();
