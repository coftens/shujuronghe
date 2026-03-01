<?php
date_default_timezone_set('Asia/Shanghai');

$config = require __DIR__ . '/../config/config.php';
if ($config['debug'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', $config['session_lifetime'] ?? 7200);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

