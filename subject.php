<?php
require __DIR__ . '/inc/layout.php';
$s = q("SELECT s.*, n.name, n2.name AS name2, c.code AS cur_code, c.content_lang, cn.name AS cur_name
          FROM subjects s" . name_joins('s') . "
          JOIN curricula c ON c.id = s.curriculum_id
          LEFT JOIN curriculum_names cn ON cn.curriculum_id = c.id AND cn.lang = " . db()->quote(lang()) . "
         WHERE s.slug = ?", [$_GET['s'] ?? ''], true);
if (!$s) not_found();
$parent = $s['parent_id'] ? q("SELECT s.slug, n.name FROM subjects s" . name_joins('s') . " WHERE s.id = ?", [$s['parent_id']], true) : null;
$children = q("SELECT s.slug, n.name, n2.name AS name2,
                      (SELECT COUNT(*) FROM question_subjects qs JOIN questions qq ON qq.id = qs.question_id
                        WHERE qs.subject_id = s.id AND qq.status = 'published'" . q_available('qq') . ") AS nq
                 FROM subjects s" . name_joins('s') . " WHERE s.parent_id = ? ORDER BY s.study_order, s.id", [$s['id']]);
$questions = q("SELECT q.*, e.title AS exam_title, e.year, e.session, e.units AS exam_units,
                       (SELECT body_html FROM question_parts WHERE question_id = q.id ORDER BY part_no LIMIT 1) AS body_html
                  FROM questions q JOIN question_subjects qs ON qs.question_id = q.id
                  LEFT JOIN exams e ON e.id = q.exam_id
                 WHERE qs.subject_id = ? AND q.status = 'published'" . q_available('q') . "
                 ORDER BY qs.position, q.id", [$s['id']]);
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
$clang = shown_lang($s['content_lang']);
$cdir = dir_of($clang);

page_header($s['name']); ?>
<div class="crumbs"><a href="index.php?c=<?= h($s['cur_code']) ?>"><?= h($s['cur_name']) ?></a><?php if ($parent): ?> › <a href="subject.php?s=<?= h($parent['slug']) ?>"><?= h($parent['name']) ?></a><?php endif; ?></div>
<h1 class="page"><?= h($s['name']) ?></h1>
<?php if ($s['name2']): ?><div class="alt big" lang="<?= lang2() ?>" dir="<?= dir_of(lang2()) ?>"><?= h($s['name2']) ?></div><?php endif; ?>
<div class="meta">
  <?php if ($s['units']): ?><span class="tag units"><?= h(units_label($s['units'])) ?></span><?php endif; ?>
  <?php if ($questions): ?><span class="tag"><?= h(nq(count($questions))) ?></span><?php endif; ?>
</div>

<?php if ($children): ?>
<section class="chapter wide">
  <div class="chapter-head static"><h2><?= h(t('subtopics')) ?></h2></div>
  <ul class="topics">
  <?php foreach ($children as $k): $has = (int)$k['nq'] > 0; ?>
    <li<?= $has ? ' class="has"' : '' ?>><a href="subject.php?s=<?= h($k['slug']) ?>">
      <span class="t-names"><span class="t-name"><?= h($k['name']) ?></span>
      <?php if ($k['name2']): ?><span class="alt" lang="<?= lang2() ?>" dir="<?= dir_of(lang2()) ?>"><?= h($k['name2']) ?></span><?php endif; ?></span>
      <?php if ($has): ?><span class="count"><?= (int)$k['nq'] ?></span><?php endif; ?>
    </a></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php elseif (!$questions): ?>
<div class="panel empty"><?= h(t('coming_soon')) ?></div>
<?php endif; ?>

<?php if ($questions): ?>
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
