<?php
require __DIR__ . '/_admin.php';
admin_guard();

$stats = q("SELECT c.code, cn.name,
                   (SELECT COUNT(*) FROM subjects s WHERE s.curriculum_id = c.id AND s.parent_id IS NULL) AS n_top,
                   (SELECT COUNT(*) FROM subjects s WHERE s.curriculum_id = c.id AND s.parent_id IS NOT NULL) AS n_sub
              FROM curricula c LEFT JOIN curriculum_names cn ON cn.curriculum_id = c.id AND cn.lang = 'he'
             ORDER BY c.sort_order");
$unreviewed = (int)q("SELECT COUNT(*) AS n FROM subject_names WHERE is_reviewed = 0", [], true)['n'];
$nq = (int)q("SELECT COUNT(*) AS n FROM questions", [], true)['n'];

$tools = [
    ['tools.php', 'כלי המרה והגדרות', 'גרסת האתר וכתובת המשוב, העלאת קובצי PDF ווידאו לשאלות, מצב התרגום, וכלי ההמרה שבתיקייה tools.', true],
    ['users.php', 'משתמשים', 'תלמידים, מורים ומנהלי מערכת: יצירת חשבונות, תפקיד, חסימה, איפוס סיסמה ושיוך תלמידים למורים.', true],
    ['docs/index.php', 'תיעוד', 'תיעוד מסד הנתונים: כל הטבלאות, שמות העמודות והסבר למה כל עמודה משמשת.', true],
    ['subject_add.php', 'הוספת נושא או תת־נושא', 'נושא חדש במסלול, או תת־נושא תחת נושא קיים, עם השם בעברית, באנגלית ובצרפתית.', true],
    ['', 'עריכת נושאים ותרגומים', 'שינוי שמות, סדר והעברת תת־נושא לנושא אחר.', false],
    ['', 'בדיקת תרגומים', sprintf('מעבר על %d שמות שעוד לא נבדקו, וסימון שלהם כבדוקים.', $unreviewed), false],
    ['', 'הוספת שאלה', 'שאלה חדשה עם סעיפים, תשובה סופית, רמזים ושלבי פתרון.', false],
    ['', 'ייבוא שאלות מהמאגר הישן', 'העברת שאלות מ-Units45 לפי נושא, עם דוח בעיות.', false],
];

admin_header('פונקציות פיתוח'); ?>
<section class="hero"><h1>פונקציות פיתוח</h1>
<p>כאן מרוכזים הכלים לבניית האתר וניהול התוכן. האזור פתוח רק מהמחשב הזה, ולא מופיע באתר הציבורי.</p></section>

<div class="stats">
<?php foreach ($stats as $s): ?>
  <div class="stat"><div class="stat-n"><?= (int)$s['n_top'] ?> · <?= (int)$s['n_sub'] ?></div><div class="stat-l"><?= h($s['name']) ?><br><span>נושאים · תתי־נושאים</span></div></div>
<?php endforeach; ?>
  <div class="stat"><div class="stat-n"><?= $nq ?></div><div class="stat-l">שאלות<br><span>במסד הנתונים</span></div></div>
</div>

<h2>כלים</h2>
<div class="tools">
<?php foreach ($tools as [$href, $title, $desc, $on]): ?>
  <?php if ($on): ?>
  <a class="tool card" href="<?= h($href) ?>"><h3><?= h($title) ?></h3><p><?= h($desc) ?></p><span class="go">פתיחה ←</span></a>
  <?php else: ?>
  <div class="tool card off"><h3><?= h($title) ?></h3><p><?= h($desc) ?></p><span class="soon">בקרוב</span></div>
  <?php endif; ?>
<?php endforeach; ?>
</div>

<div class="panel warn">
  <strong>שימו לב:</strong> הרצה מחדש של <a href="../install.php?force=1">install.php</a> בונה את מסד הנתונים מהקבצים, ומוחקת נושאים שנוספו כאן.
</div>
<?php admin_footer();
