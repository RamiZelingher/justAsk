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
// Tutorial video: put the file at media/tutorial/login.mp4.
$tut = '';
foreach (['media/tutorial/login.mp4'] as $f) {
    if (is_file(__DIR__ . '/' . $f)) { $tut = $f; break; }
}
// Login page texts are English only (Rami, 21 Nov 2026)
function te($k){ $v = T[$k] ?? null; return is_array($v) ? ($v['en'] ?? $k) : t($k); }
page_header(te('login_title')); ?>
<div class="loginwrap" dir="ltr" lang="en">
  <section class="loginframe">
    <h1 class="loginhead"><?= h(te('login_title')) ?></h1>
    <div class="loginbody">
      <?php if ($err): ?><p class="amsg err"><?= h(te($err)) ?></p>
      <?php elseif (!empty($_GET['need'])): ?><p class="amsg"><?= h(te('login_needed')) ?></p><?php endif; ?>
      <!-- Fields start read-only so the browser does not fill a saved username/password by itself (Rami, 21 Nov 2026). -->
      <form method="post" class="aform" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="next" value="<?= h($next) ?>">
        <label><?= h(te('username')) ?><input name="user" value="<?= h($name) ?>" autocomplete="off" dir="ltr" required readonly onfocus="this.removeAttribute('readonly')"></label>
        <label><?= h(te('password')) ?><input type="password" name="password" autocomplete="off" dir="ltr" required readonly onfocus="this.removeAttribute('readonly')"></label>
        <button class="abtn loginbtn"><?= h(te('login')) ?></button>
      </form>
      <p class="alink"><a href="register.php?next=<?= h(rawurlencode($next)) ?>"><?= h(te('no_account')) ?></a></p>
    </div>
  </section>
  <button type="button" class="tutbtn" id="tutOpen">&#9654;&ensp;<?= h(te('tutorial_btn')) ?></button>
</div>
<dialog class="tutdlg" id="tutDlg" dir="ltr" lang="en">
  <div class="tutbar"><strong><?= h(te('tutorial_btn')) ?></strong><button type="button" class="tutx" id="tutClose" aria-label="<?= h(te('close')) ?>">&times;</button></div>
  <?php if ($tut): ?>
    <video id="tutVideo" src="<?= h($tut) ?>" controls preload="none"></video>
  <?php else: ?>
    <p class="tutsoon"><?= h(te('tutorial_soon')) ?></p>
  <?php endif; ?>
</dialog>
<script>
(function(){
  var d=document.getElementById('tutDlg'), v=document.getElementById('tutVideo');
  document.getElementById('tutOpen').onclick=function(){ d.showModal ? d.showModal() : d.setAttribute('open',''); if(v){v.play();} };
  function shut(){ if(v){v.pause();} d.close ? d.close() : d.removeAttribute('open'); }
  document.getElementById('tutClose').onclick=shut;
  d.addEventListener('click',function(e){ if(e.target===d) shut(); });
  d.addEventListener('close',function(){ if(v){v.pause();} });
})();
</script>
<?php page_footer();
