<?php
function cfg($key) {
    static $c = null;
    if ($c === null) { $c = require __DIR__ . '/../config.php'; }
    return $c[$key] ?? null;
}

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        if (cfg('dsn_override')) {
            $pdo = new PDO(cfg('dsn_override'), null, null, $opts);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('db_host'), cfg('db_port'), cfg('db_name'));
            $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), $opts);
        }
    }
    return $pdo;
}

/** Run a query; returns all rows, or the first row (or null) when $one is true. */
function q($sql, array $args = [], $one = false) {
    $st = db()->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();
    return $one ? ($rows ? $rows[0] : null) : $rows;
}
