<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function kind_label($k) { return t('kind_' . ($k ?: 'other')); }

/** Stored content is trusted HTML + MathML from our own database; adapt internal links to this site. */
function content($html) {
    $html = (string)$html;
    $html = str_replace('href="/theory/', 'href="theory.php?id=', $html);
    $html = str_replace('src="/media/', 'src="media/', $html);
    return $html;
}

/* ---- translated content -------------------------------------------------------------
   The questions and the theory are written in Hebrew. A page in a language that has
   translations (English) shows the translated text and lists only translated items. */
const CONTENT_TRANSLATIONS = ['en'];

/** The language whose translations this page uses, or null (the content is shown as written). */
function tlang() { return in_array(lang(), CONTENT_TRANSLATIONS, true) ? lang() : null; }

/** SQL condition: question q is available on this page. */
function q_available($q = 'q') {
    return tlang() ? " AND EXISTS (SELECT 1 FROM question_languages ql WHERE ql.question_id = $q.id AND ql.lang = " . db()->quote(tlang()) . ")" : '';
}

/** SQL condition: theory item t is available on this page. */
function t_available($t = 't') {
    return tlang() ? " AND EXISTS (SELECT 1 FROM translations tt WHERE tt.entity = 'theory' AND tt.entity_id = $t.id AND tt.field = 'title' AND tt.lang = " . db()->quote(tlang()) . ")" : '';
}

/** [id => html] of one translated field for many rows, e.g. tr_map('hint', [3, 4], 'body'). */
function tr_map($entity, array $ids, $field) {
    if (!tlang() || !$ids) return [];
    $ids = array_map('intval', $ids);
    $out = [];
    foreach (q("SELECT entity_id, html FROM translations WHERE entity = ? AND field = ? AND lang = ? AND entity_id IN (" . implode(',', $ids) . ")",
               [$entity, $field, tlang()]) as $r) {
        $out[(int)$r['entity_id']] = $r['html'];
    }
    return $out;
}

/** Replace the given fields of each row by their translation (rows keyed by their 'id'). */
function tr_rows($entity, array $rows, array $fields) {
    if (!tlang() || !$rows) return $rows;
    $ids = array_column($rows, 'id');
    foreach ($fields as $field => $col) {
        $m = tr_map($entity, $ids, $field);
        foreach ($rows as &$r) { if (isset($m[(int)$r['id']])) $r[$col] = $m[(int)$r['id']]; }
        unset($r);
    }
    return $rows;
}

/** Language and direction of the content shown on this page, given the language it was written in. */
function shown_lang($written) { return tlang() ?: $written; }

/** SQL pieces that fetch a subject's name in the interface language (n) and the second language (n2). */
function name_joins($alias = 's') {
    return " LEFT JOIN subject_names n  ON n.subject_id  = $alias.id AND n.lang  = " . db()->quote(lang()) .
           " LEFT JOIN subject_names n2 ON n2.subject_id = $alias.id AND n2.lang = " . (lang2() ? db()->quote(lang2()) : 'NULL') . ' ';
}

function page_header($title) {
    header('Content-Type: text/html; charset=utf-8');
    $l = lang(); ?>
<!DOCTYPE html>
<html lang="<?= $l ?>" dir="<?= dir_of($l) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · JustAsk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(dirname(__DIR__) . '/style.css') ?>">
</head>
<body>
<header class="site-header"><div class="wrap">
  <a class="brand" href="index.php"><span class="brand-mark">∑</span><span>JustAsk<small><?= h(t('site_tagline')) ?></small></span></a>
  <nav class="langs" aria-label="Language">
    <?php foreach (LANGS as $code => $info): ?>
      <a href="<?= h(url_lang($code)) ?>" lang="<?= $code ?>"<?= $code === $l ? ' class="on" aria-current="true"' : '' ?>><?= h($info[0]) ?></a>
    <?php endforeach; ?>
  </nav>
</div></header>
<main class="wrap">
<?php }

require_once __DIR__ . '/footer_bar.php';
require_once __DIR__ . '/search_form.php';

function page_footer() { ?>
</main>
<footer><div class="wrap"><?= h(t('footer')) ?><?php site_footer_bar(); ?></div></footer>
<script src="flash.js?v=<?= @filemtime(dirname(__DIR__) . '/flash.js') ?>"<?= is_file(dirname(__DIR__) . '/ruffle/ruffle.js') ? ' data-ruffle="ruffle/ruffle.js"' : '' ?>></script>
</body>
</html>
<?php }

function not_found() {
    http_response_code(404);
    page_header(t('not_found'));
    echo '<section class="hero"><h1>' . h(t('not_found')) . '</h1><p><a href="index.php">' . h(t('to_home')) . '</a></p></section>';
    page_footer();
    exit;
}

/** Friendly message when the database is not set up yet. */
set_exception_handler(function ($e) {
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    $msg = h($e->getMessage());
    echo "<!DOCTYPE html><html lang='he' dir='rtl'><meta charset='utf-8'><body style='font-family:Arial;padding:40px;line-height:1.7'>"
       . "<h2>אין חיבור למסד הנתונים</h2><p>אם זו הפעלה ראשונה, או שמבנה הנתונים התעדכן, פתח את <a href='install.php?force=1'>install.php</a>.</p>"
       . "<p>אם המסד כבר קיים, בדוק את ההגדרות בקובץ <code>config.php</code> (שם משתמש, סיסמה, פורט).</p>"
       . "<p style='color:#888;direction:ltr;text-align:left'>$msg</p></body></html>";
});
