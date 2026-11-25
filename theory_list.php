<?php
// Theory background of one subject: its definitions, theorems and methods (a main topic includes its subtopics')
require __DIR__ . '/inc/layout.php';
$s = q("SELECT s.id, s.slug, s.parent_id, s.curriculum_id, n.name, c.code AS cur_code, c.content_lang, cn.name AS cur_name
          FROM subjects s" . name_joins('s') . "
          JOIN curricula c ON c.id = s.curriculum_id
          LEFT JOIN curriculum_names cn ON cn.curriculum_id = c.id AND cn.lang = " . db()->quote(lang()) . "
         WHERE s.slug = ?", [$_GET['s'] ?? ''], true);
if (!$s || (!FRENCH_ENABLED && $s['content_lang'] === 'fr')) not_found();
$parent = $s['parent_id'] ? q("SELECT s.slug, n.name FROM subjects s" . name_joins('s') . " WHERE s.id = ?", [$s['parent_id']], true) : null;

// the subject itself first, then its subtopics in study order
$ids = [(int)$s['id']];
foreach (q("SELECT id FROM subjects WHERE parent_id = ? ORDER BY study_order, id", [$s['id']]) as $k) $ids[] = (int)$k['id'];
$rank = array_flip($ids);

$rows = q("SELECT t.id, t.legacy_id, t.kind, t.title, t.body_html, st.subject_id, st.position
             FROM subject_theory st JOIN theory_items t ON t.id = st.theory_id
            WHERE st.subject_id IN (" . implode(',', $ids) . ")" . t_available('t'));
$rows = tr_rows('theory', $rows, ['title' => 'title', 'body' => 'body_html']);
usort($rows, function ($a, $b) use ($rank) {
    return [$rank[$a['subject_id']], (int)$a['position']] <=> [$rank[$b['subject_id']], (int)$b['position']];
});
// how many published questions of this curriculum refer to each item
$qcount = [];
foreach (q("SELECT t.legacy_id, COUNT(DISTINCT qt.question_id) AS n
              FROM question_theory qt JOIN theory_items t ON t.id = qt.theory_id
              JOIN questions qq ON qq.id = qt.question_id AND qq.status = 'published'" . q_available('qq') . "
             WHERE EXISTS (SELECT 1 FROM question_subjects qs JOIN subjects s2 ON s2.id = qs.subject_id
                            WHERE qs.question_id = qt.question_id AND s2.curriculum_id = ?)
             GROUP BY t.legacy_id", [$s['curriculum_id']]) as $r) {
    $qcount[$r['legacy_id']] = (int)$r['n'];
}
$groups = ['definition' => [], 'theorem' => [], 'method' => [], 'other' => []];
$seen = [];
foreach ($rows as $r) {
    if (isset($seen[$r['legacy_id']])) continue;
    $seen[$r['legacy_id']] = true;
    $groups[$r['kind']][] = $r;
}
$clang = shown_lang($s['content_lang']);
$cdir = dir_of($clang);
$attrs = 'dir="' . $cdir . '" lang="' . h($clang) . '"';

page_header(t('theory_of', $s['name'])); ?>
<div class="crumbs">
  <a href="index.php?c=<?= h($s['cur_code']) ?>"><?= h($s['cur_name']) ?></a>
  <?php if ($parent): ?> › <a href="subject.php?s=<?= h($parent['slug']) ?>"><?= h($parent['name']) ?></a><?php endif; ?>
  › <a href="subject.php?s=<?= h($s['slug']) ?>"><?= h($s['name']) ?></a>
</div>
<h1 class="page"><?= h(t('theory_of', $s['name'])) ?></h1>
<?php if (!$seen): ?>
  <p class="lead"><?= h(t('theory_none')) ?></p>
<?php else: ?>
  <p class="lead"><?= h(t('theory_pick')) ?></p>
  <div class="tgroup-stack">
  <?php foreach ($groups as $kind => $items): if (!$items) continue; ?>
  <details class="tgroup-box" id="g-<?= $kind ?>">
    <summary><span class="tg-name"><?= h(t('grp_' . $kind)) ?></span> <span class="gcount"><?= count($items) ?></span></summary>
    <div class="tgroup-items">
    <?php foreach ($items as $it): ?>
    <?php $nq = $qcount[$it['legacy_id']] ?? 0; ?>
    <div class="th-row">
      <details class="theory-hint">
        <summary><bdi <?= $attrs ?>><?= h($it['title']) ?></bdi></summary>
        <div class="body rich" <?= $attrs ?>><?= content($it['body_html']) ?>
          <p class="open-page"><a href="theory.php?id=<?= h($it['legacy_id']) ?>"><?= h(t('open_page')) ?></a></p>
        </div>
      </details>
      <?php if ($nq): ?>
      <a class="qbtn" href="theory_questions.php?id=<?= h($it['legacy_id']) ?>&amp;c=<?= h($s['cur_code']) ?>" title="<?= h(t('questions_using', $it['title'])) ?>"><?= h(nq($nq)) ?></a>
      <?php else: ?>
      <span class="qbtn none"><?= h(t('no_questions_yet')) ?></span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
  </details>
  <?php endforeach; ?>
  </div>
<script>
// one list open at a time; remember the open list in the address (#g-theorem)
(function () {
  var boxes = document.querySelectorAll('details.tgroup-box');
  boxes.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (!d.open) return;
      boxes.forEach(function (o) { if (o !== d) o.open = false; });
      try { history.replaceState(null, '', '#' + d.id); } catch (e) {}
    });
  });
  var start = location.hash && document.getElementById(location.hash.slice(1));
  if (start && start.classList.contains('tgroup-box')) start.open = true;
})();
</script>
<?php endif; ?>
<?php page_footer();
