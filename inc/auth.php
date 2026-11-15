<?php
// Users, login and the record of question visits.
// Roles: student (visits are recorded), teacher (sees the visits of the students linked to them), admin (manages users).
require_once __DIR__ . '/db.php';

const ROLES = ['student', 'teacher', 'admin'];
date_default_timezone_set(cfg('timezone') ?: 'Asia/Jerusalem');

function auth_session() {
    if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) return;
    session_name('justask_sid');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
                               'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}
auth_session();

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">'; }
function csrf_ok() { return hash_equals(csrf_token(), (string)($_POST['csrf'] ?? '')); }

/** Create the user tables (db/users.sql) when they are missing, e.g. on a site installed before the login system. */
function user_tables() {
    static $ok = false;
    if ($ok) return;
    try { db()->query('SELECT 1 FROM question_visits LIMIT 1'); }
    catch (PDOException $e) {
        $sql = preg_replace('/^--.*$/m', '', file_get_contents(dirname(__DIR__) . '/db/users.sql'));
        foreach (preg_split('/;\s*\n/', $sql) as $st) { if (trim($st) !== '') db()->exec($st); }
    }
    $ok = true;
}

/** The logged-in user (array with id, username, full_name, role, …) or null. */
function current_user() {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        user_tables();
        $u = q("SELECT id, username, email, full_name, role, is_active FROM users WHERE id = ? AND is_active = 1", [(int)$_SESSION['uid']], true);
        if (!$u) unset($_SESSION['uid']);
    }
    return $u;
}
function user_role() { $u = current_user(); return $u ? $u['role'] : null; }
function is_role(...$roles) { return in_array(user_role(), $roles, true); }

/** Log in with a username or an e-mail address. Returns '' on success, or the key of an error message. */
function login_attempt($name, $password) {
    user_tables();
    $now = time();
    $f = $_SESSION['login_fail'] ?? ['n' => 0, 't' => 0];
    if ($f['n'] >= 5 && $now - $f['t'] < 60) return 'login_wait';
    $u = q("SELECT * FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)", [trim($name), trim($name)], true);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        $_SESSION['login_fail'] = ['n' => ($now - $f['t'] < 600 ? $f['n'] : 0) + 1, 't' => $now];
        usleep(400000);
        return 'login_bad';
    }
    if (!$u['is_active']) return 'login_blocked';
    unset($_SESSION['login_fail']);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    q("UPDATE users SET last_login_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $u['id']]);
    return '';
}

function logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Check the fields of a new user. Returns [error keys]. */
function user_check($username, $email, $full_name, $password) {
    $err = [];
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,40}$/', $username)) $err[] = 'err_username';
    elseif (q("SELECT 1 FROM users WHERE username = ?", [$username], true)) $err[] = 'err_username_taken';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'err_email';
    elseif ($email !== '' && q("SELECT 1 FROM users WHERE email = ?", [$email], true)) $err[] = 'err_email_taken';
    if (mb_strlen(trim($full_name)) < 2) $err[] = 'err_name';
    if (strlen($password) < 8) $err[] = 'err_password';
    return $err;
}

function user_create($username, $email, $full_name, $password, $role = 'student') {
    user_tables();
    q("INSERT INTO users (username, email, full_name, password_hash, role) VALUES (?, ?, ?, ?, ?)",
      [$username, $email !== '' ? $email : null, trim($full_name), password_hash($password, PASSWORD_DEFAULT), in_array($role, ROLES, true) ? $role : 'student']);
    return (int)db()->lastInsertId();
}

/* ---- question visits ---------------------------------------------------------------- */

/** Record that the logged-in student opened a question. */
function record_visit($question_id) {
    $u = current_user();
    if (!$u || $u['role'] !== 'student') return;
    q("INSERT INTO question_visits (user_id, question_id, visited_at) VALUES (?, ?, ?)", [$u['id'], (int)$question_id, date('Y-m-d H:i:s')]);
}

/** [question_id => ['n' => visits, 'first' => datetime, 'last' => datetime]] for one student (default: the logged-in student). */
function visits_of(?array $question_ids = null, $user_id = null) {
    if ($user_id === null) {
        $u = current_user();
        if (!$u || $u['role'] !== 'student') return [];
        $user_id = $u['id'];
    }
    user_tables();
    $sql = "SELECT question_id, COUNT(*) AS n, MIN(visited_at) AS first, MAX(visited_at) AS last FROM question_visits WHERE user_id = ?";
    if ($question_ids !== null) {
        if (!$question_ids) return [];
        $sql .= " AND question_id IN (" . implode(',', array_map('intval', $question_ids)) . ")";
    }
    $out = [];
    foreach (q($sql . " GROUP BY question_id", [(int)$user_id]) as $r) $out[(int)$r['question_id']] = $r;
    return $out;
}

/** May the logged-in user see this student's visits? (the student, a linked teacher, or an admin) */
function can_see_student($student_id) {
    $u = current_user();
    if (!$u) return false;
    if ($u['role'] === 'admin' || (int)$u['id'] === (int)$student_id) return true;
    return $u['role'] === 'teacher' && (bool)q("SELECT 1 FROM teacher_students WHERE teacher_id = ? AND student_id = ?", [$u['id'], (int)$student_id], true);
}

function fmt_dt($dt, $with_time = true) {
    if (!$dt) return '';
    $t = strtotime($dt);
    return date($with_time ? 'd/m/Y H:i' : 'd/m/Y', $t);
}

/** A safe local return address (?next=question.php?id=5); anything else falls back to the home page. */
function safe_next($next, $fallback = 'index.php') {
    $next = (string)$next;
    return preg_match('~^(admin/(docs/)?)?[a-z_]+\.php(\?[^\s<>"\']*)?$~', $next) ? $next : $fallback;
}
