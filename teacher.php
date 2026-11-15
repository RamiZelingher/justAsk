<?php
// A teacher's students and the questions each of them visited. An administrator sees every student.
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/visits_view.php';
$u = require_login('teacher', 'admin');
$admin = $u['role'] === 'admin';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $err = 'err_csrf';
    elseif (isset($_POST['add'])) {
        $s = q("SELECT id FROM users WHERE username = ? AND role = 'student' AND is_active = 1", [trim((string)$_POST['username'])], true);
        if (!$s) $err = 'student_not_found';
        else { q("INSERT IGNORE INTO teacher_students (teacher_id, student_id) VALUES (?, ?)", [$u['id'], $s['id']]); $msg = 'student_added'; }
    } elseif (isset($_POST['remove'])) {
        q("DELETE FROM teacher_students WHERE teacher_id = ? AND student_id = ?", [$u['id'], (int)$_POST['remove']]);
        $msg = 'student_removed';
    }
}

$sid = (int)($_GET['student'] ?? 0);
if ($sid) {
    $st = q("SELECT id, full_name, username FROM users WHERE id = ? AND role = 'student'", [$sid], true);
    if (!$st || !can_see_student($sid)) not_found();
    page_header(t('visits_of', $st['full_name'])); ?>
<div class="crumbs"><a href="teacher.php"><?= h(t('back_students')) ?></a></div>
<h1 class="page"><?= h(t('visits_of', $st['full_name'])) ?></h1>
<p class="muted"><bdi dir="ltr"><?= h($st['username']) ?></bdi></p>
<?php render_visits($sid, $_GET['view'] ?? 'q', 'teacher.php?student=' . $sid);
    page_footer(); exit;
}

user_tables();
$cols = "u.id, u.full_name, u.username,
         (SELECT COUNT(DISTINCT v.question_id) FROM question_visits v WHERE v.user_id = u.id) AS nq,
         (SELECT MAX(v.visited_at) FROM question_visits v WHERE v.user_id = u.id) AS last";
$mine = q("SELECT $cols FROM users u JOIN teacher_students ts ON ts.student_id = u.id AND ts.teacher_id = ?
            WHERE u.role = 'student' ORDER BY u.full_name", [$u['id']]);
$all = $admin ? q("SELECT $cols FROM users u WHERE u.role = 'student' ORDER BY u.full_name") : [];

function students_table($rows, $can_remove) { ?>
<div class="utable-wrap"><table class="utable">
  <thead><tr><th><?= h(t('col_student')) ?></th><th><?= h(t('username')) ?></th><th><?= h(t('col_nq')) ?></th><th><?= h(t('col_last')) ?></th><?php if ($can_remove): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="teacher.php?student=<?= (int)$r['id'] ?>"><?= h($r['full_name']) ?></a></td>
      <td dir="ltr"><?= h($r['username']) ?></td>
      <td class="num"><?= (int)$r['nq'] ?></td>
      <td class="dt"><?= h(fmt_dt($r['last'])) ?></td>
      <?php if ($can_remove): ?><td><form method="post" class="inline"><?= csrf_field() ?><button class="linkbtn" name="remove" value="<?= (int)$r['id'] ?>"><?= h(t('remove')) ?></button></form></td><?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php }

page_header(t('my_students')); ?>
<section class="hero"><h1><?= h(t('my_students')) ?></h1><p><?= h($u['full_name']) ?> · <?= h(t('role_' . $u['role'])) ?></p></section>
<?php if ($err): ?><p class="amsg err"><?= h(t($err)) ?></p><?php endif; ?>
<?php if ($msg): ?><p class="amsg ok"><?= h(t($msg)) ?></p><?php endif; ?>

<form method="post" class="panel addform">
  <?= csrf_field() ?>
  <label><?= h(t('add_student')) ?><input name="username" dir="ltr" required></label>
  <button class="abtn" name="add" value="1"><?= h(t('add_btn')) ?></button>
</form>

<?php if ($mine): students_table($mine, true); else: ?><div class="panel empty"><?= h(t('no_students')) ?></div><?php endif; ?>

<?php if ($admin): ?>
<h2 class="usec"><?= h(t('all_students')) ?></h2>
<?php if ($all) students_table($all, false); else echo '<div class="panel empty">—</div>'; ?>
<?php endif; ?>
<?php page_footer();
