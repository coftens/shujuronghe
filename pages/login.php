<?php
/**
 * 登录页面
 */
require_once __DIR__ . '/../includes/init.php';

// 已登录则跳转
if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = input('username');
    $password = input('password');
    
    if (login($username, $password)) {
        header('Location: /pages/dashboard.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 多源数据融合主机性能分析与故障预警平台</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <h2>📊 性能监控平台</h2>
        <p class="subtitle">多源数据融合的主机性能分析与故障预警平台</p>
        
        <?php if ($error): ?>
        <div style="background:#fff2f0;border:1px solid #ffccc7;color:#ff4d4f;padding:10px;border-radius:4px;margin-bottom:16px;text-align:center;">
            <?php echo e($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" class="form-control" placeholder="请输入用户名" required autofocus value="<?php echo e(input('username')); ?>">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-control" placeholder="请输入密码" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-lg">登 录</button>
            </div>
        </form>
        
        <p style="text-align:center;color:#999;font-size:12px;margin-top:24px;">
            默认账号: admin / admin123
        </p>
    </div>
</div>
</body>
</html>
