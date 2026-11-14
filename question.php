<?php
require __DIR__ . '/inc/layout.php';

/** URL of a question file in files/, preferring the copy in the page language (q200.pdf -> q200_en.pdf); null when missing. */
function q_file($name) {
    if (!$name || !preg_match('/^[\\w.-]+$/', $name)) return null;
    $dot = strrpos($name, '.');
    $cands = [];
    if (lang() !== 'he' && $dot !== false) $cands[] = substr($name, 0, $dot) . '_' . lang() . substr($name, $dot);
    $cands[] = $name;
    foreach ($cands as $f) {
        if (is_file(__DIR__ . '/files/' . $f)) return 'files/' . rawurlencode($f) . '?v=' . filemtime(__DIR__ . '/files/' . $f);
    }
    return null;
}
$id = (int)($_GET['id'] ?? 0);
$q = q("SELECT q.*, e.title AS exam_title, e.year, e.session, e.units AS exam_units FROM questions q
        LEFT JOIN exams e ON e.id = q.exam_id WHERE q.id = ? AND q.status = 'published'", [$id], true);
if (!$q) not_found();
// on a page whose language has translations, an untranslated question is not shown
$available = !tlang() || q("SELECT 1 FROM question_languages WHERE question_id = ? AND lang = ?", [$id, tlang()], true);
$subjects = q("SELECT s.id, s.slug, s.parent_id, n.name, c.code AS cur_code, c.content_lang, cn.name AS cur_name
                 FROM subjects s" . name_joins('s') . "
                 JOIN question_subjects qs ON qs.subject_id = s.id
                 JOIN curricula c ON c.id = s.curriculum_id
                 LEFT JOIN curriculum_names cn ON cn.curriculum_id = c.id AND cn.lang = " . db()->quote(lang()) . "
                WHERE qs.question_id = ?", [$id]);
$main = $subjects[0] ?? null;
$parent = ($main && $main['parent_id']) ? q("SELECT s.slug, n.name FROM subjects s" . name_joins('s') . " WHERE s.id = ?", [$main['parent_id']], true) : null;
$written = $main['content_lang'] ?? 'he';
$clang = shown_lang($written);
$cdir = dir_of($clang);

$parts = q("SELECT * FROM question_parts WHERE question_id = ? ORDER BY part_no", [$id]);
foreach ($parts as &$p) {
    $p['hints'] = q("SELECT * FROM hints WHERE part_id = ? ORDER BY seq", [$p['id']]);
    $p['steps'] = q("SELECT * FROM solution_steps WHERE part_id = ? ORDER BY seq", [$p['id']]);
}
unset($p);
$parts = tr_rows('part', $parts, ['body' => 'body_html', 'answer' => 'final_answer_html']);
foreach ($parts as &$p) {
    $p['hints'] = tr_rows('hint', $p['hints'], ['body' => 'body_html']);
    $p['steps'] = tr_rows('step', $p['steps'], ['body' => 'body_html', 'math' => 'math_html']);
}
unset($p);
$theory = q("SELECT t.id, t.legacy_id, t.title, t.kind, t.body_html FROM theory_items t JOIN question_theory qt ON qt.theory_id = t.id
             WHERE qt.question_id = ?" . t_available('t') . " ORDER BY t.kind, t.title", [$id]);
$theory = tr_rows('theory', $theory, ['title' => 'title', 'body' => 'body_html']);

// previous / next question within the same subject
$ids = [];
if ($main) {
    $ids = array_map('intval', array_column(q("SELECT q.id FROM questions q JOIN question_subjects qs ON qs.question_id = q.id
                           LEFT JOIN exams e ON e.id = q.exam_id
                           WHERE qs.subject_id = ? AND q.status = 'published'" . q_available('q') . " ORDER BY qs.position, q.id",
                          [$main['id']]), 'id'));
}
$pos = array_search($id, $ids, true);
$prev = ($pos !== false && $pos > 0) ? $ids[$pos - 1] : null;
$next = ($pos !== false && $pos < count($ids) - 1) ? $ids[$pos + 1] : null;
$n = $pos === false ? 1 : $pos + 1;
$total = max(count($ids), 1);
$exam = exam_label(['exam_title' => $q['exam_title'], 'year' => $q['year'], 'session' => $q['session'], 'units' => $q['exam_units']]);

page_header(t('question_x_of_y', $n, $total) . ($main ? ' · ' . $main['name'] : ''));
if (!$available): ?>
<div class="panel empty">
  <p><?= h(t('not_translated')) ?></p>
  <p><a href="<?= h(url_lang($written)) ?>"><?= h(t('read_original')) ?></a></p>
</div>
<?php page_footer(); exit; endif; ?>
<div class="crumbs">
  <?php if ($main): ?><a href="index.php?c=<?= h($main['cur_code']) ?>"><?= h($main['cur_name']) ?></a>
  <?php if ($parent): ?> › <a href="subject.php?s=<?= h($parent['slug']) ?>"><?= h($parent['name']) ?></a><?php endif; ?>
  › <a href="subject.php?s=<?= h($main['slug']) ?>"><?= h($main['name']) ?></a><?php endif; ?>
</div>
<h1 class="page"><?= h(t('question_x_of_y', $n, $total)) ?></h1>
<div class="meta">
  <span class="tag units"><?= h(units_label($q['units'])) ?></span>
  <?php if ($q['exam_title']): ?><span class="tag"><?= h(t('exam_q', $exam, (int)$q['question_no'])) ?></span><?php endif; ?>
  <span class="tag qid"><?= h(t('id_label', (int)$q['id'])) ?></span>
  <?php $pdf = q_file($q['pdf_file'] ?? null); $vid = q_file($q['video_file'] ?? null); ?>
  <?php if ($pdf): ?><a class="tag fbtn pdf" href="<?= h($pdf) ?>" target="_blank" rel="noopener">&#128196; <?= h(t('pdf_btn')) ?></a><?php endif; ?>
  <?php if ($vid): ?><button type="button" class="tag fbtn video" onclick="var v=document.getElementById('qvideo');v.hidden=!v.hidden;if(!v.hidden)v.querySelector('video').play();">&#9654; <?= h(t('video_btn')) ?></button><?php endif; ?>
  <?php if (lang() !== $clang): ?><span class="tag note"><?= h(t($clang === 'fr' ? 'content_note_fr' : 'content_note')) ?></span><?php endif; ?>
  <?php if (tlang()): ?><span class="tag note"><?= h(t('machine_translation')) ?></span><?php endif; ?>
</div>
<?php if (!empty($vid)): ?><div class="qvideo" id="qvideo" hidden><video controls preload="none" src="<?= h($vid) ?>"></video></div><?php endif; ?>

<?php
$hasHints = (bool)$theory;
$hasDirs = (bool)array_filter($parts, function ($p) { return $p['hints']; });
$hasSteps = (bool)array_filter($parts, function ($p) { return $p['steps']; });
$hasFinal = (bool)array_filter($parts, function ($p) { return $p['final_answer_html']; });
$tabs = [
    'question' => [t('tab_question'), true],
    'hints'    => [t('tab_hints'), $hasHints],
    'guide'    => [t('tab_guide'), $hasDirs],
    'solution' => [t('tab_solution'), $hasSteps],
    'answer'   => [t('tab_answer'), $hasFinal],
];
$multi = count($parts) > 1;
$attrs = 'dir="' . $cdir . '" lang="' . $clang . '"';
?>
<div class="qtabs" id="qtabs">
  <div class="qtabbar" role="tablist">
  <?php foreach ($tabs as $key => [$label, $on]): ?>
    <button type="button" role="tab" id="tab-<?= $key ?>" aria-controls="pane-<?= $key ?>" data-tab="<?= $key ?>"
            aria-selected="<?= $key === 'question' ? 'true' : 'false' ?>"<?= $on ? '' : ' disabled' ?>><?= h($label) ?></button>
  <?php endforeach; ?>
  </div>

  <section class="qpane" role="tabpanel" id="pane-question" aria-labelledby="tab-question">
    <?php foreach ($parts as $p): ?>
      <div class="qtext" <?= $attrs ?>><?= content($p['body_html']) ?></div>
    <?php endforeach; ?>
  </section>

  <section class="qpane" role="tabpanel" id="pane-hints" aria-labelledby="tab-hints" hidden>
    <p class="pane-lead"><?= h(t('hints_lead')) ?></p>
    <?php foreach ($theory as $th): ?>
    <details class="theory-hint">
      <summary><span class="kind"><?= h(kind_label($th['kind'])) ?></span> <bdi <?= $attrs ?>><?= h($th['title']) ?></bdi></summary>
      <div class="body rich" <?= $attrs ?>><?= content($th['body_html']) ?></div>
    </details>
    <?php endforeach; ?>
  </section>

  <section class="qpane" role="tabpanel" id="pane-guide" aria-labelledby="tab-guide" hidden>
    <?php foreach ($parts as $pi => $p): if (!$p['hints']) continue; ?>
      <?php if ($multi): ?><h3 class="part-h"><?= h(t('part_n', $pi + 1)) ?></h3><?php endif; ?>
      <ol class="hintlist" <?= $attrs ?>>
        <?php foreach ($p['hints'] as $hn): ?><li class="rich"><?= content($hn['body_html']) ?></li><?php endforeach; ?>
      </ol>
    <?php endforeach; ?>
  </section>

  <section class="qpane" role="tabpanel" id="pane-solution" aria-labelledby="tab-solution" hidden>
    <?php foreach ($parts as $pi => $p): if (!$p['steps']) continue; ?>
      <?php if ($multi): ?><h3 class="part-h"><?= h(t('part_n', $pi + 1)) ?></h3><?php endif; ?>
      <ol class="steps" <?= $attrs ?>>
      <?php foreach ($p['steps'] as $st): ?>
        <li class="rich"><?= content($st['body_html']) ?>
          <?php if ($st['math_html']): ?><div class="stepmath"><?= content(preg_replace('/<math>/', '<math display="block">', $st['math_html'], 1)) ?></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
      </ol>
    <?php endforeach; ?>
  </section>

  <section class="qpane" role="tabpanel" id="pane-answer" aria-labelledby="tab-answer" hidden>
    <?php foreach ($parts as $pi => $p): if (!$p['final_answer_html']) continue; ?>
      <?php if ($multi): ?><h3 class="part-h"><?= h(t('part_n', $pi + 1)) ?></h3><?php endif; ?>
      <div class="final rich" <?= $attrs ?>><?= content($p['final_answer_html']) ?></div>
    <?php endforeach; ?>
  </section>
</div>
<script>
(function () {
  var box = document.getElementById('qtabs');
  var tabs = box.querySelectorAll('[role=tab]');
  function show(key, focus) {
    tabs.forEach(function (b) {
      var on = b.dataset.tab === key;
      b.setAttribute('aria-selected', on ? 'true' : 'false');
      b.tabIndex = on ? 0 : -1;
      document.getElementById('pane-' + b.dataset.tab).hidden = !on;
      if (on && focus) b.focus();
    });
    try { history.replaceState(null, '', key === 'question' ? location.pathname + location.search : '#' + key); } catch (e) {}
  }
  tabs.forEach(function (b) {
    b.addEventListener('click', function () { show(b.dataset.tab); });
    b.addEventListener('keydown', function (e) {
      var list = Array.prototype.filter.call(tabs, function (x) { return !x.disabled; });
      var i = list.indexOf(b), rtl = document.dir === 'rtl';
      var fwd = rtl ? 'ArrowLeft' : 'ArrowRight', back = rtl ? 'ArrowRight' : 'ArrowLeft';
      if (e.key === fwd) show(list[(i + 1) % list.length].dataset.tab, true);
      else if (e.key === back) show(list[(i - 1 + list.length) % list.length].dataset.tab, true);
      else return;
      e.preventDefault();
    });
  });
  var start = location.hash.slice(1);
  var t = document.getElementById('tab-' + start);
  show(t && !t.disabled ? start : 'question');
})();
</script>

<div class="pager">
  <?php if ($prev): ?><a href="question.php?id=<?= $prev ?>"><?= h(t('prev_q')) ?></a><?php else: ?><span></span><?php endif; ?>
  <span class="pos"><?= $n ?> / <?= $total ?></span>
  <?php if ($next): ?><a href="question.php?id=<?= $next ?>"><?= h(t('next_q')) ?></a><?php else: ?><span></span><?php endif; ?>
</div>
<?php page_footer();
