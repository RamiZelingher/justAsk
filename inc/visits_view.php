<?php
// The list of questions a student visited, shown to the student (my.php) and to their teacher (teacher.php).

/** Prints the visits of one student. $view: 'q' = one row per question, 'log' = every visit. $url: the page URL without the view parameter. */
function render_visits($student_id, $view, $url) {
    user_tables();
    $sum = q("SELECT COUNT(*) AS n, COUNT(DISTINCT question_id) AS nq FROM question_visits WHERE user_id = ?", [(int)$student_id], true);
    $sep = strpos($url, '?') === false ? '?' : '&';
    $subject = "(SELECT COALESCE(n.name, nh.name) FROM question_subjects qs
                   LEFT JOIN subject_names n  ON n.subject_id = qs.subject_id AND n.lang = " . db()->quote(lang()) . "
                   LEFT JOIN subject_names nh ON nh.subject_id = qs.subject_id AND nh.lang = 'he'
                  WHERE qs.question_id = a.question_id ORDER BY qs.subject_id LIMIT 1)";
    if ($view === 'log') {
        $rows = q("SELECT a.question_id, a.visited_at, q.question_no, e.title AS exam_title, e.year, e.session, e.units AS exam_units, $subject AS subject
                     FROM question_visits a LEFT JOIN questions q ON q.id = a.question_id LEFT JOIN exams e ON e.id = q.exam_id
                    WHERE a.user_id = ? ORDER BY a.visited_at DESC, a.id DESC LIMIT 1000", [(int)$student_id]);
    } else {
        $rows = q("SELECT a.*, q.question_no, e.title AS exam_title, e.year, e.session, e.units AS exam_units, $subject AS subject
                     FROM (SELECT question_id, COUNT(*) AS n, MIN(visited_at) AS first, MAX(visited_at) AS last
                             FROM question_visits WHERE user_id = ? GROUP BY question_id) a
                     LEFT JOIN questions q ON q.id = a.question_id LEFT JOIN exams e ON e.id = q.exam_id
                    ORDER BY a.last DESC", [(int)$student_id]);
    }
    if (!$rows) { echo '<div class="panel empty">' . h(t('my_none')) . '</div>'; return; } ?>
<div class="vbar">
  <span class="tag"><?= h(t('n_visits_total', (int)$sum['n'], (int)$sum['nq'])) ?></span>
  <nav class="vtabs">
    <a href="<?= h($url) ?>"<?= $view !== 'log' ? ' class="on"' : '' ?>><?= h(t('by_question')) ?></a>
    <a href="<?= h($url . $sep . 'view=log') ?>"<?= $view === 'log' ? ' class="on"' : '' ?>><?= h(t('all_entries')) ?></a>
  </nav>
</div>
<div class="utable-wrap"><table class="utable">
  <thead><tr>
    <th><?= h(t('col_question')) ?></th><th><?= h(t('q_source')) ?></th><th><?= h(t('col_subject')) ?></th>
    <?php if ($view === 'log'): ?><th><?= h(t('col_when')) ?></th>
    <?php else: ?><th><?= h(t('col_visits')) ?></th><th><?= h(t('col_first')) ?></th><th><?= h(t('col_last')) ?></th><?php endif; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $src = $r['exam_title'] ? t('exam_q', exam_label(['exam_title' => $r['exam_title'], 'year' => $r['year'], 'session' => $r['session'], 'units' => $r['exam_units']]), (int)$r['question_no']) : ''; ?>
    <tr>
      <td><a class="qpill" href="question.php?id=<?= (int)$r['question_id'] ?>"><?= (int)$r['question_id'] ?></a></td>
      <td><?= h($src) ?></td>
      <td><?= h($r['subject'] ?? '') ?></td>
      <?php if ($view === 'log'): ?><td class="dt"><?= h(fmt_dt($r['visited_at'])) ?></td>
      <?php else: ?><td class="num"><?= (int)$r['n'] ?></td><td class="dt"><?= h(fmt_dt($r['first'])) ?></td><td class="dt"><?= h(fmt_dt($r['last'])) ?></td><?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php }

/** Requires a logged-in user (optionally with one of the roles); otherwise sends them to the login page. */
function require_login(...$roles) {
    $u = current_user();
    if (!$u) {
        header('Location: login.php?need=1&next=' . rawurlencode(basename($_SERVER['SCRIPT_NAME']) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')));
        exit;
    }
    if ($roles && !in_array($u['role'], $roles, true)) not_found();
    return $u;
}
