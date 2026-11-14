<?php
// Interface language: ?lang=xx sets it (and a cookie for a year); default Hebrew.
const LANGS = ['he' => ['עברית', 'rtl'], 'en' => ['English', 'ltr'], 'fr' => ['Français', 'ltr']];

function lang() {
    static $l = null;
    if ($l === null) {
        $l = 'he';
        if (isset($_GET['lang']) && isset(LANGS[$_GET['lang']])) {
            $l = $_GET['lang'];
            if (!headers_sent()) setcookie('lang', $l, time() + 365 * 86400, '/');
        } elseif (isset($_COOKIE['lang']) && isset(LANGS[$_COOKIE['lang']])) {
            $l = $_COOKIE['lang'];
        }
    }
    return $l;
}
function dir_of($l) { return LANGS[$l][1] ?? 'rtl'; }
/** The second language shown under names: English under Hebrew; English and French pages show one language only. */
function lang2() { return lang() === 'he' ? 'en' : null; }

/** Name of a study level: in English, 4 units = Pre-Calculus and 5 units = AP Calculus. */
function units_label($u) {
    if (lang() === 'en' && $u == 4) return 'Pre-Calculus';
    if (lang() === 'en' && $u == 5) return 'AP Calculus';
    return t('units', $u);
}

/** URL of the current page in another language. */
function url_lang($l) {
    $q = $_GET; $q['lang'] = $l;
    return strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?') . '?' . http_build_query($q);
}

const T = [
    'find_q'         => ['he' => 'חיפוש שאלה לפי קוד', 'en' => 'Find a question by code', 'fr' => 'Trouver une question par son code'],
    'pdf_btn'        => ['he' => 'קובץ PDF', 'en' => 'PDF file', 'fr' => 'Fichier PDF'],
    'video_btn'      => ['he' => 'שיעור וידאו', 'en' => 'Video lesson', 'fr' => 'Leçon vidéo'],
    'version_x'      => ['he' => 'גרסה %s', 'en' => 'Version %s', 'fr' => 'Version %s'],
    'feedback'       => ['he' => 'משוב ותיקונים', 'en' => 'Feedback & corrections', 'fr' => 'Remarques et corrections'],
    'feedback_subject' => ['he' => 'משוב / תיקון', 'en' => 'Feedback / correction', 'fr' => 'Remarque / correction'],
    'feedback_body'  => ['he' => 'הדף:', 'en' => 'Page:', 'fr' => 'Page :'],
    'built_with'     => ['he' => 'פותח באמצעות Claude Code', 'en' => 'Built with Claude Code', 'fr' => 'Développé avec Claude Code'],
    'search_title'   => ['he' => 'חיפוש נושא', 'en' => 'Topic search', 'fr' => 'Recherche de sujet'],
    'search_label'   => ['he' => 'חיפוש נושא', 'en' => 'Search a topic', 'fr' => 'Chercher un sujet'],
    'search_ph'      => ['he' => 'למשל: משוואת משיק', 'en' => 'e.g. tangent line', 'fr' => 'p. ex. tangente'],
    'search_btn'     => ['he' => 'חפש', 'en' => 'Search', 'fr' => 'Chercher'],
    'voice_btn'      => ['he' => 'חיפוש קולי', 'en' => 'Voice search', 'fr' => 'Recherche vocale'],
    'voice_listening'=> ['he' => 'מקשיב… דברו עכשיו', 'en' => 'Listening… speak now', 'fr' => 'À l’écoute… parlez'],
    'voice_nothing'  => ['he' => 'לא נשמע דיבור, נסו שוב', 'en' => 'No speech heard, try again', 'fr' => 'Rien entendu, réessayez'],
    'voice_denied'   => ['he' => 'אין הרשאה למיקרופון. אפשרו אותה בדפדפן', 'en' => 'No microphone permission. Allow it in the browser', 'fr' => 'Micro non autorisé. Autorisez-le dans le navigateur'],
    'search_none'    => ['he' => 'לא נמצאו נושאים עבור "%s".', 'en' => 'No topics found for "%s".', 'fr' => 'Aucun sujet trouvé pour « %s ».'],
    'search_subjects'=> ['he' => 'נושאים (%d)', 'en' => 'Topics (%d)', 'fr' => 'Sujets (%d)'],
    'search_theory'  => ['he' => 'רקע תאורטי (%d)', 'en' => 'Theory (%d)', 'fr' => 'Théorie (%d)'],
    'search_nq'      => ['he' => '%d שאלות', 'en' => '%d questions', 'fr' => '%d questions'],
    'q_source'       => ['he' => 'מקור שאלה', 'en' => 'Question source', 'fr' => 'Source de la question'],
    'q_id'           => ['he' => 'קוד שאלה', 'en' => 'Question code', 'fr' => 'Code'],
    'q_lang'         => ['he' => 'שפה', 'en' => 'Language', 'fr' => 'Langue'],
    'go'             => ['he' => 'הצג', 'en' => 'Show', 'fr' => 'Afficher'],
    'id_label'       => ['he' => 'קוד שאלה = %s', 'en' => 'Question code = %s', 'fr' => 'Code de la question = %s'],
    'site_tagline'   => ['he' => 'שאלות בגרות במתמטיקה עם פתרונות מודרכים', 'en' => 'Math exam questions with guided solutions', 'fr' => "Questions d'examen de maths avec corrigés guidés"],
    'subjects'       => ['he' => 'נושאים', 'en' => 'Subjects', 'fr' => 'Thèmes'],
    'hero_title'     => ['he' => 'שאלות בגרות במתמטיקה, עם פתרון שמלמד', 'en' => 'Math exam questions, with solutions that teach', 'fr' => "Des questions d'examen, avec des corrigés qui expliquent"],
    'hero_text'      => ['he' => 'בחרו מסלול ונושא. לכל שאלה: תשובה סופית, הכוונה ברמזים, ופתרון מלא שלב אחר שלב עם קישור לרקע התאורטי.',
                         'en' => 'Pick a track and a subject. Every question has a final answer, step-by-step hints, and a full worked solution linked to the theory behind it.',
                         'fr' => "Choisissez un programme et un thème. Chaque question propose la réponse finale, des indices et un corrigé détaillé relié au cours."],
    'n_subjects'     => ['he' => '%d נושאים', 'en' => '%d subjects', 'fr' => '%d thèmes'],
    'n_questions'    => ['he' => '%d שאלות', 'en' => '%d questions', 'fr' => '%d questions'],
    'one_question'   => ['he' => 'שאלה אחת', 'en' => '1 question', 'fr' => '1 question'],
    'questions_word' => ['he' => 'שאלות', 'en' => 'questions', 'fr' => 'questions'],
    'units'          => ['he' => '%d יח"ל', 'en' => '%d units', 'fr' => '%d unités'],
    'coming_soon'    => ['he' => 'השאלות בנושא הזה עוד לא הועברו לאתר החדש.', 'en' => "This subject's questions have not been moved to the new site yet.", 'fr' => "Les questions de ce thème n'ont pas encore été transférées."],
    'col_theory'     => ['he' => 'רקע תיאורטי', 'en' => 'Theory', 'fr' => 'Cours'],
    'theory_of'      => ['he' => 'רקע תיאורטי: %s', 'en' => 'Theory: %s', 'fr' => 'Cours : %s'],
    'theory_lead'    => ['he' => 'ההגדרות, המשפטים והשיטות של הנושא. לחצו על פריט כדי לפתוח אותו.', 'en' => 'The definitions, theorems and methods of this subject. Click an item to open it.', 'fr' => 'Définitions, théorèmes et méthodes de ce thème. Cliquez sur un élément pour l\'ouvrir.'],
    'theory_pick'    => ['he' => 'בחרו את סוג הרקע התיאורטי כדי לפתוח את הרשימה.', 'en' => 'Choose a category to open its list.', 'fr' => 'Choisissez une catégorie pour ouvrir sa liste.'],
    'questions_using'=> ['he' => 'שאלות שנעזרות ב: %s', 'en' => 'Questions that use: %s', 'fr' => 'Questions qui utilisent : %s'],
    'not_translated' => ['he' => 'השאלה הזו עדיין לא תורגמה.', 'en' => 'This question has not been translated into English yet.', 'fr' => 'Cette question n\'est pas encore traduite.'],
    'not_translated_th' => ['he' => 'הפריט הזה עדיין לא תורגם.', 'en' => 'This item has not been translated into English yet.', 'fr' => 'Cet élément n\'est pas encore traduit.'],
    'read_original'  => ['he' => 'לקריאה בעברית', 'en' => 'Read it in Hebrew', 'fr' => 'La lire en hébreu'],
    'machine_translation' => ['he' => 'תרגום אוטומטי', 'en' => 'Translated from Hebrew; not yet reviewed', 'fr' => 'Traduit de l\'hébreu, non relu'],
    'grp_definition' => ['he' => 'הגדרות', 'en' => 'Definitions', 'fr' => 'Définitions'],
    'grp_theorem'    => ['he' => 'משפטים', 'en' => 'Theorems', 'fr' => 'Théorèmes'],
    'grp_method'     => ['he' => 'עקרונות ושיטות', 'en' => 'Principles and methods', 'fr' => 'Principes et méthodes'],
    'grp_other'      => ['he' => 'תוצאות ידועות', 'en' => 'Known results', 'fr' => 'Résultats connus'],
    'open_page'      => ['he' => 'לדף המלא', 'en' => 'Open full page', 'fr' => 'Page complète'],
    'theory_none'    => ['he' => 'עדיין אין רקע תיאורטי לנושא הזה.', 'en' => 'No theory for this subject yet.', 'fr' => 'Pas encore de cours pour ce thème.'],
    'col_subject'    => ['he' => 'נושא', 'en' => 'Subject', 'fr' => 'Thème'],
    'no_questions_yet'=> ['he' => 'עוד אין שאלות', 'en' => 'No questions yet', 'fr' => 'Pas encore de questions'],
    'subtopics'      => ['he' => 'תתי־נושאים', 'en' => 'Subtopics', 'fr' => 'Sous-thèmes'],
    'question_x_of_y'=> ['he' => 'שאלה %d מתוך %d', 'en' => 'Question %d of %d', 'fr' => 'Question %d sur %d'],
    'exam_q'         => ['he' => 'בגרות %s, שאלה %d', 'en' => 'Bagrut %s, question %d', 'fr' => 'Bagrout %s, question %d'],
    'tab_question'   => ['he' => 'השאלה', 'en' => 'Question', 'fr' => 'Énoncé'],
    'tab_hints'      => ['he' => 'רקע תיאורטי', 'en' => 'Theory', 'fr' => 'Rappels de cours'],
    'tab_guide'      => ['he' => 'הכוונה', 'en' => 'Guidance', 'fr' => 'Pistes'],
    'tab_solution'   => ['he' => 'פתרון מפורט', 'en' => 'Detailed solution', 'fr' => 'Corrigé détaillé'],
    'tab_answer'     => ['he' => 'תשובה סופית', 'en' => 'Final answer', 'fr' => 'Réponse finale'],
    'hints_lead'     => ['he' => 'ההגדרות, המשפטים והשיטות שהשאלה נשענת עליהם. לחצו על נושא כדי לפתוח אותו.', 'en' => 'Ideas and theorems worth knowing before you start. Click one to open it.', 'fr' => 'Notions et théorèmes utiles avant de commencer. Cliquez pour les ouvrir.'],
    'part_n'         => ['he' => 'חלק %d', 'en' => 'Part %d', 'fr' => 'Partie %d'],
    'final_answer'   => ['he' => 'תשובה סופית', 'en' => 'Final answer', 'fr' => 'Réponse finale'],
    'hints'          => ['he' => 'הכוונה לפתרון', 'en' => 'Hints', 'fr' => 'Indices'],
    'n_hints'        => ['he' => '(%d רמזים)', 'en' => '(%d hints)', 'fr' => '(%d indices)'],
    'full_solution'  => ['he' => 'פתרון מלא שלב אחר שלב', 'en' => 'Full step-by-step solution', 'fr' => 'Corrigé détaillé, étape par étape'],
    'n_steps'        => ['he' => '(%d שלבים)', 'en' => '(%d steps)', 'fr' => '(%d étapes)'],
    'theory_for_q'   => ['he' => 'רקע תאורטי לשאלה', 'en' => 'Theory for this question', 'fr' => 'Rappels de cours pour cette question'],
    'theory'         => ['he' => 'רקע תאורטי', 'en' => 'Theory', 'fr' => 'Rappels de cours'],
    'prev_q'         => ['he' => '→ השאלה הקודמת', 'en' => '← Previous question', 'fr' => '← Question précédente'],
    'next_q'         => ['he' => 'השאלה הבאה ←', 'en' => 'Next question →', 'fr' => 'Question suivante →'],
    'used_in'        => ['he' => 'שאלות שמשתמשות בפריט הזה', 'en' => 'Questions that use this item', 'fr' => 'Questions qui utilisent cet élément'],
    'back'           => ['he' => '→ חזרה', 'en' => '← Back', 'fr' => '← Retour'],
    'not_found'      => ['he' => 'הדף לא נמצא', 'en' => 'Page not found', 'fr' => 'Page introuvable'],
    'to_home'        => ['he' => 'חזרה לעמוד הראשי', 'en' => 'Back to the home page', 'fr' => "Retour à l'accueil"],
    'content_note'   => ['he' => 'תוכן השאלות כתוב בעברית', 'en' => 'Questions are written in Hebrew', 'fr' => 'Les questions sont rédigées en hébreu'],
    'content_note_fr'=> ['he' => 'תוכן השאלות כתוב בצרפתית', 'en' => 'Questions are written in French', 'fr' => 'Les questions sont rédigées en français'],
    'kind_definition'=> ['he' => 'הגדרה', 'en' => 'Definition', 'fr' => 'Définition'],
    'kind_theorem'   => ['he' => 'משפט', 'en' => 'Theorem', 'fr' => 'Théorème'],
    'kind_method'    => ['he' => 'שיטה', 'en' => 'Method', 'fr' => 'Méthode'],
    'kind_other'     => ['he' => 'רקע', 'en' => 'Background', 'fr' => 'Rappel'],
    'footer'         => ['he' => 'JustAsk · שאלות בגרות במתמטיקה עם פתרונות מודרכים', 'en' => 'JustAsk · Math exam questions with guided solutions', 'fr' => "JustAsk · Questions d'examen de maths avec corrigés guidés"],
];

/** "שאלה אחת" / "5 שאלות" */
function nq($n) { return (int)$n === 1 ? t('one_question') : t('n_questions', (int)$n); }

function t($key, ...$args) {
    $s = T[$key][lang()] ?? T[$key]['he'] ?? $key;
    return $args ? vsprintf($s, $args) : $s;
}

/** "4 יחידות יוני 1996" → localized "4 units, June 1996" from the exam row. */
function exam_label($e) {
    $months = ['ינואר' => ['January', 'janvier'], 'פברואר' => ['February', 'février'], 'מאי' => ['May', 'mai'],
               'יוני' => ['June', 'juin'], 'יולי' => ['July', 'juillet'], 'קיץ' => ['Summer', 'été'], 'חורף' => ['Winter', 'hiver'],
               'דצמבר' => ['December', 'décembre'], 'אוגוסט' => ['August', 'août'], 'קיץ מועד א' => ['Summer (1st sitting)', 'été (1re session)'],
               'קיץ מועד ב' => ['Summer (2nd sitting)', 'été (2e session)']];
    if (lang() === 'he' || empty($e['year'])) return $e['exam_title'] ?? '';
    $s = $e['session'] ?? '';
    $i = lang() === 'en' ? 0 : 1;
    $sess = $months[$s][$i] ?? $s;
    $u = $e['units'] ?? null;
    return trim(($u ? units_label($u) . ', ' : '') . $sess . ' ' . $e['year']);
}
