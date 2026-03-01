<?php
/**
 * 认证模块
 */

/**
 * 检查是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * 要求登录（未登录则跳转）
 */
function requireLogin() {
    if (!isLoggedIn()) {
        if (defined('API_MODE')) {
            jsonResponse(401, '请先登录');
        }
        header('Location: /pages/login.php');
        exit;
    }
}

/**
 * 要求管理员权限
 */
function requireAdmin() {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        if (defined('API_MODE')) {
            jsonResponse(403, '权限不足');
        }
        die('权限不足');
    }
}

/**
 * 用户登录
 */
function login($username, $password) {
    $user = db()->fetch("SELECT * FROM users WHERE username = ?", [$username]);
    
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    
    // 更新最后登录时间
    db()->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    
    logOperation('login', 'user', "用户 {$user['username']} 登录");
    
    return true;
}

/**
 * 用户登出
 */
function logout() {
    logOperation('logout', 'user', "用户 {$_SESSION['username']} 登出");
    session_destroy();
    header('Location: /pages/login.php');
    exit;
}

/**
 * 验证Agent密钥
 */
function verifyAgentKey($agentKey) {
    $server = db()->fetch("SELECT * FROM servers WHERE agent_key = ?", [$agentKey]);
    return $server ?: false;
}

/**
 * 修改密码
 */
function changePassword($userId, $oldPassword, $newPassword) {
    $user = db()->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
    if (!$user || !password_verify($oldPassword, $user['password'])) {
        return false;
    }
    
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    db()->execute("UPDATE users SET password = ? WHERE id = ?", [$hash, $userId]);
    return true;
}
