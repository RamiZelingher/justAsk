<?php
require __DIR__ . '/inc/layout.php';
$t = q("SELECT * FROM theory_items WHERE legacy_id = ?", [$_GET['id'] ?? ''], true);
if (!$t) not_found();
if (tlang() && !q("SELECT 1 FROM translations WHERE entity = 'theory' AND entity_id = ? AND field = 'title' AND lang = ?", [$t['id'], tlang()], true)) {
    page_header($t['title']); ?>
<div class="panel empty"><p><?= h(t('not_translated_th')) ?></p><p><a href="<?= h(url_lang('he')) ?>"><?= h(t('read_original')) ?></a></p></div>
<?php page_footer(); exit;
}
$t = tr_rows('theory', [$t], ['title' => 'title', 'body' => 'body_html'])[0];
$clang = shown_lang('he');
$cdir = dir_of($clang);
$used = q("SELECT q.id, q.question_no, e.title AS exam_title, e.year, e.session, e.units AS exam_units
             FROM questions q JOIN question_theory qt ON qt.question_id = q.id
             LEFT JOIN exams e ON e.id = q.exam_id
            WHERE qt.theory_id = ? AND q.status = 'published'" . q_available('q') . " ORDER BY e.year, e.id, q.id", [$t['id']]);
page_header($t['title']); ?>
<div class="crumbs"><a href="index.php"><?= h(t('subjects')) ?></a> › <?= h(t('theory')) ?></div>
<span class="kind <?= h($t['kind']) ?>"><?= h(kind_label($t['kind'])) ?></span>
<h1 class="page" dir="<?= $cdir ?>" lang="<?= $clang ?>"><?= h($t['title']) ?></h1>
<div class="panel rich" dir="<?= $cdir ?>" lang="<?= $clang ?>"><?= content($t['body_html']) ?></div>
<?php if ($used): ?>
<div class="panel"><strong><?= h(t('used_in')) ?></strong>
  <div class="qsrc-wrap"><table class="qsrc">
    <thead><tr><th><?= h(t('q_id')) ?></th><th><?= h(t('q_source')) ?></th></tr></thead>
    <tbody><?php foreach ($used as $u): ?>
      <tr><td><a class="qcode" href="question.php?id=<?= (int)$u['id'] ?>"><?= (int)$u['id'] ?></a></td>
          <td><a href="question.php?id=<?= (int)$u['id'] ?>"><?= h(t('exam_q', exam_label($u), (int)$u['question_no'])) ?></a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>
<p><a href="javascript:history.back()"><?= h(t('back')) ?></a></p>
<?php page_footer();
