<?php
// Topic search (typed or spoken): subjects and theory items whose name contains the words.
require __DIR__ . '/inc/layout.php';

$query = trim((string)($_GET['q'] ?? ''));

/** Lower-case, no niqqud / punctuation, single spaces. */
function norm_text($s) {
    $s = mb_strtolower(strip_tags((string)$s), 'UTF-8');
    $s = preg_replace('/[\x{0591}-\x{05C7}]/u', '', $s);            // Hebrew vowel points
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/** Forms of a search word: the word itself, and in Hebrew also without a one-letter prefix (ה, ו, ב, ל, מ, ש, כ). */
function word_forms($w) {
    $forms = [$w];
    if (mb_strlen($w, 'UTF-8') > 3 && preg_match('/^[הובלמשכ]/u', $w)) $forms[] = mb_substr($w, 1, null, 'UTF-8');
    if (mb_strlen($w, 'UTF-8') > 4 && preg_match('/^(וה|שה|מה|בה|לה|כש)/u', $w)) $forms[] = mb_substr($w, 2, null, 'UTF-8');
    // simple plural / singular in English and French
    if (preg_match('/^[a-z]{4,}s$/u', $w)) $forms[] = mb_substr($w, 0, -1, 'UTF-8');
    return array_unique($forms);
}

/** Score of a text for the words: 0 = a word is missing; more when the words start the text or match whole words. */
function match_score($text, array $words) {
    $t = ' ' . norm_text($text) . ' ';
    if (trim($t) === '') return 0;
    $score = 0;
    foreach ($words as $w) {
        $best = 0;
        foreach (word_forms($w) as $f) {
            $pos = mb_strpos($t, $f, 0, 'UTF-8');
            if ($pos === false) continue;
            $s = 10;
            if (mb_strpos($t, ' ' . $f . ' ', 0, 'UTF-8') !== false) $s += 6;      // whole word
            elseif (mb_strpos($t, ' ' . $f, 0, 'UTF-8') !== false) $s += 3;       // start of a word
            if ($pos <= 1) $s += 4;                                                // start of the name
            $best = max($best, $s);
        }
        if (!$best) return 0;
        $score += $best;
    }
    return $score;
}

$words = $query === '' ? [] : array_values(array_filter(explode(' ', norm_text($query)), function ($w) { return mb_strlen($w, 'UTF-8') >= 2; }));
$subjects = $theory = [];
if ($words) {
    $langs = array_unique([lang(), 'he']);
    $in = implode(',', array_map(function ($l) { return db()->quote($l); }, $langs));
    // subjects: names in the page language and in Hebrew; the Hebrew page leaves out the French track
    $rows = q("SELECT s.id, s.slug, s.parent_id, s.curriculum_id, c.content_lang, sn.lang, sn.name
                 FROM subjects s JOIN curricula c ON c.id = s.curriculum_id
                 JOIN subject_names sn ON sn.subject_id = s.id AND sn.lang IN ($in)" .
              (lang() === 'he' ? " WHERE c.content_lang <> 'fr'" : ''));
    foreach ($rows as $r) {
        $sc = match_score($r['name'], $words);
        if ($sc && (!isset($subjects[$r['id']]) || $sc > $subjects[$r['id']]['score'])) $subjects[$r['id']] = ['score' => $sc] + $r;
    }
    if ($subjects) {
        $ids = implode(',', array_map('intval', array_keys($subjects)));
        // name in the page language, parent name, track name, number of questions (with the subtopics')
        $info = [];
        foreach (q("SELECT s.id, n.name, pn.name AS parent_name, cn.name AS cur_name
                      FROM subjects s
                      LEFT JOIN subject_names n ON n.subject_id = s.id AND n.lang = " . db()->quote(lang()) . "
                      LEFT JOIN subject_names pn ON pn.subject_id = s.parent_id AND pn.lang = " . db()->quote(lang()) . "
                      LEFT JOIN curriculum_names cn ON cn.curriculum_id = s.curriculum_id AND cn.lang = " . db()->quote(lang()) . "
                     WHERE s.id IN ($ids)") as $r) $info[$r['id']] = $r;
        $nq = [];
        foreach (q("SELECT COALESCE(s.parent_id, s.id) AS top, s.id, COUNT(*) AS n FROM question_subjects qs
                      JOIN questions qq ON qq.id = qs.question_id JOIN subjects s ON s.id = qs.subject_id
                     WHERE qq.status = 'published'" . q_available('qq') . " AND (s.id IN ($ids) OR s.parent_id IN ($ids))
                     GROUP BY s.id") as $r) {
            if (isset($subjects[$r['id']])) $nq[$r['id']] = ($nq[$r['id']] ?? 0) + $r['n'];
            if ($r['top'] != $r['id'] && isset($subjects[$r['top']])) $nq[$r['top']] = ($nq[$r['top']] ?? 0) + $r['n'];
        }
        foreach ($subjects as $id => &$s) {
            $s['show'] = $info[$id]['name'] ?: $s['name'];
            $s['parent_name'] = $info[$id]['parent_name'] ?? null;
            $s['cur_name'] = $info[$id]['cur_name'] ?? '';
            $s['nq'] = $nq[$id] ?? 0;
        }
        unset($s);
        uasort($subjects, function ($a, $b) { return [$b['score'], $b['nq']] <=> [$a['score'], $a['nq']]; });
    }
    // theory items: Hebrew title, and the translated title on pages that use translations
    $tr = [];
    if (tlang()) foreach (q("SELECT entity_id, html FROM translations WHERE entity = 'theory' AND field = 'title' AND lang = ?", [tlang()]) as $r) $tr[$r['entity_id']] = strip_tags($r['html']);
    foreach (q("SELECT t.id, t.legacy_id, t.kind, t.title FROM theory_items t WHERE 1" . t_available('t')) as $r) {
        $show = $tr[$r['id']] ?? $r['title'];
        $sc = max(match_score($r['title'], $words), isset($tr[$r['id']]) ? match_score($tr[$r['id']], $words) : 0);
        if ($sc) $theory[] = ['score' => $sc, 'show' => $show] + $r;
    }
    usort($theory, function ($a, $b) { return [$b['score'], $a['show']] <=> [$a['score'], $b['show']]; });
}
$subjects = array_slice($subjects, 0, 40, true);
$theory = array_slice($theory, 0, 40);

page_header($query === '' ? t('search_title') : t('search_title') . ': ' . $query); ?>
<div class="crumbs"><a href="index.php"><?= h(t('subjects')) ?></a> › <?= h(t('search_title')) ?></div>
<h1 class="page"><?= h(t('search_title')) ?></h1>
<?php search_form($query); ?>

<?php if ($query !== ''): ?>
  <?php if (!$subjects && !$theory): ?>
    <div class="panel empty"><p><?= h(t('search_none', $query)) ?></p></div>
  <?php endif; ?>
  <?php if ($subjects): ?>
  <section class="panel sres">
    <h2><?= h(t('search_subjects', count($subjects))) ?></h2>
    <ul class="slist">
    <?php foreach ($subjects as $s): ?>
      <li><a href="subject.php?s=<?= h($s['slug']) ?>"><span class="sname"><?= h($s['show']) ?></span></a>
        <?php if ($s['parent_name']): ?><span class="spath"><?= h($s['parent_name']) ?></span><?php endif; ?>
        <span class="tag"><?= h($s['cur_name']) ?></span>
        <span class="tag snq"><?= h(t('search_nq', (int)$s['nq'])) ?></span></li>
    <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
  <?php if ($theory): ?>
  <section class="panel sres">
    <h2><?= h(t('search_theory', count($theory))) ?></h2>
    <ul class="slist">
    <?php foreach ($theory as $r): ?>
      <li><a href="theory.php?id=<?= h($r['legacy_id']) ?>"><span class="sname"><?= h($r['show']) ?></span></a>
        <span class="kind <?= h($r['kind']) ?>"><?= h(kind_label($r['kind'])) ?></span></li>
    <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
<?php endif; ?>
<?php page_footer();
