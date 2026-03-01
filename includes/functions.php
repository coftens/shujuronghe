<?php
/**
 * 公共函数库
 */

/**
 * 获取数据库实例
 */
function db() {
    return Database::getInstance();
}

/**
 * JSON响应
 */
function jsonResponse($code = 200, $msg = 'success', $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['code' => $code, 'msg' => $msg];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 获取GET参数
 */
function input($key, $default = null) {
    return isset($_GET[$key]) ? trim($_GET[$key]) : (isset($_POST[$key]) ? trim($_POST[$key]) : $default);
}

/**
 * 获取JSON请求体
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

/**
 * 安全输出HTML
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 格式化文件大小
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * 格式化KB为人类可读
 */
function formatKB($kb, $precision = 2) {
    return formatBytes($kb * 1024, $precision);
}

/**
 * 时间格式化（多久前）
 */
function timeAgo($datetime) {
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 0) return '刚刚';
    if ($diff < 60) return $diff . '秒前';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    return date('Y-m-d H:i', $timestamp);
}

/**
 * 生成随机字符串
 */
function generateKey($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * 获取客户端IP
 */
function getClientIp() {
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            return trim($ip);
        }
    }
    return '127.0.0.1';
}

/**
 * 记录操作日志
 */
function logOperation($action, $target = '', $detail = '') {
    $userId = $_SESSION['user_id'] ?? 0;
    $ip = getClientIp();
    db()->execute(
        "INSERT INTO operation_logs (user_id, action, target, detail, ip) VALUES (?, ?, ?, ?, ?)",
        [$userId, $action, $target, $detail, $ip]
    );
}

/**
 * 获取系统设置
 */
function getSetting($key, $default = '') {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    
    $val = db()->fetchColumn("SELECT value FROM settings WHERE key_name = ?", [$key]);
    $cache[$key] = $val !== false ? $val : $default;
    return $cache[$key];
}

/**
 * 更新系统设置
 */
function setSetting($key, $value) {
    db()->execute(
        "INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)",
        [$key, $value]
    );
}

/**
 * 创建站内通知
 */
function createNotification($userId, $title, $content, $type = 'alert', $relatedId = null) {
    db()->execute(
        "INSERT INTO notifications (user_id, type, title, content, related_id) VALUES (?, ?, ?, ?, ?)",
        [$userId, $type, $title, $content, $relatedId]
    );
}

/**
 * 给所有管理员创建通知
 */
function notifyAllAdmins($title, $content, $type = 'alert', $relatedId = null) {
    $admins = db()->fetchAll("SELECT id FROM users WHERE role = 'admin'");
    foreach ($admins as $admin) {
        createNotification($admin['id'], $title, $content, $type, $relatedId);
    }
}

/**
 * 获取未读通知数
 */
function getUnreadCount($userId) {
    return db()->fetchColumn(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
        [$userId]
    );
}

/**
 * 判断服务器是否在线（3分钟内有心跳）
 */
function isServerOnline($lastHeartbeat) {
    if (!$lastHeartbeat) return false;
    return (time() - strtotime($lastHeartbeat)) < 180;
}

/**
 * 获取状态颜色
 */
function statusColor($status) {
    $colors = [
        'online'  => '#28a745',
        'offline' => '#6c757d',
        'warning' => '#ffc107',
        'danger'  => '#dc3545',
    ];
    return $colors[$status] ?? '#6c757d';
}

/**
 * 获取严重级别颜色
 */
function severityColor($severity) {
    $colors = [
        'info'     => '#17a2b8',
        'warning'  => '#ffc107',
        'danger'   => '#dc3545',
        'critical' => '#721c24',
    ];
    return $colors[$severity] ?? '#17a2b8';
}

/**
 * 获取严重级别标签
 */
function severityLabel($severity) {
    $labels = [
        'info'     => '提示',
        'warning'  => '警告',
        'danger'   => '严重',
        'critical' => '紧急',
    ];
    return $labels[$severity] ?? '未知';
}

/**
 * 分页辅助
 */
function paginate($total, $page, $perPage = 20) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current_page'=> $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

/**
 * CSRF Token 生成
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateKey(32);
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF 验证
 */
function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
