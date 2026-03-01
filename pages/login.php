<?php

require_once __DIR__ . '/../includes/init.php';
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
    
    <div class="login-left">
        <div class="login-brand">
            <div class="login-brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2"/>
                    <path d="M8 21h8M12 17v4"/>
                    <polyline points="7 10 10 7 13 10 17 6"/>
                </svg>
            </div>
            <h1>性能监控平台</h1>
            <p>多源数据融合的主机性能分析<br>与故障预警系统</p>
        </div>
        <div class="login-features">
            <div class="login-feature-item">
                <span class="login-feature-dot"></span>
                实时采集 CPU、内存、磁盘、网络多维数据
            </div>
            <div class="login-feature-item">
                <span class="login-feature-dot"></span>
                智能阈值预警，状态机驱动告警逻辑
            </div>
            <div class="login-feature-item">
                <span class="login-feature-dot"></span>
                历史趋势分析与性能综合评分
            </div>
            <div class="login-feature-item">
                <span class="login-feature-dot"></span>
                邮件实时通知，故障感知零延迟
            </div>
        </div>
    </div>

    
    <div class="login-right">
        <div class="login-form-wrap">
            <div class="login-title">欢迎回来</div>
            <div class="login-subtitle">请登录您的账号以继续</div>

            <?php if ($error): ?>
            <div class="login-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?php echo e($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="login-input-wrap">
                    <label>用户名</label>
                    <div class="login-input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <input type="text" name="username" class="form-control" placeholder="请输入用户名" required autofocus value="<?php echo e(input('username')); ?>">
                </div>

                <div class="login-input-wrap">
                    <label>密码</label>
                    <div class="login-input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="请输入密码" required>
                </div>

                <button type="submit" class="login-submit">登 录</button>
            </form>

            <div class="login-hint">默认账号：admin &nbsp;/&nbsp; admin123</div>
        </div>
    </div>
</div>
</body>
</html>
