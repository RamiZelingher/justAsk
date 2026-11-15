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
    // ---- login system
    'login'          => ['he' => 'כניסה', 'en' => 'Log in', 'fr' => 'Connexion'],
    'logout'         => ['he' => 'יציאה', 'en' => 'Log out', 'fr' => 'Déconnexion'],
    'register'       => ['he' => 'הרשמה', 'en' => 'Sign up', 'fr' => 'Inscription'],
    'my_questions'   => ['he' => 'השאלות שלי', 'en' => 'My questions', 'fr' => 'Mes questions'],
    'my_students'    => ['he' => 'התלמידים שלי', 'en' => 'My students', 'fr' => 'Mes élèves'],
    'manage'         => ['he' => 'ניהול', 'en' => 'Admin', 'fr' => 'Administration'],
    'login_title'    => ['he' => 'כניסה לאתר', 'en' => 'Log in', 'fr' => 'Se connecter'],
    'login_user'     => ['he' => 'שם משתמש או אימייל', 'en' => 'Username or e-mail', 'fr' => "Nom d'utilisateur ou e-mail"],
    'password'       => ['he' => 'סיסמה', 'en' => 'Password', 'fr' => 'Mot de passe'],
    'login_bad'      => ['he' => 'שם המשתמש או הסיסמה שגויים.', 'en' => 'Wrong username or password.', 'fr' => "Nom d'utilisateur ou mot de passe incorrect."],
    'login_blocked'  => ['he' => 'החשבון הזה חסום. פנה למנהל המערכת.', 'en' => 'This account is blocked. Contact the administrator.', 'fr' => "Ce compte est bloqué. Contactez l'administrateur."],
    'login_wait'     => ['he' => 'יותר מדי ניסיונות. נסה שוב בעוד דקה.', 'en' => 'Too many attempts. Try again in a minute.', 'fr' => 'Trop de tentatives. Réessayez dans une minute.'],
    'login_needed'   => ['he' => 'צריך להיכנס לאתר כדי לראות את הדף הזה.', 'en' => 'Please log in to see this page.', 'fr' => 'Connectez-vous pour voir cette page.'],
    'no_account'     => ['he' => 'אין לך חשבון? הרשמה לתלמידים', 'en' => "No account? Student sign-up", 'fr' => 'Pas de compte ? Inscription élève'],
    'have_account'   => ['he' => 'כבר יש לך חשבון? כניסה', 'en' => 'Already have an account? Log in', 'fr' => 'Déjà un compte ? Connexion'],
    'register_title' => ['he' => 'הרשמה לתלמידים', 'en' => 'Student sign-up', 'fr' => 'Inscription élève'],
    'reg_note'       => ['he' => 'אחרי ההרשמה האתר יזכור באילו שאלות ביקרת, והמורה שלך יוכל לראות אותן. חשבונות של מורים נפתחים על ידי מנהל המערכת.', 'en' => 'Once you sign up, the site remembers which questions you visited, and your teacher can see them. Teacher accounts are opened by the administrator.', 'fr' => "Après l'inscription, le site garde la liste des questions que vous avez consultées, et votre professeur peut la voir. Les comptes professeurs sont ouverts par l'administrateur."],
    'full_name'      => ['he' => 'שם מלא', 'en' => 'Full name', 'fr' => 'Nom complet'],
    'username'       => ['he' => 'שם משתמש', 'en' => 'Username', 'fr' => "Nom d'utilisateur"],
    'username_help'  => ['he' => 'אותיות באנגלית, ספרות, נקודה או קו תחתון, 3–40 תווים', 'en' => 'Latin letters, digits, dot or underscore, 3–40 characters', 'fr' => 'Lettres latines, chiffres, point ou tiret bas, 3 à 40 caractères'],
    'email_opt'      => ['he' => 'אימייל (רשות)', 'en' => 'E-mail (optional)', 'fr' => 'E-mail (facultatif)'],
    'password_new'   => ['he' => 'סיסמה (לפחות 8 תווים)', 'en' => 'Password (at least 8 characters)', 'fr' => 'Mot de passe (au moins 8 caractères)'],
    'password2'      => ['he' => 'אימות סיסמה', 'en' => 'Repeat the password', 'fr' => 'Confirmez le mot de passe'],
    'err_username'   => ['he' => 'שם המשתמש צריך להיות 3–40 תווים: אותיות באנגלית, ספרות, נקודה, מקף או קו תחתון.', 'en' => 'The username must be 3–40 characters: Latin letters, digits, dot, hyphen or underscore.', 'fr' => "Le nom d'utilisateur doit compter 3 à 40 caractères : lettres latines, chiffres, point, tiret ou tiret bas."],
    'err_username_taken' => ['he' => 'שם המשתמש הזה כבר תפוס.', 'en' => 'This username is taken.', 'fr' => "Ce nom d'utilisateur est déjà pris."],
    'err_email'      => ['he' => 'כתובת האימייל לא תקינה.', 'en' => 'The e-mail address is not valid.', 'fr' => "L'adresse e-mail n'est pas valide."],
    'err_email_taken'=> ['he' => 'כתובת האימייל הזאת כבר רשומה.', 'en' => 'This e-mail address is already registered.', 'fr' => 'Cette adresse e-mail est déjà inscrite.'],
    'err_name'       => ['he' => 'צריך למלא שם מלא.', 'en' => 'Please enter your full name.', 'fr' => 'Indiquez votre nom complet.'],
    'err_password'   => ['he' => 'הסיסמה צריכה להיות באורך 8 תווים לפחות.', 'en' => 'The password must be at least 8 characters long.', 'fr' => 'Le mot de passe doit compter au moins 8 caractères.'],
    'err_password2'  => ['he' => 'שתי הסיסמאות אינן זהות.', 'en' => 'The two passwords do not match.', 'fr' => 'Les deux mots de passe ne correspondent pas.'],
    'err_csrf'       => ['he' => 'הטופס פג תוקף. נסה שוב.', 'en' => 'The form expired. Please try again.', 'fr' => 'Le formulaire a expiré. Réessayez.'],
    'err_cur_pass'   => ['he' => 'הסיסמה הנוכחית שגויה.', 'en' => 'The current password is wrong.', 'fr' => 'Le mot de passe actuel est incorrect.'],
    'role_student'   => ['he' => 'תלמיד', 'en' => 'Student', 'fr' => 'Élève'],
    'role_teacher'   => ['he' => 'מורה', 'en' => 'Teacher', 'fr' => 'Professeur'],
    'role_admin'     => ['he' => 'מנהל מערכת', 'en' => 'Administrator', 'fr' => 'Administrateur'],
    'visited'        => ['he' => 'ביקרת', 'en' => 'Visited', 'fr' => 'Consultée'],
    'visited_title'  => ['he' => 'ביקרת בשאלה %d פעמים, לאחרונה ב-%s', 'en' => 'You visited this question %d times, last on %s', 'fr' => 'Consultée %d fois, la dernière le %s'],
    'visited_before' => ['he' => 'כבר ביקרת בשאלה הזאת (%d פעמים, לאחרונה ב-%s)', 'en' => 'You have visited this question before (%d times, last on %s)', 'fr' => 'Vous avez déjà consulté cette question (%d fois, la dernière le %s)'],
    'my_title'       => ['he' => 'השאלות שביקרתי בהן', 'en' => 'Questions I visited', 'fr' => "Questions que j'ai consultées"],
    'my_none'        => ['he' => 'עוד לא ביקרת באף שאלה.', 'en' => "You haven't visited any question yet.", 'fr' => "Vous n'avez encore consulté aucune question."],
    'col_question'   => ['he' => 'קוד שאלה', 'en' => 'Question', 'fr' => 'Question'],
    'col_subject'    => ['he' => 'נושא', 'en' => 'Topic', 'fr' => 'Thème'],
    'col_visits'     => ['he' => 'כניסות', 'en' => 'Visits', 'fr' => 'Visites'],
    'col_first'      => ['he' => 'כניסה ראשונה', 'en' => 'First visit', 'fr' => 'Première visite'],
    'col_last'       => ['he' => 'כניסה אחרונה', 'en' => 'Last visit', 'fr' => 'Dernière visite'],
    'col_when'       => ['he' => 'תאריך ושעה', 'en' => 'Date and time', 'fr' => 'Date et heure'],
    'col_student'    => ['he' => 'תלמיד', 'en' => 'Student', 'fr' => 'Élève'],
    'col_nq'         => ['he' => 'שאלות שביקר', 'en' => 'Questions visited', 'fr' => 'Questions consultées'],
    'my_teachers'    => ['he' => 'המורים שלי', 'en' => 'My teachers', 'fr' => 'Mes professeurs'],
    'my_account'     => ['he' => 'החשבון שלי', 'en' => 'My account', 'fr' => 'Mon compte'],
    'change_pass'    => ['he' => 'שינוי סיסמה', 'en' => 'Change password', 'fr' => 'Changer le mot de passe'],
    'cur_pass'       => ['he' => 'הסיסמה הנוכחית', 'en' => 'Current password', 'fr' => 'Mot de passe actuel'],
    'save'           => ['he' => 'שמירה', 'en' => 'Save', 'fr' => 'Enregistrer'],
    'pass_changed'   => ['he' => 'הסיסמה שונתה.', 'en' => 'Your password was changed.', 'fr' => 'Le mot de passe a été modifié.'],
    'n_visits_total' => ['he' => '%d כניסות ל-%d שאלות', 'en' => '%d visits to %d questions', 'fr' => '%d visites, %d questions'],
    'add_student'    => ['he' => 'הוספת תלמיד לפי שם המשתמש שלו', 'en' => 'Add a student by username', 'fr' => "Ajouter un élève par son nom d'utilisateur"],
    'add_btn'        => ['he' => 'הוספה', 'en' => 'Add', 'fr' => 'Ajouter'],
    'remove'         => ['he' => 'הסרה', 'en' => 'Remove', 'fr' => 'Retirer'],
    'student_added'  => ['he' => 'התלמיד נוסף לרשימה.', 'en' => 'The student was added.', 'fr' => "L'élève a été ajouté."],
    'student_removed'=> ['he' => 'התלמיד הוסר מהרשימה.', 'en' => 'The student was removed.', 'fr' => "L'élève a été retiré."],
    'student_not_found' => ['he' => 'לא נמצא תלמיד עם שם המשתמש הזה.', 'en' => 'No student has this username.', 'fr' => "Aucun élève n'a ce nom d'utilisateur."],
    'no_students'    => ['he' => 'עוד אין תלמידים ברשימה שלך. הוסף תלמיד לפי שם המשתמש שבחר בהרשמה.', 'en' => 'No students yet. Add a student by the username they chose when signing up.', 'fr' => "Aucun élève pour l'instant. Ajoutez un élève avec le nom d'utilisateur choisi à l'inscription."],
    'all_students'   => ['he' => 'כל התלמידים באתר', 'en' => 'All students', 'fr' => 'Tous les élèves'],
    'visits_of'      => ['he' => 'השאלות ש%s ביקר/ה בהן', 'en' => 'Questions visited by %s', 'fr' => 'Questions consultées par %s'],
    'by_question'    => ['he' => 'לפי שאלה', 'en' => 'By question', 'fr' => 'Par question'],
    'all_entries'    => ['he' => 'כל הכניסות', 'en' => 'All visits', 'fr' => 'Toutes les visites'],
    'back_students'  => ['he' => '→ חזרה לרשימת התלמידים', 'en' => '← Back to the students', 'fr' => '← Retour aux élèves'],
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
