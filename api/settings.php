<?php
/**
 * 系统设置API
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';
requireAdmin();

$action = input('action', 'get');

switch ($action) {
    
    case 'get':
        $settings = db()->fetchAll("SELECT key_name, value, description FROM settings ORDER BY id");
        $result = [];
        foreach ($settings as $s) {
            $result[$s['key_name']] = $s['value'];
        }
        jsonResponse(200, 'success', $result);
        break;
    
    case 'save':
        $data = getJsonInput();
        if (empty($data)) $data = $_POST;
        
        foreach ($data as $key => $value) {
            if ($key === 'action' || $key === 'csrf_token') continue;
            setSetting($key, $value);
        }
        
        logOperation('update_settings', 'settings', '更新系统设置');
        jsonResponse(200, '保存成功');
        break;
    
    // 用户管理
    case 'users':
        $users = db()->fetchAll("SELECT id, username, email, role, last_login, created_at FROM users ORDER BY id");
        jsonResponse(200, 'success', $users);
        break;
    
    case 'add_user':
        $username = input('username');
        $password = input('password');
        $email = input('email');
        $role = input('role', 'viewer');
        
        if (empty($username) || empty($password)) {
            jsonResponse(400, '用户名和密码不能为空');
        }
        
        $exists = db()->fetchColumn("SELECT COUNT(*) FROM users WHERE username = ?", [$username]);
        if ($exists) jsonResponse(400, '用户名已存在');
        
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $id = db()->insert(
            "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)",
            [$username, $hash, $email, $role]
        );
        
        logOperation('add_user', "user#{$id}", "添加用户: {$username}");
        jsonResponse(200, '添加成功');
        break;
    
    case 'delete_user':
        $id = intval(input('id'));
        if ($id === $_SESSION['user_id']) {
            jsonResponse(400, '不能删除自己');
        }
        db()->execute("DELETE FROM users WHERE id = ?", [$id]);
        logOperation('delete_user', "user#{$id}", '删除用户');
        jsonResponse(200, '删除成功');
        break;
    
    default:
        jsonResponse(400, '未知操作');
}
