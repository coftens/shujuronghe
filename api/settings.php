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
    
    case 'edit_user':
        $id = intval(input('id'));
        $email = input('email', '');
        $role  = input('role', 'viewer');
        $password = input('password', '');
        
        if (!in_array($role, ['admin', 'viewer'])) {
            jsonResponse(400, '无效的角色');
        }
        
        if (!empty($password)) {
            if (strlen($password) < 6) jsonResponse(400, '密码至少6个字符');
            $hash = password_hash($password, PASSWORD_BCRYPT);
            db()->execute("UPDATE users SET email = ?, role = ?, password = ? WHERE id = ?", [$email, $role, $hash, $id]);
        } else {
            db()->execute("UPDATE users SET email = ?, role = ? WHERE id = ?", [$email, $role, $id]);
        }
        
        logOperation('edit_user', "user#{$id}", "编辑用户");
        jsonResponse(200, '保存成功');
        break;
    
    case 'test_email':
        $data = getJsonInput();
        if (empty($data)) $data = $_POST;
        
        $host       = $data['smtp_host'] ?? '';
        $port       = intval($data['smtp_port'] ?? 465);
        $encryption = $data['smtp_encryption'] ?? 'ssl';
        $user       = $data['smtp_user'] ?? '';
        $pass       = $data['smtp_pass'] ?? '';
        $from       = $data['smtp_from'] ?? '';
        $to         = $data['to'] ?? '';
        
        if (!$host || !$user || !$pass) jsonResponse(400, '请先填写完整的SMTP配置');
        if (!$to) jsonResponse(400, '请填写测试收件人地址');
        
        $result = smtpSendDirect($host, $port, $encryption, $user, $pass, $from ?: $user, $to,
            '=?UTF-8?B?' . base64_encode('【监控平台】邮件测试') . '?=',
            '<html><body><h3>邮件测试成功</h3><p>您的主机性能监控平台 SMTP 邮件功能配置正确，告警通知将正常发送。</p></body></html>'
        );
        
        if ($result === true) {
            jsonResponse(200, '发送成功');
        } else {
            jsonResponse(500, '发送失败: ' . $result);
        }
        break;
    
    default:
        jsonResponse(400, '未知操作');
}

function smtpSendDirect($host, $port, $encryption, $user, $pass, $from, $to, $subject, $body) {
    try {
        $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        if (!$socket) return "连接失败: {$errstr} ({$errno})";
        
        stream_set_timeout($socket, 15);
        fgets($socket, 1024); // 220 banner
        
        fputs($socket, "EHLO localhost\r\n");
        while ($line = fgets($socket, 1024)) {
            if (substr($line, 3, 1) === ' ') break;
        }
        
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            fgets($socket, 1024);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO localhost\r\n");
            while ($line = fgets($socket, 1024)) {
                if (substr($line, 3, 1) === ' ') break;
            }
        }
        
        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket, 1024);
        fputs($socket, base64_encode($user) . "\r\n");
        fgets($socket, 1024);
        fputs($socket, base64_encode($pass) . "\r\n");
        $authResp = fgets($socket, 1024);
        if (strpos($authResp, '235') === false) {
            fclose($socket);
            return '认证失败，请检查用户名和密码（QQ邮箱请使用授权码）: ' . trim($authResp);
        }
        
        fputs($socket, "MAIL FROM:<{$from}>\r\n"); fgets($socket, 1024);
        fputs($socket, "RCPT TO:<{$to}>\r\n"); fgets($socket, 1024);
        fputs($socket, "DATA\r\n"); fgets($socket, 1024);
        
        $msg  = "From: {$from}\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: {$subject}\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $msg .= $body . "\r\n.\r\n";
        fputs($socket, $msg);
        $sendResp = fgets($socket, 1024);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        if (strpos($sendResp, '250') === false) {
            return '邮件提交失败: ' . trim($sendResp);
        }
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}
