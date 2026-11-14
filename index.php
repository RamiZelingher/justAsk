<?php
require __DIR__ . '/inc/layout.php';

$curricula = q("SELECT c.*, cn.name, cn.description FROM curricula c
                LEFT JOIN curriculum_names cn ON cn.curriculum_id = c.id AND cn.lang = ?
                ORDER BY c.sort_order", [lang()]);
// the Hebrew page shows only the Hebrew curricula (no French track)
if (lang() === 'he') {
    $curricula = array_values(array_filter($curricula, function ($c) { return $c['content_lang'] !== 'fr'; }));
}
$codes = array_column($curricula, 'code');
$active = in_array($_GET['c'] ?? '', $codes, true) ? $_GET['c'] : ($codes[0] ?? '');
$cur = null;
foreach ($curricula as $c) { if ($c['code'] === $active) $cur = $c; }

// all subjects of the track, in study order, with the number of published questions on each
$subjects = $cur ? q("SELECT s.id, s.parent_id, s.slug, s.study_order, n.name, n2.name AS name2,
                             (SELECT COUNT(*) FROM question_subjects qs JOIN questions qq ON qq.id = qs.question_id
                               WHERE qs.subject_id = s.id AND qq.status = 'published'" . q_available('qq') . ") AS nq
                        FROM subjects s" . name_joins('s') . "
                       WHERE s.curriculum_id = ? ORDER BY s.study_order, s.id", [$cur['id']]) : [];
$top = []; $kids = [];
foreach ($subjects as $s) {
    if ($s['parent_id']) $kids[$s['parent_id']][] = $s; else $top[] = $s;
}
$two = false;   // one language per page: no second-language column

// theory background: items linked to each subject; a main topic counts its own items plus its subtopics'
$th = [];
if ($cur) {
    foreach (q("SELECT st.subject_id, st.theory_id FROM subject_theory st JOIN subjects s ON s.id = st.subject_id
                  JOIN theory_items t ON t.id = st.theory_id
                 WHERE s.curriculum_id = ?" . t_available('t'), [$cur['id']]) as $r) {
        $th[$r['subject_id']][$r['theory_id']] = true;
    }
}
function theory_cell($slug, $n) {
    return '<span class="c-num c-th">' . ($n ? '<a class="thlink numlink" href="theory_list.php?s=' . h($slug) . '" title="' . h(t('col_theory')) . '">' . $n . '</a>' : '–') . '</span>';
}

page_header(t('subjects')); ?>
<section class="hero compact">
  <h1><?= h(t('hero_title')) ?></h1>
</section>
<?php search_form(); ?>
<form class="qfind" action="question.php" method="get" role="search">
  <label for="qfind-id"><?= h(t('find_q')) ?></label>
  <input id="qfind-id" name="id" type="number" min="1" inputmode="numeric" required placeholder="<?= h(t('q_id')) ?>" aria-label="<?= h(t('q_id')) ?>">
  <select name="lang" aria-label="<?= h(t('q_lang')) ?>">
    <?php foreach (LANGS as $code => $info): ?><option value="<?= h($code) ?>"<?= $code === lang() ? ' selected' : '' ?>><?= h($info[0]) ?></option><?php endforeach; ?>
  </select>
  <button type="submit"><?= h(t('go')) ?></button>
</form>

<nav class="tracks" role="tablist">
<?php foreach ($curricula as $c): ?>
  <a role="tab" href="?c=<?= h($c['code']) ?>"<?= $c['code'] === $active ? ' class="on" aria-selected="true"' : '' ?>><?= h($c['name']) ?></a>
<?php endforeach; ?>
</nav>

<?php if ($cur): ?>
<div class="ttable<?= $two ? ' two' : '' ?>">
  <div class="trow thead">
    <span class="c-no">#</span>
    <span class="c-name"><?= h(t('col_subject')) ?></span>
    <?php if ($two): ?><span class="c-alt" lang="en" dir="ltr">Subject</span><?php endif; ?>
    <span class="c-num"><?= h(t('subtopics')) ?></span>
    <span class="c-num"><?= h(t('questions_word')) ?></span>
    <span class="c-num"><?= h(t('col_theory')) ?></span>
  </div>
<?php foreach ($top as $i => $s):
    $children = $kids[$s['id']] ?? [];
    $nq = (int)$s['nq'] + array_sum(array_map(function ($k) { return (int)$k['nq']; }, $children));
    $tset = $th[$s['id']] ?? [];
    foreach ($children as $k) $tset += $th[$k['id']] ?? [];
    $cells = '<span class="c-no">' . ($i + 1) . '</span>'
           . '<span class="c-name">' . h($s['name']) . '</span>'
           . ($two ? '<span class="c-alt" lang="en" dir="ltr">' . h($s['name2']) . '</span>' : '')
           . '<span class="c-num">' . (count($children) ? '<span class="numlink">' . count($children) . '</span>' : '–') . '</span>'
           . '<span class="c-num c-lk">' . ($nq ? '<a class="numlink" href="subject.php?s=' . h($s['slug']) . '">' . $nq . '</a>' : '–') . '</span>'
           . theory_cell($s['slug'], count($tset));
    if (!$children): ?>
  <div class="trow linked"><?= str_replace('<span class="c-name">' . h($s['name']) . '</span>', '<a class="c-name rowlink" href="subject.php?s=' . h($s['slug']) . '">' . h($s['name']) . '</a>', $cells) ?></div>
  <?php else: ?>
  <details class="tgroup">
    <summary class="trow"><?= $cells ?></summary>
    <ol class="subrows">
    <?php foreach ($children as $j => $k): $kq = (int)$k['nq']; ?>
      <li class="trow linked<?= $kq ? '' : ' empty' ?>">
        <span class="c-no"><?= ($i + 1) . '.' . ($j + 1) ?></span>
        <a class="c-name rowlink" href="subject.php?s=<?= h($k['slug']) ?>"><?= h($k['name']) ?></a>
        <span class="c-num"></span>
        <span class="c-num c-lk"><?php if ($kq): ?><a class="numlink" href="subject.php?s=<?= h($k['slug']) ?>" title="<?= h(nq($kq)) ?>"><?= $kq ?></a><?php else: ?><?= h(t('no_questions_yet')) ?><?php endif; ?></span>
        <?= theory_cell($k['slug'], count($th[$k['id']] ?? [])) ?>
        
      </li>
    <?php endforeach; ?>
    </ol>
  </details>
  <?php endif; ?>
<?php endforeach; ?>
</div>
<script>
// size the subject column to its longest name (including closed subtopic lists)
(function () {
  document.querySelectorAll('.ttable').forEach(function (t) {
    var closed = [].filter.call(t.querySelectorAll('details'), function (d) { return !d.open; });
    closed.forEach(function (d) { d.open = true; });
    var m = 0, r = document.createRange();
    t.querySelectorAll('.c-name').forEach(function (e) {
      r.selectNodeContents(e);
      var w = r.getBoundingClientRect().width;
      var cs = getComputedStyle(e);
      w += parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
      if (w > m) m = w;
    });
    closed.forEach(function (d) { d.open = false; });
    if (m) t.style.setProperty('--namew', Math.ceil(m + 8) + 'px');
  });
})();
</script>
<?php if ($cur['content_lang'] !== lang()): ?><p class="table-note"><?= h(t($cur['content_lang'] === 'fr' ? 'content_note_fr' : 'content_note')) ?></p><?php endif; ?>
<?php endif; ?>
<?php page_footer();
