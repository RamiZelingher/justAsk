<?php
require __DIR__ . '/inc/layout.php';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
if (current_user()) { header('Location: ' . $next); exit; }
$f = ['full_name' => '', 'username' => '', 'email' => ''];
$errs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    if (!csrf_ok()) $errs[] = 'err_csrf';
    else {
        user_tables();
        $errs = user_check($f['username'], $f['email'], $f['full_name'], $pw);
        if ($pw !== (string)($_POST['password2'] ?? '')) $errs[] = 'err_password2';
    }
    if (!$errs) {
        $id = user_create($f['username'], $f['email'], $f['full_name'], $pw, 'student');
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
        q("UPDATE users SET last_login_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $id]);
        header('Location: ' . $next); exit;
    }
}
page_header(t('register_title')); ?>
<section class="panel authbox">
  <h1><?= h(t('register_title')) ?></h1>
  <p class="anote"><?= h(t('reg_note')) ?></p>
  <?php foreach ($errs as $e): ?><p class="amsg err"><?= h(t($e)) ?></p><?php endforeach; ?>
  <form method="post" class="aform">
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= h($next) ?>">
    <label><?= h(t('full_name')) ?><input name="full_name" value="<?= h($f['full_name']) ?>" autocomplete="name" required></label>
    <label><?= h(t('username')) ?><input name="username" value="<?= h($f['username']) ?>" autocomplete="username" dir="ltr" required pattern="[A-Za-z0-9_.\-]{3,40}"><small><?= h(t('username_help')) ?></small></label>
    <label><?= h(t('email_opt')) ?><input type="email" name="email" value="<?= h($f['email']) ?>" autocomplete="email" dir="ltr"></label>
    <label><?= h(pw_label()) ?><input type="password" name="password" autocomplete="new-password" dir="ltr" required minlength="<?= PASSWORD_MIN ?>"></label>
    <label><?= h(t('password2')) ?><input type="password" name="password2" autocomplete="new-password" dir="ltr" required minlength="<?= PASSWORD_MIN ?>"></label>
    <button class="abtn"><?= h(t('register')) ?></button>
  </form>
  <p class="alink"><a href="login.php?next=<?= h(rawurlencode($next)) ?>"><?= h(t('have_account')) ?></a></p>
</section>
<?php page_footer();
