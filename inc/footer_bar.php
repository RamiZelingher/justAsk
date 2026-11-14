<?php
// Bottom line of every page (public site and development area): version, feedback button, credit.

/** A value from the settings table (version, feedback_email); null when missing. */
function setting($name) {
    static $all = null;
    if ($all === null) {
        $all = [];
        try { foreach (q("SELECT name, value FROM settings") as $r) $all[$r['name']] = $r['value']; } catch (Exception $e) { }
    }
    return $all[$name] ?? null;
}

/** "2026-10" -> "אוקטובר 2026" / "October 2026" / "octobre 2026". */
function version_label($v) {
    if (!preg_match('/^(\d{4})-(\d{1,2})$/', (string)$v, $m)) return (string)$v;
    $names = ['he' => ['ינואר','פברואר','מרץ','אפריל','מאי','יוני','יולי','אוגוסט','ספטמבר','אוקטובר','נובמבר','דצמבר'],
              'en' => ['January','February','March','April','May','June','July','August','September','October','November','December'],
              'fr' => ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre']];
    $l = isset($names[lang()]) ? lang() : 'he';
    return $names[$l][(int)$m[2] - 1] . ' ' . $m[1];
}

/** Bottom line of every page: version, feedback / corrections button, credit. */
function site_footer_bar() {
    $ver = setting('version');
    $mail = (string)setting('feedback_email');
    echo '<div class="fbar">';
    if ($ver) echo '<span>' . h(t('version_x', version_label($ver))) . '</span>';
    if ($mail !== '' && strpos($mail, '@') !== false) {
        [$user, $dom] = explode('@', $mail, 2);   // the address is put together in the browser, not written in the page
        echo '<button type="button" class="fb-btn" data-u="' . h(strrev($user)) . '" data-d="' . h(strrev($dom)) . '" data-s="' . h(t('feedback_subject')) . '" data-b="' . h(t('feedback_body')) . '"'
           . ' onclick="var b=this,a=b.dataset.u.split(\'\').reverse().join(\'\')+\'@\'+b.dataset.d.split(\'\').reverse().join(\'\');location.href=\'mailto:\'+a+\'?subject=\'+encodeURIComponent(b.dataset.s+\' – \'+document.title)+\'&body=\'+encodeURIComponent(b.dataset.b+\'\\n\'+location.href+\'\\n\\n\');">'
           . '&#9993; ' . h(t('feedback')) . '</button>';
    }
    echo '<span>' . h(t('built_with')) . '</span>';
    echo '</div>';
}
