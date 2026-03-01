<?php
/**
 * 用户认证API
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';

$action = input('action', '');

switch ($action) {
    
    case 'login':
        $username = input('username');
        $password = input('password');
        
        if (empty($username) || empty($password)) {
            jsonResponse(400, '用户名和密码不能为空');
        }
        
        if (login($username, $password)) {
            jsonResponse(200, '登录成功', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['user_role'],
            ]);
        } else {
            jsonResponse(401, '用户名或密码错误');
        }
        break;
    
    case 'logout':
        logout();
        jsonResponse(200, '已退出');
        break;
    
    case 'info':
        requireLogin();
        jsonResponse(200, 'success', [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['user_role'],
            'email' => $_SESSION['user_email'] ?? '',
        ]);
        break;
    
    case 'change_password':
        requireLogin();
        $oldPwd = input('old_password');
        $newPwd = input('new_password');
        
        if (empty($oldPwd) || empty($newPwd)) {
            jsonResponse(400, '请填写完整');
        }
        if (strlen($newPwd) < 6) {
            jsonResponse(400, '新密码至少6位');
        }
        
        if (changePassword($_SESSION['user_id'], $oldPwd, $newPwd)) {
            jsonResponse(200, '密码修改成功');
        } else {
            jsonResponse(400, '原密码错误');
        }
        break;
    
    case 'update_profile':
        requireLogin();
        $email = input('email');
        db()->execute("UPDATE users SET email = ? WHERE id = ?", [$email, $_SESSION['user_id']]);
        $_SESSION['user_email'] = $email;
        jsonResponse(200, '更新成功', ['email' => $email]);
        break;
    
    default:
        jsonResponse(400, '未知操作');
}
