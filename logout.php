<?php
require __DIR__ . '/inc/layout.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) logout();
header('Location: index.php');
