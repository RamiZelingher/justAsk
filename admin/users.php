<?php
// Users of the site: create users (student / teacher / admin), change role, block, reset a password,
// and link students to teachers.
require __DIR__ . '/_admin.php';
admin_guard();
user_tables();

$msg = ''; $err = '';
$ROLE_HE = ['student' => 'תלמיד', 'teacher' => 'מורה', 'admin' => 'מנהל מערכת'];
$LANG_HE = array_intersect_key(['he' => 'עברית', 'en' => 'אנגלית', 'ar' => 'ערבית', 'fr' => 'צרפתית'], LANGS);
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if (!csrf_ok()) $err = 'הטופס פג תוקף. נסה שוב.';
    elseif ($a === 'create') {
        $f = array_map(function ($k) { return trim((string)($_POST[$k] ?? '')); }, ['username' => 'username', 'email' => 'email', 'full_name' => 'full_name', 'role' => 'role']);
        $pw = (string)($_POST['password'] ?? '');
        $errs = user_check($f['username'], $f['email'], $f['full_name'], $pw);
        if ($errs) $err = implode(' ', array_map('t', $errs));
        else { user_create($f['username'], $f['email'], $f['full_name'], $pw, $f['role'], (string)($_POST['lang'] ?? 'he'), $_POST['course'] ?? null); $msg = 'המשתמש ' . $f['username'] . ' נוצר.'; }
    } elseif ($a === 'update') {
        $id = (int)$_POST['id'];
        $role = in_array($_POST['role'] ?? '', ROLES, true) ? $_POST['role'] : 'student';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $ulang = isset(LANGS[$_POST['lang'] ?? '']) ? $_POST['lang'] : 'he';
        $ucourse = course_ok($_POST['course'] ?? '');
        if ($me && $id === (int)$me['id'] && ($role !== 'admin' || !$active)) $err = 'אי אפשר להוריד את ההרשאות או לחסום את המשתמש שאיתו נכנסת.';
        else {
            q("UPDATE users SET role = ?, lang = ?, course = ?, is_active = ? WHERE id = ?", [$role, $ulang, $ucourse, $active, $id]);
            if ($role !== 'student') q("DELETE FROM teacher_students WHERE student_id = ?", [$id]);
            if ($role !== 'teacher') q("DELETE FROM teacher_students WHERE teacher_id = ?", [$id]);
            $msg = 'המשתמש עודכן.';
        }
        $pw = (string)($_POST['new_password'] ?? '');
        if (!$err && $pw !== '') {
            if (pw_short($pw)) $err = 'הסיסמה החדשה צריכה להיות באורך ' . PASSWORD_MIN . ' תווים לפחות. שאר השינויים נשמרו.';
            else { q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($pw, PASSWORD_DEFAULT), $id]); $msg = 'המשתמש עודכן והסיסמה הוחלפה.'; }
        }
    } elseif ($a === 'delete') {
        $id = (int)$_POST['id'];
        if ($me && $id === (int)$me['id']) $err = 'אי אפשר למחוק את המשתמש שאיתו נכנסת.';
        elseif (empty($_POST['confirm'])) $err = 'כדי למחוק, סמן את תיבת האישור שליד כפתור המחיקה.';
        else { q("DELETE FROM users WHERE id = ?", [$id]); $msg = 'המשתמש נמחק, יחד עם השיוכים ויומן הכניסות שלו.'; }
    } elseif ($a === 'link') {
        $t = q("SELECT id FROM users WHERE id = ? AND role = 'teacher'", [(int)$_POST['teacher']], true);
        $s = q("SELECT id FROM users WHERE id = ? AND role = 'student'", [(int)$_POST['student']], true);
        if (!$t || !$s) $err = 'צריך לבחור מורה ותלמיד.';
        else { q("INSERT IGNORE INTO teacher_students (teacher_id, student_id) VALUES (?, ?)", [$t['id'], $s['id']]); $msg = 'התלמיד שויך למורה.'; }
    } elseif ($a === 'unlink') {
        q("DELETE FROM teacher_students WHERE teacher_id = ? AND student_id = ?", [(int)$_POST['teacher'], (int)$_POST['student']]);
        $msg = 'השיוך הוסר.';
    }
}

$filter = in_array($_GET['role'] ?? '', ROLES, true) ? $_GET['role'] : '';
$users = q("SELECT u.*, (SELECT COUNT(*) FROM question_visits v WHERE v.user_id = u.id) AS nvisits,
                   (SELECT COUNT(DISTINCT v.question_id) FROM question_visits v WHERE v.user_id = u.id) AS nq
              FROM users u" . ($filter ? " WHERE u.role = " . db()->quote($filter) : '') . " ORDER BY FIELD(u.role, 'admin', 'teacher', 'student'), u.full_name");
$count = [];
foreach (q("SELECT role, COUNT(*) AS n FROM users GROUP BY role") as $r) $count[$r['role']] = (int)$r['n'];
$teachers = q("SELECT id, full_name, username FROM users WHERE role = 'teacher' ORDER BY full_name");
$students = q("SELECT id, full_name, username FROM users WHERE role = 'student' ORDER BY full_name");
$links = q("SELECT ts.teacher_id, ts.student_id, t.full_name AS teacher, s.full_name AS student, s.username AS s_user, ts.created_at
              FROM teacher_students ts JOIN users t ON t.id = ts.teacher_id JOIN users s ON s.id = ts.student_id
             ORDER BY t.full_name, s.full_name");

admin_header('משתמשים'); ?>
<section class="hero"><h1>משתמשים</h1>
<p>תלמידים נרשמים בעצמם בדף <a href="../register.php">ההרשמה</a>. כאן פותחים חשבונות של מורים ומנהלי מערכת, משנים תפקיד, חוסמים, מאפסים סיסמה ומשייכים תלמידים למורים. מנהל מערכת שנכנס לאתר יכול להיכנס גם לאזור הפיתוח מכל מחשב.</p></section>

<?php if ($err): ?><p class="msg err"><?= h($err) ?></p><?php endif; ?>
<?php if ($msg): ?><p class="msg ok"><?= h($msg) ?></p><?php endif; ?>
<?php if (empty($count['admin'])): ?><div class="panel warn">עוד אין מנהל מערכת. כדאי לפתוח אחד כאן, כדי שאפשר יהיה לנהל את המשתמשים גם מהשרת החיצוני.</div><?php endif; ?>

<div class="stats">
<?php foreach ($ROLE_HE as $r => $label): ?>
  <a class="stat" href="?role=<?= $r ?>"><div class="stat-n"><?= (int)($count[$r] ?? 0) ?></div><div class="stat-l"><?= h($label === 'מנהל מערכת' ? 'מנהלי מערכת' : ($label === 'מורה' ? 'מורים' : 'תלמידים')) ?></div></a>
<?php endforeach; ?>
</div>

<h2>משתמש חדש</h2>
<form method="post" class="panel form tform">
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <div class="fgrid">
    <label>שם מלא<input type="text" name="full_name" required></label>
    <label>שם משתמש<input type="text" name="username" dir="ltr" required pattern="[A-Za-z0-9_.\-]{3,40}"></label>
    <label>אימייל (רשות)<input type="text" name="email" dir="ltr"></label>
    <label>תפקיד<select name="role"><?php foreach ($ROLE_HE as $r => $l): ?><option value="<?= $r ?>"<?= $r === 'teacher' ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
    <label>שפה<select name="lang"><?php foreach ($LANG_HE as $c => $l): ?><option value="<?= $c ?>"><?= h($l) ?></option><?php endforeach; ?></select></label>
    <label>קורס<select name="course"><option value="">—</option><?php foreach (course_list() as $c => $l): ?><option value="<?= h($c) ?>"><?= h($l) ?></option><?php endforeach; ?></select></label>
    <label>סיסמה<?= PASSWORD_MIN > 1 ? ' (לפחות ' . PASSWORD_MIN . ' תווים)' : '' ?><input type="text" name="password" dir="ltr" required minlength="<?= PASSWORD_MIN ?>" autocomplete="off" spellcheck="false" readonly onfocus="this.removeAttribute('readonly')" class="pwplain"></label>
  </div>
  <button class="btn">יצירה</button>
</form>

<h2>כל המשתמשים<?= $filter ? ' · ' . h($ROLE_HE[$filter]) . ' <a class="small" href="users.php">(הצג הכול)</a>' : '' ?></h2>
<div class="panel"><div class="dbwrap"><table class="ftable utbl">
  <thead><tr><th>שם</th><th>שם משתמש</th><th>תפקיד</th><th>שפה</th><th>קורס</th><th>פעיל</th><th>סיסמה חדשה</th><th></th><th>שאלות / כניסות</th><th>נרשם</th><th>כניסה אחרונה</th><th>מחיקה</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): $fid = 'u' . (int)$u['id']; ?>
    <tr<?= $u['is_active'] ? '' : ' class="blocked"' ?>>
      <td><?= h($u['full_name']) ?><?php if ($u['email']): ?><br><small dir="ltr"><?= h($u['email']) ?></small><?php endif; ?></td>
      <td dir="ltr"><?= h($u['username']) ?></td>
      <td><select name="role" form="<?= $fid ?>"><?php foreach ($ROLE_HE as $r => $l): ?><option value="<?= $r ?>"<?= $r === $u['role'] ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></td>
      <td><select name="lang" form="<?= $fid ?>"><?php foreach ($LANG_HE as $c => $l): ?><option value="<?= $c ?>"<?= $c === ($u['lang'] ?? 'he') ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></td>
      <td><select name="course" form="<?= $fid ?>"><option value="">—</option><?php foreach (course_list() as $c => $l): ?><option value="<?= h($c) ?>"<?= $c === ($u['course'] ?? '') ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></td>
      <td><input type="checkbox" name="is_active" value="1" form="<?= $fid ?>"<?= $u['is_active'] ? ' checked' : '' ?>></td>
      <td><input type="text" name="new_password" form="<?= $fid ?>" dir="ltr" placeholder="ללא שינוי" autocomplete="off" spellcheck="false" readonly onfocus="this.removeAttribute('readonly')" class="pwin pwplain"></td>
      <td><form method="post" id="<?= $fid ?>"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn small">שמירה</button></form></td>
      <td class="num"><?php if ($u['role'] === 'student'): ?><a href="../teacher.php?student=<?= (int)$u['id'] ?>"><?= (int)$u['nq'] ?> / <?= (int)$u['nvisits'] ?></a><?php else: ?>—<?php endif; ?></td>
      <td class="dt"><?= h(fmt_dt($u['created_at'], false)) ?></td>
      <td class="dt"><?= h(fmt_dt($u['last_login_at'])) ?></td>
      <td><form method="post" class="delf"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><label title="סימון לאישור המחיקה"><input type="checkbox" name="confirm" value="1" required></label><button class="linkbtn">מחיקה</button></form></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$users): ?><tr><td colspan="10">אין משתמשים.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<h2>שיוך תלמידים למורים</h2>
<form method="post" class="panel form tform">
  <?= csrf_field() ?><input type="hidden" name="action" value="link">
  <label>מורה<select name="teacher" required><option value="">—</option><?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= h($t['full_name'] . ' (' . $t['username'] . ')') ?></option><?php endforeach; ?></select></label>
  <label>תלמיד<select name="student" required><option value="">—</option><?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['full_name'] . ' (' . $s['username'] . ')') ?></option><?php endforeach; ?></select></label>
  <button class="btn">שיוך</button>
  <p class="hint">מורה יכול גם להוסיף תלמידים בעצמו, בדף <a href="../teacher.php">התלמידים שלי</a>, לפי שם המשתמש של התלמיד.</p>
</form>
<?php if ($links): ?>
<div class="panel"><table class="ftable">
  <thead><tr><th>מורה</th><th>תלמיד</th><th>שויך ב</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($links as $l): ?>
    <tr><td><?= h($l['teacher']) ?></td><td><?= h($l['student']) ?> <small dir="ltr">(<?= h($l['s_user']) ?>)</small></td><td class="dt"><?= h(fmt_dt($l['created_at'], false)) ?></td>
      <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unlink"><input type="hidden" name="teacher" value="<?= (int)$l['teacher_id'] ?>"><input type="hidden" name="student" value="<?= (int)$l['student_id'] ?>"><button class="linkbtn">הסרה</button></form></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php admin_footer();
