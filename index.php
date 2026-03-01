<?php
/**
 * 入口文件 - 路由到登录或仪表盘
 */
require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/login.php');
}
exit;
