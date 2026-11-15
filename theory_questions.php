<?php
// The questions that refer to one theory item (definition / theorem / method), within one curriculum
require __DIR__ . '/inc/layout.php';
$t = q("SELECT * FROM theory_items WHERE legacy_id = ?", [$_GET['id'] ?? ''], true);
if (!$t) not_found();
$t = tr_rows('theory', [$t], ['title' => 'title'])[0];
$cur = q("SELECT c.*, cn.name FROM curricula c
            LEFT JOIN curriculum_names cn ON cn.curriculum_id = c.id AND cn.lang = " . db()->quote(lang()) . "
           WHERE c.code = ?", [$_GET['c'] ?? ''], true);

$sql = "SELECT q.*, e.title AS exam_title, e.year, e.session, e.units AS exam_units,
               (SELECT body_html FROM question_parts WHERE question_id = q.id ORDER BY part_no LIMIT 1) AS body_html
          FROM questions q JOIN question_theory qt ON qt.question_id = q.id
          LEFT JOIN exams e ON e.id = q.exam_id
         WHERE qt.theory_id = ? AND q.status = 'published'" . q_available('q');
$args = [$t['id']];
if ($cur) {
    $sql .= " AND EXISTS (SELECT 1 FROM question_subjects qs JOIN subjects s ON s.id = qs.subject_id
                           WHERE qs.question_id = q.id AND s.curriculum_id = ?)";
    $args[] = $cur['id'];
}
$sql .= " ORDER BY e.year, e.id, q.question_no, q.id";   // exams were stored in date order
$questions = q($sql, $args);
if (tlang() && $questions) {   // first part of each question, translated
    $first = [];
    foreach (q("SELECT id, question_id FROM question_parts WHERE question_id IN (" . implode(',', array_map('intval', array_column($questions, 'id'))) . ") ORDER BY part_no DESC") as $r) {
        $first[(int)$r['question_id']] = (int)$r['id'];
    }
    $m = tr_map('part', array_values($first), 'body');
    foreach ($questions as &$qq) { $pid = $first[(int)$qq['id']] ?? 0; if (isset($m[$pid])) $qq['body_html'] = $m[$pid]; }
    unset($qq);
}
$vis = $questions ? visits_of(array_map('intval', array_column($questions, 'id'))) : [];   // questions the logged-in student visited
$clang = shown_lang($cur['content_lang'] ?? 'he');
$cdir = dir_of($clang);

page_header(t('questions_using', $t['title'])); ?>
<div class="crumbs">
  <?php if ($cur): ?><a href="index.php?c=<?= h($cur['code']) ?>"><?= h($cur['name']) ?></a> › <?php endif; ?>
  <a href="javascript:history.back()"><?= h(t('col_theory')) ?></a>
</div>
<span class="kind <?= h($t['kind']) ?>"><?= h(kind_label($t['kind'])) ?></span>
<h1 class="page"><bdi dir="<?= $cdir ?>" lang="<?= h($clang) ?>"><?= h($t['title']) ?></bdi></h1>
<div class="meta">
  <span class="tag"><?= h(nq(count($questions))) ?></span>
  <a class="tag" href="theory.php?id=<?= h($t['legacy_id']) ?>"><?= h(t('open_page')) ?></a>
</div>

<?php if (!$questions): ?>
<div class="panel empty"><?= h(t('coming_soon')) ?></div>
<?php else: ?>
<ol class="qlist">
<?php foreach ($questions as $i => $q): ?>
  <?php $v = $vis[(int)$q['id']] ?? null; ?>
  <li<?= $v ? ' class="visited"' : '' ?>><a class="card" href="question.php?id=<?= (int)$q['id'] ?>">
    <span class="qnum"><?= $i + 1 ?></span>
    <?php if ($v): ?><span class="vmark" title="<?= h(t('visited_title', (int)$v['n'], fmt_dt($v['last']))) ?>">&#10003; <?= h(t('visited')) ?></span><?php endif; ?>
    <div>
      <div class="kicker"><?= h(t('exam_q', exam_label(['exam_title' => $q['exam_title'], 'year' => $q['year'], 'session' => $q['session'], 'units' => $q['exam_units']]), (int)$q['question_no'])) ?></div>
      <div class="preview" dir="<?= $cdir ?>" lang="<?= h($clang) ?>"><?= content($q['body_html']) ?></div>
    </div>
  </a></li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
<?php page_footer();
