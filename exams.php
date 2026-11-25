<?php
// List of the bagrut exams of one level (4 / 5 units): year and exam name; a row opens exam.php with the exam's questions.
require __DIR__ . '/inc/layout.php';

$curricula = q("SELECT c.*, cn.name FROM curricula c
                LEFT JOIN curriculum_names cn ON cn.curriculum_id = c.id AND cn.lang = ?
                WHERE c.units IS NOT NULL ORDER BY c.sort_order", [lang()]);
$codes = array_column($curricula, 'code');
$active = in_array($_GET['c'] ?? '', $codes, true) ? $_GET['c'] : ($codes[0] ?? '');
$cur = null;
foreach ($curricula as $c) { if ($c['code'] === $active) $cur = $c; }

// exams of this level that have questions on this page (on English pages: translated questions only)
$exams = $cur ? q("SELECT e.id, e.title, e.year, e.session, e.units, COUNT(q.id) AS nq
                     FROM exams e JOIN questions q ON q.exam_id = e.id AND q.status = 'published'" . q_available('q') . "
                    WHERE e.units = ?
                    GROUP BY e.id, e.title, e.year, e.session, e.units", [$cur['units']]) : [];
// newest first; within a year the latest sitting first; within a sitting by questionnaire (ד before ה)
$ORDER = ['ינואר' => 1, 'חורף' => 1, 'מאי' => 2, 'יוני' => 3, 'קיץ' => 3, 'קיץ מועד א' => 3, 'יולי' => 4, 'אוגוסט' => 4, 'קיץ מועד ב' => 4, 'קיץ מועד מיוחד' => 5, 'דצמבר' => 6];
usort($exams, function ($a, $b) use ($ORDER) {
    return [(int)$b['year'], $ORDER[$b['session']] ?? 9, (int)$a['id']] <=> [(int)$a['year'], $ORDER[$a['session']] ?? 9, (int)$b['id']];
});

page_header(t('exams_title')); ?>
<div class="crumbs"><a href="index.php?c=<?= h($active) ?>"><?= h(t('subjects')) ?></a></div>
<h1 class="page"><?= h(t('exams_title')) ?></h1>
<p class="lead"><?= h(t('exams_lead')) ?></p>

<nav class="tracks" role="tablist">
<?php foreach ($curricula as $c): ?>
  <a role="tab" href="?c=<?= h($c['code']) ?>"<?= $c['code'] === $active ? ' class="on" aria-selected="true"' : '' ?>><?= h($c['name']) ?></a>
<?php endforeach; ?>
</nav>

<?php if (!$exams): ?>
<div class="panel empty"><?= h(t('no_exams')) ?></div>
<?php else: ?>
<div class="etable" role="table">
  <div class="erow ehead" role="row">
    <span class="e-year" role="columnheader"><?= h(t('col_year')) ?></span>
    <span class="e-name" role="columnheader"><?= h(t('col_exam')) ?></span>
    <span class="e-num" role="columnheader"><?= h(t('questions_word')) ?></span>
  </div>
<?php foreach ($exams as $e): ?>
  <div class="erow" role="row">
    <span class="e-year" role="cell"><?= (int)$e['year'] ?: '' ?></span>
    <a class="e-name rowlink" role="cell" href="exam.php?id=<?= (int)$e['id'] ?>"><?= h(exam_label(['exam_title' => $e['title'], 'year' => $e['year'], 'session' => $e['session'], 'units' => $e['units']])) ?></a>
    <span class="e-num" role="cell"><?= (int)$e['nq'] ?></span>
  </div>
<?php endforeach; ?>
  <div class="erow etotal" role="row">
    <span class="e-year" role="cell"><?= h(t('exams_total')) ?></span>
    <span class="e-name" role="cell"><?= h(sprintf(t('n_exams'), count($exams))) ?></span>
    <span class="e-num" role="cell"><?= array_sum(array_map('intval', array_column($exams, 'nq'))) ?></span>
  </div>
</div>
<?php endif; ?>
<?php page_footer();
