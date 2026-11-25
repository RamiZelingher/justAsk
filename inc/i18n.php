<?php
// Interface language (Rami, 21 Nov 2026): the logged-in user's language (users.lang), set by the administrator.
// There is no language switch on the site. Visitors who are not logged in get Hebrew.
// ?lang=xx still works for one page only (no cookie) – used by the "read the original" links of untranslated pages.
// French is switched off for now (Rami, 21 Nov 2026): no French interface, no French track (Première S), no French option
// for users. Nothing is deleted from the database; set FRENCH_ENABLED to true to bring it all back.
const FRENCH_ENABLED = false;
// Arabic added 21 Nov 2026 (pilot: subtopic 6.4 translated; other questions are hidden on Arabic pages until translated)
const LANGS = FRENCH_ENABLED ? ['he' => ['עברית', 'rtl'], 'en' => ['English', 'ltr'], 'ar' => ['العربية', 'rtl'], 'fr' => ['Français', 'ltr']]
                             : ['he' => ['עברית', 'rtl'], 'en' => ['English', 'ltr'], 'ar' => ['العربية', 'rtl']];
/** SQL condition that leaves out the French track while French is switched off (c = alias of curricula). */
function fr_off_sql($c = 'c') { return FRENCH_ENABLED ? '' : " AND $c.content_lang <> 'fr'"; }

function lang() {
    static $l = null;
    if ($l === null) {
        $l = 'he';
        $u = function_exists('current_user') ? current_user() : null;
        if (isset($_GET['lang']) && isset(LANGS[$_GET['lang']])) $l = $_GET['lang'];
        elseif ($u && isset(LANGS[$u['lang'] ?? ''])) $l = $u['lang'];
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
    'login'          => ['he' => 'כניסה', 'en' => 'Log in', 'fr' => 'Connexion', 'ar' => 'دخول'],
    'logout'         => ['he' => 'יציאה', 'en' => 'Log out', 'fr' => 'Déconnexion', 'ar' => 'خروج'],
    'register'       => ['he' => 'הרשמה', 'en' => 'Sign up', 'fr' => 'Inscription', 'ar' => 'تسجيل'],
    'my_questions'   => ['he' => 'השאלות שלי', 'en' => 'My questions', 'fr' => 'Mes questions', 'ar' => 'أسئلتي'],
    'my_students'    => ['he' => 'התלמידים שלי', 'en' => 'My students', 'fr' => 'Mes élèves', 'ar' => 'طلابي'],
    'manage'         => ['he' => 'ניהול', 'en' => 'Admin', 'fr' => 'Administration', 'ar' => 'إدارة'],
    'login_title'    => ['he' => 'כניסה לאתר', 'en' => 'Log in', 'fr' => 'Se connecter', 'ar' => 'الدخول إلى الموقع'],
    'login_user'     => ['he' => 'שם משתמש או אימייל', 'en' => 'Username or e-mail', 'fr' => "Nom d'utilisateur ou e-mail", 'ar' => 'اسم المستخدم أو البريد الإلكتروني'],
    'password'       => ['he' => 'סיסמה', 'en' => 'Password', 'fr' => 'Mot de passe', 'ar' => 'كلمة المرور'],
    'login_bad'      => ['he' => 'שם המשתמש או הסיסמה שגויים.', 'en' => 'Wrong username or password.', 'fr' => "Nom d'utilisateur ou mot de passe incorrect.", 'ar' => 'اسم المستخدم أو كلمة المرور غير صحيحة.'],
    'login_blocked'  => ['he' => 'החשבון הזה חסום. פנה למנהל המערכת.', 'en' => 'This account is blocked. Contact the administrator.', 'fr' => "Ce compte est bloqué. Contactez l'administrateur.", 'ar' => 'هذا الحساب محظور. توجّه إلى مدير النظام.'],
    'login_wait'     => ['he' => 'יותר מדי ניסיונות. נסה שוב בעוד דקה.', 'en' => 'Too many attempts. Try again in a minute.', 'fr' => 'Trop de tentatives. Réessayez dans une minute.', 'ar' => 'محاولات كثيرة جدًا. حاول مرة أخرى بعد دقيقة.'],
    'tutorial_btn'   => ['he' => 'סרט הדרכה', 'en' => 'Tutorial video', 'fr' => 'Vidéo de présentation', 'ar' => 'فيلم إرشادي'],
    'tutorial_soon'  => ['he' => 'סרט ההדרכה יתווסף בקרוב.', 'en' => 'The tutorial video is coming soon.', 'fr' => 'La vidéo de présentation sera bientôt disponible.', 'ar' => 'سيُضاف الفيلم الإرشادي قريبًا.'],
    'close'          => ['he' => 'סגירה', 'en' => 'Close', 'fr' => 'Fermer', 'ar' => 'إغلاق'],
    'login_needed'   => ['he' => 'צריך להיכנס לאתר כדי לראות את הדף הזה.', 'en' => 'Please log in to see this page.', 'fr' => 'Connectez-vous pour voir cette page.', 'ar' => 'يجب الدخول إلى الموقع لمشاهدة هذه الصفحة.'],
    'no_account'     => ['he' => 'אין לך חשבון? הרשמה לתלמידים', 'en' => "No account? Student sign-up", 'fr' => 'Pas de compte ? Inscription élève', 'ar' => 'ليس لديك حساب؟ تسجيل الطلاب'],
    'have_account'   => ['he' => 'כבר יש לך חשבון? כניסה', 'en' => 'Already have an account? Log in', 'fr' => 'Déjà un compte ? Connexion', 'ar' => 'لديك حساب؟ دخول'],
    'register_title' => ['he' => 'הרשמה לתלמידים', 'en' => 'Student sign-up', 'fr' => 'Inscription élève', 'ar' => 'تسجيل الطلاب'],
    'reg_note'       => ['he' => 'אחרי ההרשמה האתר יזכור באילו שאלות ביקרת, והמורה שלך יוכל לראות אותן. חשבונות של מורים נפתחים על ידי מנהל המערכת.', 'en' => 'Once you sign up, the site remembers which questions you visited, and your teacher can see them. Teacher accounts are opened by the administrator.', 'fr' => "Après l'inscription, le site garde la liste des questions que vous avez consultées, et votre professeur peut la voir. Les comptes professeurs sont ouverts par l'administrateur.", 'ar' => 'بعد التسجيل يتذكر الموقع الأسئلة التي زرتها، ويستطيع معلمك رؤيتها. حسابات المعلمين يفتحها مدير النظام.'],
    'full_name'      => ['he' => 'שם מלא', 'en' => 'Full name', 'fr' => 'Nom complet', 'ar' => 'الاسم الكامل'],
    'username'       => ['he' => 'שם משתמש', 'en' => 'Username', 'fr' => "Nom d'utilisateur", 'ar' => 'اسم المستخدم'],
    'username_help'  => ['he' => 'אותיות באנגלית, ספרות, נקודה או קו תחתון, 3–40 תווים', 'en' => 'Latin letters, digits, dot or underscore, 3–40 characters', 'fr' => 'Lettres latines, chiffres, point ou tiret bas, 3 à 40 caractères', 'ar' => 'حروف لاتينية، أرقام، نقطة أو شرطة سفلية، 3–40 حرفًا'],
    'email_opt'      => ['he' => 'אימייל (רשות)', 'en' => 'E-mail (optional)', 'fr' => 'E-mail (facultatif)', 'ar' => 'البريد الإلكتروني (اختياري)'],
    'password_new'   => ['he' => 'סיסמה (לפחות 8 תווים)', 'en' => 'Password (at least 8 characters)', 'fr' => 'Mot de passe (au moins 8 caractères)', 'ar' => 'كلمة المرور (8 أحرف على الأقل)'],
    'password2'      => ['he' => 'אימות סיסמה', 'en' => 'Repeat the password', 'fr' => 'Confirmez le mot de passe', 'ar' => 'تأكيد كلمة المرور'],
    'err_username'   => ['he' => 'שם המשתמש צריך להיות 3–40 תווים: אותיות באנגלית, ספרות, נקודה, מקף או קו תחתון.', 'en' => 'The username must be 3–40 characters: Latin letters, digits, dot, hyphen or underscore.', 'fr' => "Le nom d'utilisateur doit compter 3 à 40 caractères : lettres latines, chiffres, point, tiret ou tiret bas.", 'ar' => 'يجب أن يتكون اسم المستخدم من 3–40 حرفًا: حروف لاتينية، أرقام، نقطة، شرطة أو شرطة سفلية.'],
    'err_username_taken' => ['he' => 'שם המשתמש הזה כבר תפוס.', 'en' => 'This username is taken.', 'fr' => "Ce nom d'utilisateur est déjà pris.", 'ar' => 'اسم المستخدم هذا محجوز.'],
    'err_email'      => ['he' => 'כתובת האימייל לא תקינה.', 'en' => 'The e-mail address is not valid.', 'fr' => "L'adresse e-mail n'est pas valide.", 'ar' => 'عنوان البريد الإلكتروني غير صالح.'],
    'err_email_taken'=> ['he' => 'כתובת האימייל הזאת כבר רשומה.', 'en' => 'This e-mail address is already registered.', 'fr' => 'Cette adresse e-mail est déjà inscrite.', 'ar' => 'عنوان البريد الإلكتروني هذا مسجّل مسبقًا.'],
    'err_name'       => ['he' => 'צריך למלא שם מלא.', 'en' => 'Please enter your full name.', 'fr' => 'Indiquez votre nom complet.', 'ar' => 'يرجى كتابة الاسم الكامل.'],
    'err_password_empty' => ['he' => 'צריך להקליד סיסמה.', 'en' => 'Please type a password.', 'fr' => 'Veuillez saisir un mot de passe.', 'ar' => 'يرجى كتابة كلمة مرور.'],
    'err_password'   => ['he' => 'הסיסמה צריכה להיות באורך 8 תווים לפחות.', 'en' => 'The password must be at least 8 characters long.', 'fr' => 'Le mot de passe doit compter au moins 8 caractères.', 'ar' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.'],
    'err_password2'  => ['he' => 'שתי הסיסמאות אינן זהות.', 'en' => 'The two passwords do not match.', 'fr' => 'Les deux mots de passe ne correspondent pas.', 'ar' => 'كلمتا المرور غير متطابقتين.'],
    'err_csrf'       => ['he' => 'הטופס פג תוקף. נסה שוב.', 'en' => 'The form expired. Please try again.', 'fr' => 'Le formulaire a expiré. Réessayez.', 'ar' => 'انتهت صلاحية النموذج. حاول مرة أخرى.'],
    'err_cur_pass'   => ['he' => 'הסיסמה הנוכחית שגויה.', 'en' => 'The current password is wrong.', 'fr' => 'Le mot de passe actuel est incorrect.', 'ar' => 'كلمة المرور الحالية غير صحيحة.'],
    'role_student'   => ['he' => 'תלמיד', 'en' => 'Student', 'fr' => 'Élève', 'ar' => 'طالب'],
    'role_teacher'   => ['he' => 'מורה', 'en' => 'Teacher', 'fr' => 'Professeur', 'ar' => 'معلم'],
    'role_admin'     => ['he' => 'מנהל מערכת', 'en' => 'Administrator', 'fr' => 'Administrateur', 'ar' => 'مدير النظام'],
    'visited'        => ['he' => 'ביקרת', 'en' => 'Visited', 'fr' => 'Consultée', 'ar' => 'تمت الزيارة'],
    'visited_title'  => ['he' => 'ביקרת בשאלה %d פעמים, לאחרונה ב-%s', 'en' => 'You visited this question %d times, last on %s', 'fr' => 'Consultée %d fois, la dernière le %s', 'ar' => 'زرت هذا السؤال %d مرات، آخرها في %s'],
    'visited_before' => ['he' => 'כבר ביקרת בשאלה הזאת (%d פעמים, לאחרונה ב-%s)', 'en' => 'You have visited this question before (%d times, last on %s)', 'fr' => 'Vous avez déjà consulté cette question (%d fois, la dernière le %s)', 'ar' => 'زرت هذا السؤال من قبل (%d مرات، آخرها في %s)'],
    'my_title'       => ['he' => 'השאלות שביקרתי בהן', 'en' => 'Questions I visited', 'fr' => "Questions que j'ai consultées", 'ar' => 'الأسئلة التي زرتها'],
    'my_none'        => ['he' => 'עוד לא ביקרת באף שאלה.', 'en' => "You haven't visited any question yet.", 'fr' => "Vous n'avez encore consulté aucune question.", 'ar' => 'لم تزر أي سؤال بعد.'],
    'col_question'   => ['he' => 'קוד שאלה', 'en' => 'Question', 'fr' => 'Question', 'ar' => 'سؤال'],
    'col_subject'    => ['he' => 'נושא', 'en' => 'Topic', 'fr' => 'Thème', 'ar' => 'موضوع'],
    'col_visits'     => ['he' => 'כניסות', 'en' => 'Visits', 'fr' => 'Visites', 'ar' => 'زيارات'],
    'col_first'      => ['he' => 'כניסה ראשונה', 'en' => 'First visit', 'fr' => 'Première visite', 'ar' => 'الزيارة الأولى'],
    'col_last'       => ['he' => 'כניסה אחרונה', 'en' => 'Last visit', 'fr' => 'Dernière visite', 'ar' => 'الزيارة الأخيرة'],
    'col_when'       => ['he' => 'תאריך ושעה', 'en' => 'Date and time', 'fr' => 'Date et heure', 'ar' => 'التاريخ والساعة'],
    'col_student'    => ['he' => 'תלמיד', 'en' => 'Student', 'fr' => 'Élève', 'ar' => 'طالب'],
    'col_nq'         => ['he' => 'שאלות שביקר', 'en' => 'Questions visited', 'fr' => 'Questions consultées', 'ar' => 'أسئلة تمت زيارتها'],
    'my_teachers'    => ['he' => 'המורים שלי', 'en' => 'My teachers', 'fr' => 'Mes professeurs', 'ar' => 'معلّميّ'],
    'my_account'     => ['he' => 'החשבון שלי', 'en' => 'My account', 'fr' => 'Mon compte', 'ar' => 'حسابي'],
    'change_pass'    => ['he' => 'שינוי סיסמה', 'en' => 'Change password', 'fr' => 'Changer le mot de passe', 'ar' => 'تغيير كلمة المرور'],
    'cur_pass'       => ['he' => 'הסיסמה הנוכחית', 'en' => 'Current password', 'fr' => 'Mot de passe actuel', 'ar' => 'كلمة المرور الحالية'],
    'save'           => ['he' => 'שמירה', 'en' => 'Save', 'fr' => 'Enregistrer', 'ar' => 'حفظ'],
    'pass_changed'   => ['he' => 'הסיסמה שונתה.', 'en' => 'Your password was changed.', 'fr' => 'Le mot de passe a été modifié.', 'ar' => 'تم تغيير كلمة المرور.'],
    'n_visits_total' => ['he' => '%d כניסות ל-%d שאלות', 'en' => '%d visits to %d questions', 'fr' => '%d visites, %d questions', 'ar' => '%d زيارات لـ %d أسئلة'],
    'add_student'    => ['he' => 'הוספת תלמיד לפי שם המשתמש שלו', 'en' => 'Add a student by username', 'fr' => "Ajouter un élève par son nom d'utilisateur", 'ar' => 'إضافة طالب حسب اسم المستخدم'],
    'add_btn'        => ['he' => 'הוספה', 'en' => 'Add', 'fr' => 'Ajouter', 'ar' => 'إضافة'],
    'remove'         => ['he' => 'הסרה', 'en' => 'Remove', 'fr' => 'Retirer', 'ar' => 'إزالة'],
    'student_added'  => ['he' => 'התלמיד נוסף לרשימה.', 'en' => 'The student was added.', 'fr' => "L'élève a été ajouté.", 'ar' => 'تمت إضافة الطالب.'],
    'student_removed'=> ['he' => 'התלמיד הוסר מהרשימה.', 'en' => 'The student was removed.', 'fr' => "L'élève a été retiré.", 'ar' => 'تمت إزالة الطالب.'],
    'student_not_found' => ['he' => 'לא נמצא תלמיד עם שם המשתמש הזה.', 'en' => 'No student has this username.', 'fr' => "Aucun élève n'a ce nom d'utilisateur.", 'ar' => 'لا يوجد طالب بهذا الاسم.'],
    'no_students'    => ['he' => 'עוד אין תלמידים ברשימה שלך. הוסף תלמיד לפי שם המשתמש שבחר בהרשמה.', 'en' => 'No students yet. Add a student by the username they chose when signing up.', 'fr' => "Aucun élève pour l'instant. Ajoutez un élève avec le nom d'utilisateur choisi à l'inscription.", 'ar' => 'لا يوجد طلاب بعد. أضف طالبًا حسب اسم المستخدم الذي اختاره عند التسجيل.'],
    'all_students'   => ['he' => 'כל התלמידים באתר', 'en' => 'All students', 'fr' => 'Tous les élèves', 'ar' => 'كل الطلاب'],
    'visits_of'      => ['he' => 'השאלות ש%s ביקר/ה בהן', 'en' => 'Questions visited by %s', 'fr' => 'Questions consultées par %s', 'ar' => 'الأسئلة التي زارها %s'],
    'by_question'    => ['he' => 'לפי שאלה', 'en' => 'By question', 'fr' => 'Par question', 'ar' => 'حسب السؤال'],
    'all_entries'    => ['he' => 'כל הכניסות', 'en' => 'All visits', 'fr' => 'Toutes les visites', 'ar' => 'كل الزيارات'],
    'back_students'  => ['he' => '→ חזרה לרשימת התלמידים', 'en' => '← Back to the students', 'fr' => '← Retour aux élèves', 'ar' => '→ عودة إلى الطلاب'],
    'find_exam'      => ['he' => 'חיפוש לפי שאלון', 'en' => 'Find by exam', 'fr' => 'Chercher par épreuve', 'ar' => 'بحث حسب النموذج'],
    'search_btn'     => ['he' => 'חיפוש', 'en' => 'Search', 'fr' => 'Chercher', 'ar' => 'بحث'],
    'exams_title'    => ['he' => 'בחינות הבגרות', 'en' => 'Bagrut exams', 'fr' => 'Épreuves du bagrout', 'ar' => 'امتحانات البجروت'],
    'exams_lead'     => ['he' => 'לחיצה על בחינה פותחת את השאלות שבה.', 'en' => 'Click an exam to see its questions.', 'fr' => 'Cliquez sur une épreuve pour voir ses questions.', 'ar' => 'انقر على امتحان لمشاهدة أسئلته.'],
    'col_year'       => ['he' => 'שנה', 'en' => 'Year', 'fr' => 'Année', 'ar' => 'السنة'],
    'col_exam'       => ['he' => 'שם הבחינה', 'en' => 'Exam', 'fr' => 'Épreuve', 'ar' => 'اسم الامتحان'],
    'exams_total'    => ['he' => 'סה"כ', 'en' => 'Total', 'fr' => 'Total', 'ar' => 'المجموع'],
    'n_exams'        => ['he' => '%d בחינות', 'en' => '%d exams', 'fr' => '%d épreuves', 'ar' => '%d امتحانات'],
    'no_exams'       => ['he' => 'אין בחינות להצגה.', 'en' => 'No exams to show.', 'fr' => 'Aucune épreuve à afficher.', 'ar' => 'لا توجد امتحانات للعرض.'],
    'exam_q_no'      => ['he' => 'שאלה %d', 'en' => 'Question %d', 'fr' => 'Question %d', 'ar' => 'سؤال %d'],
    'find_q'         => ['he' => 'חיפוש שאלה לפי קוד', 'en' => 'Find a question by code', 'fr' => 'Trouver une question par son code', 'ar' => 'البحث عن سؤال حسب الرمز'],
    'pdf_btn'        => ['he' => 'קובץ PDF', 'en' => 'PDF file', 'fr' => 'Fichier PDF', 'ar' => 'ملف PDF'],
    'video_btn'      => ['he' => 'שיעור וידאו', 'en' => 'Video lesson', 'fr' => 'Leçon vidéo', 'ar' => 'درس فيديو'],
    'version_x'      => ['he' => 'גרסה %s', 'en' => 'Version %s', 'fr' => 'Version %s', 'ar' => 'إصدار %s'],
    'feedback'       => ['he' => 'משוב ותיקונים', 'en' => 'Feedback & corrections', 'fr' => 'Remarques et corrections', 'ar' => 'ملاحظات وتصحيحات'],
    'feedback_subject' => ['he' => 'משוב / תיקון', 'en' => 'Feedback / correction', 'fr' => 'Remarque / correction', 'ar' => 'ملاحظة / تصحيح'],
    'feedback_body'  => ['he' => 'הדף:', 'en' => 'Page:', 'fr' => 'Page :', 'ar' => 'الصفحة:'],
    'built_with'     => ['he' => 'פותח באמצעות Claude Code', 'en' => 'Built with Claude Code', 'fr' => 'Développé avec Claude Code', 'ar' => 'طُوّر بواسطة Claude Code'],
    'search_title'   => ['he' => 'חיפוש נושא', 'en' => 'Topic search', 'fr' => 'Recherche de sujet', 'ar' => 'البحث عن موضوع'],
    'search_label'   => ['he' => 'חיפוש נושא', 'en' => 'Search a topic', 'fr' => 'Chercher un sujet', 'ar' => 'البحث عن موضوع'],
    'search_ph'      => ['he' => 'למשל: משוואת משיק', 'en' => 'e.g. tangent line', 'fr' => 'p. ex. tangente', 'ar' => 'مثلًا: معادلة المماس'],
    'search_btn'     => ['he' => 'חפש', 'en' => 'Search', 'fr' => 'Chercher', 'ar' => 'بحث'],
    'voice_btn'      => ['he' => 'חיפוש קולי', 'en' => 'Voice search', 'fr' => 'Recherche vocale', 'ar' => 'بحث صوتي'],
    'voice_listening'=> ['he' => 'מקשיב… דברו עכשיו', 'en' => 'Listening… speak now', 'fr' => 'À l’écoute… parlez', 'ar' => 'أستمع… تكلّم الآن'],
    'voice_nothing'  => ['he' => 'לא נשמע דיבור, נסו שוב', 'en' => 'No speech heard, try again', 'fr' => 'Rien entendu, réessayez', 'ar' => 'لم يُسمع كلام، حاول مرة أخرى'],
    'voice_denied'   => ['he' => 'אין הרשאה למיקרופון. אפשרו אותה בדפדפן', 'en' => 'No microphone permission. Allow it in the browser', 'fr' => 'Micro non autorisé. Autorisez-le dans le navigateur', 'ar' => 'لا يوجد إذن لاستخدام الميكروفون. اسمح به في المتصفح'],
    'search_none'    => ['he' => 'לא נמצאו נושאים עבור "%s".', 'en' => 'No topics found for "%s".', 'fr' => 'Aucun sujet trouvé pour « %s ».', 'ar' => 'لم يتم العثور على مواضيع لـ "%s".'],
    'search_subjects'=> ['he' => 'נושאים (%d)', 'en' => 'Topics (%d)', 'fr' => 'Sujets (%d)', 'ar' => 'مواضيع (%d)'],
    'search_theory'  => ['he' => 'רקע תאורטי (%d)', 'en' => 'Theory (%d)', 'fr' => 'Théorie (%d)', 'ar' => 'خلفية نظرية (%d)'],
    'search_nq'      => ['he' => '%d שאלות', 'en' => '%d questions', 'fr' => '%d questions', 'ar' => '%d أسئلة'],
    'q_source'       => ['he' => 'מקור שאלה', 'en' => 'Question source', 'fr' => 'Source de la question', 'ar' => 'مصدر السؤال'],
    'q_id'           => ['he' => 'קוד שאלה', 'en' => 'Question code', 'fr' => 'Code', 'ar' => 'رمز السؤال'],
    'q_lang'         => ['he' => 'שפה', 'en' => 'Language', 'fr' => 'Langue', 'ar' => 'اللغة'],
    'go'             => ['he' => 'הצג', 'en' => 'Show', 'fr' => 'Afficher', 'ar' => 'عرض'],
    'id_label'       => ['he' => 'קוד שאלה = %s', 'en' => 'Question code = %s', 'fr' => 'Code de la question = %s', 'ar' => 'رمز السؤال = %s'],
    'site_tagline'   => ['he' => 'שאלות בגרות במתמטיקה עם פתרונות מודרכים', 'en' => 'Math exam questions with guided solutions', 'fr' => "Questions d'examen de maths avec corrigés guidés", 'ar' => 'أسئلة بجروت في الرياضيات مع حلول موجِّهة'],
    'subjects'       => ['he' => 'נושאים', 'en' => 'Subjects', 'fr' => 'Thèmes', 'ar' => 'مواضيع'],
    'hero_title'     => ['he' => 'שאלות בגרות במתמטיקה, עם פתרון שמלמד', 'en' => 'Math exam questions, with solutions that teach', 'fr' => "Des questions d'examen, avec des corrigés qui expliquent", 'ar' => 'أسئلة بجروت في الرياضيات، مع حلول تعلّم'],
    'hero_text'      => ['he' => 'בחרו מסלול ונושא. לכל שאלה: תשובה סופית, הכוונה ברמזים, ופתרון מלא שלב אחר שלב עם קישור לרקע התאורטי.',
                         'en' => 'Pick a track and a subject. Every question has a final answer, step-by-step hints, and a full worked solution linked to the theory behind it.',
                         'fr' => "Choisissez un programme et un thème. Chaque question propose la réponse finale, des indices et un corrigé détaillé relié au cours.", 'ar' => 'اختر مسارًا وموضوعًا. لكل سؤال جواب نهائي، وتلميحات خطوة بخطوة، وحل مفصّل كامل مرتبط بالخلفية النظرية.'],
    'n_subjects'     => ['he' => '%d נושאים', 'en' => '%d subjects', 'fr' => '%d thèmes', 'ar' => '%d مواضيع'],
    'n_questions'    => ['he' => '%d שאלות', 'en' => '%d questions', 'fr' => '%d questions', 'ar' => '%d أسئلة'],
    'one_question'   => ['he' => 'שאלה אחת', 'en' => '1 question', 'fr' => '1 question', 'ar' => 'سؤال واحد'],
    'questions_word' => ['he' => 'שאלות', 'en' => 'questions', 'fr' => 'questions', 'ar' => 'أسئلة'],
    'units'          => ['he' => '%d יח"ל', 'en' => '%d units', 'fr' => '%d unités', 'ar' => '%d وحدات'],
    'coming_soon'    => ['he' => 'השאלות בנושא הזה עוד לא הועברו לאתר החדש.', 'en' => "This subject's questions have not been moved to the new site yet.", 'fr' => "Les questions de ce thème n'ont pas encore été transférées.", 'ar' => 'لم تُنقل أسئلة هذا الموضوع إلى الموقع الجديد بعد.'],
    'col_theory'     => ['he' => 'רקע תיאורטי', 'en' => 'Theory', 'fr' => 'Cours', 'ar' => 'خلفية نظرية'],
    'theory_of'      => ['he' => 'רקע תיאורטי: %s', 'en' => 'Theory: %s', 'fr' => 'Cours : %s', 'ar' => 'خلفية نظرية: %s'],
    'theory_lead'    => ['he' => 'ההגדרות, המשפטים והשיטות של הנושא. לחצו על פריט כדי לפתוח אותו.', 'en' => 'The definitions, theorems and methods of this subject. Click an item to open it.', 'fr' => 'Définitions, théorèmes et méthodes de ce thème. Cliquez sur un élément pour l\'ouvrir.', 'ar' => 'التعريفات والنظريات والطرق في هذا الموضوع. انقر على بند لفتحه.'],
    'theory_pick'    => ['he' => 'בחרו את סוג הרקע התיאורטי כדי לפתוח את הרשימה.', 'en' => 'Choose a category to open its list.', 'fr' => 'Choisissez une catégorie pour ouvrir sa liste.', 'ar' => 'اختر فئة لفتح قائمتها.'],
    'questions_using'=> ['he' => 'שאלות שנעזרות ב: %s', 'en' => 'Questions that use: %s', 'fr' => 'Questions qui utilisent : %s', 'ar' => 'أسئلة تستخدم: %s'],
    'not_translated' => ['he' => 'השאלה הזו עדיין לא תורגמה.', 'en' => 'This question has not been translated into English yet.', 'fr' => 'Cette question n\'est pas encore traduite.', 'ar' => 'لم يُترجم هذا السؤال إلى العربية بعد.'],
    'not_translated_th' => ['he' => 'הפריט הזה עדיין לא תורגם.', 'en' => 'This item has not been translated into English yet.', 'fr' => 'Cet élément n\'est pas encore traduit.', 'ar' => 'لم يُترجم هذا البند إلى العربية بعد.'],
    'read_original'  => ['he' => 'לקריאה בעברית', 'en' => 'Read it in Hebrew', 'fr' => 'La lire en hébreu', 'ar' => 'اقرأه بالعبرية'],
    'machine_translation' => ['he' => 'תרגום אוטומטי', 'en' => 'Translated from Hebrew; not yet reviewed', 'fr' => 'Traduit de l\'hébreu, non relu', 'ar' => 'مترجم من العبرية؛ لم تتم مراجعته بعد'],
    'grp_definition' => ['he' => 'הגדרות', 'en' => 'Definitions', 'fr' => 'Définitions', 'ar' => 'تعريفات'],
    'grp_theorem'    => ['he' => 'משפטים', 'en' => 'Theorems', 'fr' => 'Théorèmes', 'ar' => 'نظريات'],
    'grp_method'     => ['he' => 'עקרונות ושיטות', 'en' => 'Principles and methods', 'fr' => 'Principes et méthodes', 'ar' => 'مبادئ وطرق'],
    'grp_other'      => ['he' => 'תוצאות ידועות', 'en' => 'Known results', 'fr' => 'Résultats connus', 'ar' => 'نتائج معروفة'],
    'open_page'      => ['he' => 'לדף המלא', 'en' => 'Open full page', 'fr' => 'Page complète', 'ar' => 'فتح الصفحة كاملة'],
    'theory_none'    => ['he' => 'עדיין אין רקע תיאורטי לנושא הזה.', 'en' => 'No theory for this subject yet.', 'fr' => 'Pas encore de cours pour ce thème.', 'ar' => 'لا توجد خلفية نظرية لهذا الموضوع بعد.'],
    'col_subject'    => ['he' => 'נושא', 'en' => 'Subject', 'fr' => 'Thème', 'ar' => 'موضوع'],
    'no_questions_yet'=> ['he' => 'עוד אין שאלות', 'en' => 'No questions yet', 'fr' => 'Pas encore de questions', 'ar' => 'لا توجد أسئلة بعد'],
    'subtopics'      => ['he' => 'תתי־נושאים', 'en' => 'Subtopics', 'fr' => 'Sous-thèmes', 'ar' => 'مواضيع فرعية'],
    'question_x_of_y'=> ['he' => 'שאלה %d מתוך %d', 'en' => 'Question %d of %d', 'fr' => 'Question %d sur %d', 'ar' => 'سؤال %d من %d'],
    'exam_q'         => ['he' => 'בגרות %s, שאלה %d', 'en' => 'Bagrut %s, question %d', 'fr' => 'Bagrout %s, question %d', 'ar' => 'بجروت %s، سؤال %d'],
    'tab_question'   => ['he' => 'השאלה', 'en' => 'Question', 'fr' => 'Énoncé', 'ar' => 'السؤال'],
    'tab_hints'      => ['he' => 'רקע תיאורטי', 'en' => 'Theory', 'fr' => 'Rappels de cours', 'ar' => 'خلفية نظرية'],
    'tab_guide'      => ['he' => 'הכוונה', 'en' => 'Guidance', 'fr' => 'Pistes', 'ar' => 'توجيه'],
    'tab_solution'   => ['he' => 'פתרון מפורט', 'en' => 'Detailed solution', 'fr' => 'Corrigé détaillé', 'ar' => 'حل مفصّل'],
    'tab_answer'     => ['he' => 'תשובה סופית', 'en' => 'Final answer', 'fr' => 'Réponse finale', 'ar' => 'جواب نهائي'],
    'hints_lead'     => ['he' => 'ההגדרות, המשפטים והשיטות שהשאלה נשענת עליהם. לחצו על נושא כדי לפתוח אותו.', 'en' => 'Ideas and theorems worth knowing before you start. Click one to open it.', 'fr' => 'Notions et théorèmes utiles avant de commencer. Cliquez pour les ouvrir.', 'ar' => 'أفكار ونظريات من المفيد معرفتها قبل البدء. انقر على أحدها لفتحه.'],
    'part_n'         => ['he' => 'חלק %d', 'en' => 'Part %d', 'fr' => 'Partie %d', 'ar' => 'بند %d'],
    'final_answer'   => ['he' => 'תשובה סופית', 'en' => 'Final answer', 'fr' => 'Réponse finale', 'ar' => 'جواب نهائي'],
    'hints'          => ['he' => 'הכוונה לפתרון', 'en' => 'Hints', 'fr' => 'Indices', 'ar' => 'تلميحات'],
    'n_hints'        => ['he' => '(%d רמזים)', 'en' => '(%d hints)', 'fr' => '(%d indices)', 'ar' => '(%d تلميحات)'],
    'full_solution'  => ['he' => 'פתרון מלא שלב אחר שלב', 'en' => 'Full step-by-step solution', 'fr' => 'Corrigé détaillé, étape par étape', 'ar' => 'حل كامل خطوة بخطوة'],
    'n_steps'        => ['he' => '(%d שלבים)', 'en' => '(%d steps)', 'fr' => '(%d étapes)', 'ar' => '(%d خطوات)'],
    'theory_for_q'   => ['he' => 'רקע תאורטי לשאלה', 'en' => 'Theory for this question', 'fr' => 'Rappels de cours pour cette question', 'ar' => 'خلفية نظرية لهذا السؤال'],
    'theory'         => ['he' => 'רקע תאורטי', 'en' => 'Theory', 'fr' => 'Rappels de cours', 'ar' => 'خلفية نظرية'],
    'prev_q'         => ['he' => '→ השאלה הקודמת', 'en' => '← Previous question', 'fr' => '← Question précédente', 'ar' => '→ السؤال السابق'],
    'next_q'         => ['he' => 'השאלה הבאה ←', 'en' => 'Next question →', 'fr' => 'Question suivante →', 'ar' => 'السؤال التالي ←'],
    'used_in'        => ['he' => 'שאלות שמשתמשות בפריט הזה', 'en' => 'Questions that use this item', 'fr' => 'Questions qui utilisent cet élément', 'ar' => 'أسئلة تستخدم هذا البند'],
    'back'           => ['he' => '→ חזרה', 'en' => '← Back', 'fr' => '← Retour', 'ar' => '→ عودة'],
    'not_found'      => ['he' => 'הדף לא נמצא', 'en' => 'Page not found', 'fr' => 'Page introuvable', 'ar' => 'الصفحة غير موجودة'],
    'to_home'        => ['he' => 'חזרה לעמוד הראשי', 'en' => 'Back to the home page', 'fr' => "Retour à l'accueil", 'ar' => 'العودة إلى الصفحة الرئيسية'],
    'content_note'   => ['he' => 'תוכן השאלות כתוב בעברית', 'en' => 'Questions are written in Hebrew', 'fr' => 'Les questions sont rédigées en hébreu', 'ar' => 'الأسئلة مكتوبة بالعبرية'],
    'content_note_fr'=> ['he' => 'תוכן השאלות כתוב בצרפתית', 'en' => 'Questions are written in French', 'fr' => 'Les questions sont rédigées en français', 'ar' => 'الأسئلة مكتوبة بالفرنسية'],
    'kind_definition'=> ['he' => 'הגדרה', 'en' => 'Definition', 'fr' => 'Définition', 'ar' => 'تعريف'],
    'kind_theorem'   => ['he' => 'משפט', 'en' => 'Theorem', 'fr' => 'Théorème', 'ar' => 'نظرية'],
    'kind_method'    => ['he' => 'שיטה', 'en' => 'Method', 'fr' => 'Méthode', 'ar' => 'طريقة'],
    'kind_other'     => ['he' => 'רקע', 'en' => 'Background', 'fr' => 'Rappel', 'ar' => 'خلفية'],
    'footer'         => ['he' => 'JustAsk · שאלות בגרות במתמטיקה עם פתרונות מודרכים', 'en' => 'JustAsk · Math exam questions with guided solutions', 'fr' => "JustAsk · Questions d'examen de maths avec corrigés guidés", 'ar' => 'JustAsk · أسئلة بجروت في الرياضيات مع حلول موجِّهة'],
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
               'קיץ מועד ב' => ['Summer (2nd sitting)', 'été (2e session)'],
               'קיץ מועד מיוחד' => ['Summer (special sitting)', 'été (session spéciale)']];
    if (lang() === 'he' || empty($e['year'])) return $e['exam_title'] ?? '';
    if (lang() === 'ar') {
        $ar = ['ינואר' => 'كانون الثاني', 'פברואר' => 'شباط', 'מאי' => 'أيار', 'יוני' => 'حزيران', 'יולי' => 'تموز', 'אוגוסט' => 'آب',
               'דצמבר' => 'كانون الأول', 'קיץ' => 'صيف', 'חורף' => 'شتاء', 'קיץ מועד א' => 'صيف (موعد أ)', 'קיץ מועד ב' => 'صيف (موعد ب)',
               'קיץ מועד מיוחד' => 'صيف (موعد خاص)'];
        $s = $e['session'] ?? '';
        $u = $e['units'] ?? null;
        return trim(($u ? units_label($u) . '، ' : '') . ($ar[$s] ?? $s) . ' ' . $e['year']);
    }
    $s = $e['session'] ?? '';
    $i = lang() === 'en' ? 0 : 1;
    $sess = $months[$s][$i] ?? $s;
    $u = $e['units'] ?? null;
    return trim(($u ? units_label($u) . ', ' : '') . $sess . ' ' . $e['year']);
}
