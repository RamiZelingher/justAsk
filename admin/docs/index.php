<?php
require __DIR__ . '/../_admin.php';
admin_guard();

$docs = [
    ['database.php', 'מסד הנתונים', 'כל הטבלאות, טבלה אחר טבלה: שמות העמודות, הסוג, המפתחות, מספר השורות והסבר למה כל עמודה משמשת.'],
];

admin_header('תיעוד'); ?>
<section class="hero"><h1>תיעוד</h1>
<p>מסמכי התיעוד של האתר. הם פתוחים רק מהמחשב הזה, כמו שאר אזור הפיתוח.</p></section>
<div class="tools">
<?php foreach ($docs as [$href, $title, $desc]): ?>
  <a class="tool card" href="<?= h($href) ?>"><h3><?= h($title) ?></h3><p><?= h($desc) ?></p><span class="go">פתיחה ←</span></a>
<?php endforeach; ?>
</div>
<?php admin_footer();
