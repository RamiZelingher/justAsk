<?php
// The logged-in user's page: the questions a student visited, their teachers, and a password change for everyone.
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/visits_view.php';
$u = require_login();
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $row = q("SELECT password_hash FROM users WHERE id = ?", [$u['id']], true);
    $new = (string)($_POST['password'] ?? '');
    if (!csrf_ok()) $err = 'err_csrf';
    elseif (!password_verify((string)($_POST['current'] ?? ''), $row['password_hash'])) $err = 'err_cur_pass';
    elseif (pw_short($new)) $err = pw_err();
    elseif ($new !== (string)($_POST['password2'] ?? '')) $err = 'err_password2';
    else {
        q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        $msg = 'pass_changed';
    }
}
$teachers = $u['role'] === 'student'
    ? q("SELECT u.full_name FROM teacher_students ts JOIN users u ON u.id = ts.teacher_id WHERE ts.student_id = ? AND u.is_active = 1 ORDER BY u.full_name", [$u['id']])
    : [];
page_header($u['role'] === 'student' ? t('my_questions') : t('my_account')); ?>
<section class="hero">
  <h1><?= h($u['role'] === 'student' ? t('my_title') : t('my_account')) ?></h1>
  <p><?= h($u['full_name']) ?> · <bdi dir="ltr"><?= h($u['username']) ?></bdi> · <?= h(t('role_' . $u['role'])) ?>
  <?php if ($teachers): ?> · <?= h(t('my_teachers')) ?>: <?= h(implode(', ', array_column($teachers, 'full_name'))) ?><?php endif; ?></p>
</section>

<?php if ($u['role'] === 'student'): render_visits($u['id'], $_GET['view'] ?? 'q', 'my.php'); else: ?>
<p><a class="abtn ghost" href="teacher.php"><?= h(t('my_students')) ?></a></p>
<?php endif; ?>

<details class="panel passbox"<?= ($err || $msg) ? ' open' : '' ?>>
  <summary><?= h(t('change_pass')) ?></summary>
  <?php if ($err): ?><p class="amsg err"><?= h(t($err)) ?></p><?php endif; ?>
  <?php if ($msg): ?><p class="amsg ok"><?= h(t($msg)) ?></p><?php endif; ?>
  <form method="post" class="aform">
    <?= csrf_field() ?><input type="hidden" name="change_pass" value="1">
    <label><?= h(t('cur_pass')) ?><input type="password" name="current" autocomplete="current-password" dir="ltr" required></label>
    <label><?= h(pw_label()) ?><input type="password" name="password" autocomplete="new-password" dir="ltr" required minlength="<?= PASSWORD_MIN ?>"></label>
    <label><?= h(t('password2')) ?><input type="password" name="password2" autocomplete="new-password" dir="ltr" required minlength="<?= PASSWORD_MIN ?>"></label>
    <button class="abtn"><?= h(t('save')) ?></button>
  </form>
</details>
<?php page_footer();
