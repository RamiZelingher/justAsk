<?php
// Shared code for the development area: access check, CSRF token, page frame.
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/i18n.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function is_local_request() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($ip, ['127.0.0.1', '::1'], true);
}

/** Access: with no admin_password in config.php the area opens only from this computer; with one, a login is required. */
function admin_guard() {
    $pw = (string)cfg('admin_password');
    if ($pw === '') {
        if (is_local_request()) return;
        http_response_code(403);
        admin_header('אין גישה');
        echo '<div class="panel">אזור הפיתוח פתוח רק מהמחשב שעליו רץ האתר. כדי לפתוח אותו מבחוץ, הגדר סיסמה ב-<code>config.php</code> (<code>admin_password</code>).</div>';
        admin_footer(); exit;
    }
    if (!empty($_SESSION['admin_ok'])) return;
    $err = '';
    if (($_POST['admin_login'] ?? '') !== '') {
        if (hash_equals($pw, (string)($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin_ok'] = true;
            header('Location: ' . $_SERVER['REQUEST_URI']); exit;
        }
        $err = 'סיסמה שגויה.';
    }
    admin_header('כניסה');
    echo '<form method="post" class="panel form narrow"><h2>כניסה לאזור הפיתוח</h2>'
       . ($err ? '<p class="msg err">' . h($err) . '</p>' : '')
       . '<label>סיסמה<input type="password" name="password" autofocus></label>'
       . '<button class="btn" name="admin_login" value="1">כניסה</button></form>';
    admin_footer(); exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_ok() { return hash_equals(csrf_token(), (string)($_POST['csrf'] ?? '')); }

/** Relative path from the current page to the admin folder ('' in admin/, '../' in admin/docs/). */
function admin_base() {
    $here = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
    return ($here && $here !== realpath(__DIR__)) ? '../' : '';
}

function admin_header($title) {
    $b = admin_base();
    header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= h($title) ?> · פיתוח · JustAsk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $b ?>../style.css?v=<?= @filemtime(dirname(__DIR__) . '/style.css') ?>">
<link rel="stylesheet" href="<?= $b ?>admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?>">
</head>
<body class="admin">
<header class="site-header"><div class="wrap">
  <a class="brand" href="<?= $b ?>index.php"><span class="brand-mark">∑</span><span>JustAsk<small>אזור פיתוח</small></span></a>
  <nav><a href="<?= $b ?>index.php">כל הפונקציות</a><a href="<?= $b ?>docs/index.php">תיעוד</a><a href="<?= $b ?>../index.php?lang=he">לאתר ←</a></nav>
</div></header>
<main class="wrap">
<?php }

require_once __DIR__ . '/../inc/footer_bar.php';

function admin_footer() { ?>
</main>
<footer><div class="wrap">אזור הפיתוח אינו מוצג באתר הציבורי<?php if (function_exists('site_footer_bar')) site_footer_bar(); ?></div></footer>
</body>
</html>
<?php }

set_exception_handler(function ($e) {
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    echo "<!DOCTYPE html><html lang='he' dir='rtl'><meta charset='utf-8'><body style='font-family:Arial;padding:40px;line-height:1.7'>"
       . "<h2>שגיאה</h2><p>אם מסד הנתונים עוד לא הותקן, פתח את <a href='../install.php'>install.php</a>.</p>"
       . "<p style='color:#888;direction:ltr;text-align:left'>" . h($e->getMessage()) . "</p></body></html>";
});
