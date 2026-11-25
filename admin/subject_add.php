<?php
require __DIR__ . '/_admin.php';
admin_guard();

$LANG_FIELDS = array_intersect_key(['he' => ['עברית', 'rtl'], 'en' => ['English', 'ltr'], 'fr' => ['Français', 'ltr']], LANGS);   // no French field while French is switched off

$curricula = q("SELECT c.*, cn.name FROM curricula c
                LEFT JOIN curriculum_names cn ON cn.curriculum_id = c.id AND cn.lang = 'he' WHERE 1=1" . fr_off_sql('c') . " ORDER BY c.sort_order");
$curById = [];
foreach ($curricula as $c) $curById[(int)$c['id']] = $c;

// every subject with its names, for the parent list, the "position" list and the duplicate check
$all = q("SELECT s.id, s.curriculum_id, s.parent_id, s.study_order, s.slug,
                 he.name AS he, en.name AS en, fr.name AS fr
            FROM subjects s
            LEFT JOIN subject_names he ON he.subject_id = s.id AND he.lang = 'he'
            LEFT JOIN subject_names en ON en.subject_id = s.id AND en.lang = 'en'
            LEFT JOIN subject_names fr ON fr.subject_id = s.id AND fr.lang = 'fr'
           ORDER BY s.study_order, s.id");
$byId = [];
foreach ($all as $s) $byId[(int)$s['id']] = $s;

function slugify($t) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-'); }

$errors = [];
$v = [
    'curriculum_id' => (int)($_POST['curriculum_id'] ?? ($_GET['cur'] ?? ($curricula[0]['id'] ?? 0))),
    'parent_id'     => (int)($_POST['parent_id'] ?? ($_GET['parent'] ?? 0)),
    'before_id'     => (int)($_POST['before_id'] ?? 0),
];
if (!$_POST && $v['parent_id'] && isset($byId[$v['parent_id']])) $v['curriculum_id'] = (int)$byId[$v['parent_id']]['curriculum_id'];
foreach ($LANG_FIELDS as $l => $_) {
    $v["name_$l"] = trim((string)($_POST["name_$l"] ?? ''));
    $v["desc_$l"] = trim((string)($_POST["desc_$l"] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $errors[] = 'פג תוקף הטופס. טענו את הדף מחדש ונסו שוב.';
    $cur = $curById[$v['curriculum_id']] ?? null;
    if (!$cur) $errors[] = 'בחרו מסלול.';
    $parent = $v['parent_id'] ? ($byId[$v['parent_id']] ?? null) : null;
    if ($v['parent_id'] && (!$parent || $parent['parent_id'] || (int)$parent['curriculum_id'] !== $v['curriculum_id'])) {
        $errors[] = 'הנושא הראשי שנבחר לא שייך למסלול הזה.';
    }
    foreach ($LANG_FIELDS as $l => [$label]) {
        if ($v["name_$l"] === '') $errors[] = "חסר השם ב$label.";
        elseif (mb_strlen($v["name_$l"]) > 255) $errors[] = "השם ב$label ארוך מדי (עד 255 תווים).";
        if (mb_strlen($v["desc_$l"]) > 500) $errors[] = "התיאור ב$label ארוך מדי (עד 500 תווים).";
    }
    // siblings = same curriculum and same parent
    $siblings = array_values(array_filter($all, function ($s) use ($v) {
        return (int)$s['curriculum_id'] === $v['curriculum_id'] && (int)$s['parent_id'] === $v['parent_id'];
    }));
    foreach ($siblings as $s) {
        foreach ($LANG_FIELDS as $l => [$label]) {
            if ($v["name_$l"] !== '' && mb_strtolower($s[$l] ?? '') === mb_strtolower($v["name_$l"])) {
                $errors[] = "כבר יש באותו מקום נושא בשם \"" . $v["name_$l"] . "\" ($label).";
            }
        }
    }
    $before = $v['before_id'] ? ($byId[$v['before_id']] ?? null) : null;
    if ($v['before_id'] && (!$before || (int)$before['parent_id'] !== $v['parent_id'] || (int)$before['curriculum_id'] !== $v['curriculum_id'])) {
        $errors[] = 'המיקום שנבחר לא נמצא באותה רשימה.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // study order is counted among siblings: at the end, or in the place of the chosen sibling (which moves down)
            if ($before) {
                $order = (int)$before['study_order'];
                $sql = 'UPDATE subjects SET study_order = study_order + 1 WHERE curriculum_id = ? AND study_order >= ? AND '
                     . ($v['parent_id'] ? 'parent_id = ?' : 'parent_id IS NULL');
                $args = [$v['curriculum_id'], $order];
                if ($v['parent_id']) $args[] = $v['parent_id'];
                $pdo->prepare($sql)->execute($args);
            } else {
                $order = $siblings ? max(array_map(function ($s) { return (int)$s['study_order']; }, $siblings)) + 1 : 1;
            }
            $base = ($cur['units'] ? $cur['units'] : 'fr') . '-' . (slugify($v['name_en']) ?: 'subject');
            $slug = $base; $i = 2;
            while (q('SELECT id FROM subjects WHERE slug = ?', [$slug], true)) $slug = $base . '-' . $i++;

            $pdo->prepare('INSERT INTO subjects (curriculum_id, parent_id, legacy_id, units, slug, study_order) VALUES (?, ?, NULL, ?, ?, ?)')
                ->execute([$v['curriculum_id'], $v['parent_id'] ?: null, $cur['units'] ?: null, $slug, $order]);
            $newId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO subject_names (subject_id, lang, name, description, is_original, is_reviewed) VALUES (?, ?, ?, ?, ?, 1)');
            foreach ($LANG_FIELDS as $l => $_) {
                $ins->execute([$newId, $l, $v["name_$l"], $v["desc_$l"] !== '' ? $v["desc_$l"] : null, $l === $cur['content_lang'] ? 1 : 0]);
            }
            $pdo->commit();
            header('Location: subject_add.php?done=' . $newId); exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'השמירה נכשלה: ' . $e->getMessage();
        }
    }
}

$done = isset($_GET['done']) ? ($byId[(int)$_GET['done']] ?? null) : null;

admin_header('הוספת נושא'); ?>
<div class="crumbs"><a href="index.php">פונקציות פיתוח</a> › הוספת נושא או תת־נושא</div>
<h1 class="page">הוספת נושא או תת־נושא</h1>

<?php if ($done): $dp = $done['parent_id'] ? $byId[(int)$done['parent_id']] : null; ?>
<div class="panel ok">
  <strong>נשמר:</strong> <?= h($done['he']) ?> · <span dir="ltr"><?= h($done['en']) ?></span><?php if (isset($LANG_FIELDS['fr'])): ?> · <span dir="ltr"><?= h($done['fr']) ?></span><?php endif; ?>
  <?= $dp ? '<br>תת־נושא של ' . h($dp['he']) : '<br>נושא ראשי' ?>
  <div class="actions">
    <a class="btn" href="../subject.php?s=<?= h($done['slug']) ?>&amp;lang=he">צפייה באתר</a>
    <a class="btn ghost" href="subject_add.php?parent=<?= (int)($done['parent_id'] ?: $done['id']) ?>"><?= $dp ? 'עוד תת־נושא באותו נושא' : 'הוספת תת־נושא לנושא הזה' ?></a>
    <a class="btn ghost" href="subject_add.php?cur=<?= (int)$done['curriculum_id'] ?>">נושא ראשי חדש</a>
  </div>
</div>
<?php endif; ?>

<?php if ($errors): ?>
<div class="panel err"><strong>לא נשמר:</strong><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="admin-grid">
<form method="post" class="panel form" id="f">
  <?= csrf_field() ?>
  <fieldset>
    <legend>מסלול</legend>
    <div class="seg">
    <?php foreach ($curricula as $c): ?>
      <label><input type="radio" name="curriculum_id" value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $v['curriculum_id'] ? ' checked' : '' ?>><span><?= h($c['name']) ?></span></label>
    <?php endforeach; ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>סוג</legend>
    <label>מיקום בעץ הנושאים
      <select name="parent_id" id="parent">
        <option value="0">נושא ראשי חדש</option>
        <?php foreach ($all as $s): if ($s['parent_id']) continue; ?>
        <option value="<?= (int)$s['id'] ?>" data-cur="<?= (int)$s['curriculum_id'] ?>"<?= (int)$s['id'] === $v['parent_id'] ? ' selected' : '' ?>>תת־נושא של: <?= h($s['he']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>מיקום בסדר הלימוד
      <select name="before_id" id="before"><option value="0">בסוף הרשימה</option></select>
    </label>
  </fieldset>

  <fieldset>
    <legend>שם הנושא</legend>
    <?php foreach ($LANG_FIELDS as $l => [$label, $dir]): ?>
    <label class="lang-field"><span class="lang-tag"><?= h($label) ?></span>
      <input type="text" name="name_<?= $l ?>" lang="<?= $l ?>" dir="<?= $dir ?>" maxlength="255" required value="<?= h($v["name_$l"]) ?>"
             placeholder="<?= $l === 'he' ? 'למשל: חקירת פונקציות רציונליות' : ($l === 'en' ? 'e.g. Investigating rational functions' : 'p. ex. Étude de fonctions rationnelles') ?>">
    </label>
    <?php endforeach; ?>
    <p class="hint">הכתובת של הדף נוצרת מהשם באנגלית.</p>
  </fieldset>

  <details class="desc"<?= implode('', array_map(function ($l) use ($v) { return $v["desc_$l"]; }, array_keys($LANG_FIELDS))) !== '' ? ' open' : '' ?>>
    <summary>תיאור קצר (לא חובה)</summary>
    <div class="body">
    <?php foreach ($LANG_FIELDS as $l => [$label, $dir]): ?>
      <label class="lang-field"><span class="lang-tag"><?= h($label) ?></span>
        <textarea name="desc_<?= $l ?>" lang="<?= $l ?>" dir="<?= $dir ?>" maxlength="500" rows="2"><?= h($v["desc_$l"]) ?></textarea>
      </label>
    <?php endforeach; ?>
    </div>
  </details>

  <div class="actions"><button class="btn" type="submit">שמירה</button><a class="btn ghost" href="index.php">ביטול</a></div>
</form>

<aside class="panel side">
  <h3 id="ctx-title">הנושאים הקיימים</h3>
  <ol id="ctx" class="ctx"></ol>
</aside>
</div>

<script>
const SUBJECTS = <?= json_encode(array_map(function ($s) {
    return ['id' => (int)$s['id'], 'cur' => (int)$s['curriculum_id'], 'parent' => (int)$s['parent_id'], 'he' => $s['he'], 'en' => $s['en']];
}, $all), JSON_UNESCAPED_UNICODE) ?>;
const BEFORE = <?= (int)$v['before_id'] ?>;
const f = document.getElementById('f'), parentSel = document.getElementById('parent'), beforeSel = document.getElementById('before');
function cur() { const r = f.querySelector('input[name=curriculum_id]:checked'); return r ? +r.value : 0; }
function refresh(keepParent) {
  const c = cur();
  for (const o of parentSel.options) { if (o.dataset.cur) { o.hidden = +o.dataset.cur !== c; } }
  if (!keepParent && parentSel.selectedOptions[0] && parentSel.selectedOptions[0].hidden) parentSel.value = '0';
  const p = +parentSel.value;
  const sibs = SUBJECTS.filter(s => s.cur === c && s.parent === p);
  beforeSel.length = 1;
  for (const s of sibs) { const o = new Option('לפני: ' + s.he, s.id); if (s.id === BEFORE) o.selected = true; beforeSel.add(o); }
  const parentName = p ? SUBJECTS.find(s => s.id === p).he : null;
  document.getElementById('ctx-title').textContent = p ? 'תתי־הנושאים של ' + parentName : 'הנושאים הראשיים במסלול';
  const ol = document.getElementById('ctx'); ol.innerHTML = '';
  for (const s of sibs) { const li = document.createElement('li'); li.innerHTML = '<span></span><small dir="ltr"></small>'; li.firstChild.textContent = s.he; li.lastChild.textContent = s.en; ol.appendChild(li); }
  if (!sibs.length) { const li = document.createElement('li'); li.className = 'none'; li.textContent = 'עוד אין כאן נושאים.'; ol.appendChild(li); }
}
f.addEventListener('change', e => { if (e.target.name === 'curriculum_id') refresh(false); if (e.target === parentSel) refresh(true); });
refresh(true);
</script>
<?php admin_footer();
