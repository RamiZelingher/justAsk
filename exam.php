<?php
// The questions of one bagrut exam, in the exam's order (opened from exams.php).
require __DIR__ . '/inc/layout.php';
$e = q("SELECT * FROM exams WHERE id = ?", [(int)($_GET['id'] ?? 0)], true);
if (!$e) not_found();
$cur = q("SELECT c.code, c.content_lang FROM curricula c WHERE c.units = ? ORDER BY c.sort_order LIMIT 1", [$e['units']], true);
$questions = q("SELECT q.*, (SELECT body_html FROM question_parts WHERE question_id = q.id ORDER BY part_no LIMIT 1) AS body_html
                  FROM questions q
                 WHERE q.exam_id = ? AND q.status = 'published'" . q_available('q') . "
                 ORDER BY q.question_no, q.id", [$e['id']]);
// first part of each question, translated on pages that use translations
if (tlang() && $questions) {
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
$label = exam_label(['exam_title' => $e['title'], 'year' => $e['year'], 'session' => $e['session'], 'units' => $e['units']]);

page_header($label); ?>
<div class="crumbs"><a href="exams.php?c=<?= h($cur['code'] ?? '') ?>"><?= h(t('exams_title')) ?></a></div>
<h1 class="page"><?= h($label) ?></h1>
<div class="meta">
  <?php if ($e['units']): ?><span class="tag units"><?= h(units_label($e['units'])) ?></span><?php endif; ?>
  <?php if ($questions): ?><span class="tag"><?= h(nq(count($questions))) ?></span><?php endif; ?>
</div>

<?php if (!$questions): ?>
<div class="panel empty"><?= h(t('coming_soon')) ?></div>
<?php else: ?>
<ol class="qlist">
<?php foreach ($questions as $q): ?>
  <?php $v = $vis[(int)$q['id']] ?? null; ?>
  <li<?= $v ? ' class="visited"' : '' ?>><a class="card" href="question.php?id=<?= (int)$q['id'] ?>">
    <span class="qnum"><?= (int)$q['question_no'] ?></span>
    <?php if ($v): ?><span class="vmark" title="<?= h(t('visited_title', (int)$v['n'], fmt_dt($v['last']))) ?>">&#10003; <?= h(t('visited')) ?></span><?php endif; ?>
    <div>
      <div class="kicker"><?= h(t('exam_q_no', (int)$q['question_no'])) ?></div>
      <div class="preview" dir="<?= $cdir ?>" lang="<?= h($clang) ?>"><?= content($q['body_html']) ?></div>
    </div>
  </a></li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
<?php page_footer();
