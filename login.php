<?php
require __DIR__ . '/inc/layout.php';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
if (current_user()) { header('Location: ' . $next); exit; }
$err = ''; $name = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = (string)($_POST['user'] ?? '');
    if (!csrf_ok()) $err = 'err_csrf';
    else {
        $err = login_attempt($name, (string)($_POST['password'] ?? ''));
        if ($err === '') { header('Location: ' . $next); exit; }
    }
}
page_header(t('login_title')); ?>
<section class="panel authbox">
  <h1><?= h(t('login_title')) ?></h1>
  <?php if ($err): ?><p class="amsg err"><?= h(t($err)) ?></p>
  <?php elseif (!empty($_GET['need'])): ?><p class="amsg"><?= h(t('login_needed')) ?></p><?php endif; ?>
  <form method="post" class="aform">
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= h($next) ?>">
    <label><?= h(t('login_user')) ?><input name="user" value="<?= h($name) ?>" autocomplete="username" dir="ltr" required autofocus></label>
    <label><?= h(t('password')) ?><input type="password" name="password" autocomplete="current-password" dir="ltr" required></label>
    <button class="abtn"><?= h(t('login')) ?></button>
  </form>
  <p class="alink"><a href="register.php?next=<?= h(rawurlencode($next)) ?>"><?= h(t('no_account')) ?></a></p>
</section>
<?php page_footer();
