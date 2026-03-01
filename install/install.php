<?php
/**
 * Web安装向导
 * 首次部署时通过浏览器访问此页面完成数据库初始化
 */
session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        // 测试数据库连接并创建表
        $host = trim($_POST['db_host']);
        $port = trim($_POST['db_port']) ?: '3306';
        $name = trim($_POST['db_name']);
        $user = trim($_POST['db_user']);
        $pass = $_POST['db_pass'];
        
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            // 创建数据库
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$name}`");
            
            // 执行SQL脚本
            $sqlFile = __DIR__ . '/database.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception('找不到 database.sql 文件');
            }
            
            $sql = file_get_contents($sqlFile);
            // 分割SQL语句
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (!empty($stmt) && stripos($stmt, '--') !== 0) {
                    $pdo->exec($stmt);
                }
            }
            
            // 更新配置文件
            $configContent = "<?php\nreturn [\n";
            $configContent .= "    'host'     => '{$host}',\n";
            $configContent .= "    'port'     => '{$port}',\n";
            $configContent .= "    'dbname'   => '{$name}',\n";
            $configContent .= "    'username' => '{$user}',\n";
            $configContent .= "    'password' => '{$pass}',\n";
            $configContent .= "    'charset'  => 'utf8mb4',\n";
            $configContent .= "];\n";
            
            $configPath = dirname(__DIR__) . '/config/database.php';
            if (file_put_contents($configPath, $configContent) === false) {
                throw new Exception('无法写入配置文件，请手动修改 config/database.php');
            }
            
            $_SESSION['install_step'] = 3;
            header('Location: install.php?step=3');
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($step === 3) {
        // 修改管理员密码
        $adminPass = $_POST['admin_password'];
        $confirmPass = $_POST['confirm_password'];
        
        if (strlen($adminPass) < 6) {
            $error = '密码至少6个字符';
        } elseif ($adminPass !== $confirmPass) {
            $error = '两次密码不一致';
        } else {
            try {
                $config = require dirname(__DIR__) . '/config/database.php';
                $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
                $pdo = new PDO($dsn, $config['username'], $config['password']);
                
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'")->execute([$hash]);
                
                // 更新站点URL
                if (!empty($_POST['site_url'])) {
                    $pdo->prepare("UPDATE settings SET value = ? WHERE `key` = 'server_url'")->execute([$_POST['site_url']]);
                }
                
                $_SESSION['install_step'] = 4;
                header('Location: install.php?step=4');
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装向导</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .installer { background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.3); padding:40px; width:500px; max-width:90vw; }
        h1 { text-align:center; color:#333; margin-bottom:8px; font-size:24px; }
        .subtitle { text-align:center; color:#888; margin-bottom:30px; font-size:14px; }
        .steps { display:flex; justify-content:center; margin-bottom:30px; gap:8px; }
        .step-item { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:bold; background:#e8e8e8; color:#999; }
        .step-item.active { background:#667eea; color:#fff; }
        .step-item.done { background:#52c41a; color:#fff; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; margin-bottom:6px; color:#333; font-weight:500; font-size:14px; }
        .form-group input, .form-group select { width:100%; padding:10px 12px; border:1px solid #d9d9d9; border-radius:6px; font-size:14px; }
        .form-group input:focus { outline:none; border-color:#667eea; box-shadow:0 0 0 2px rgba(102,126,234,0.2); }
        .btn { display:block; width:100%; padding:12px; border:none; border-radius:6px; background:#667eea; color:#fff; font-size:16px; cursor:pointer; margin-top:20px; }
        .btn:hover { background:#5a6fd6; }
        .error { background:#fff2f0; border:1px solid #ffccc7; color:#ff4d4f; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:14px; }
        .success { background:#f6ffed; border:1px solid #b7eb8f; color:#52c41a; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:14px; }
        .info { background:#e6f7ff; border:1px solid #91d5ff; color:#1890ff; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:14px; }
        .check-list { list-style:none; padding:0; }
        .check-list li { padding:8px 0; display:flex; justify-content:space-between; border-bottom:1px solid #f0f0f0; font-size:14px; }
        .check-ok { color:#52c41a; }
        .check-fail { color:#ff4d4f; }
        .complete-icon { font-size:64px; text-align:center; margin:20px 0; }
    </style>
</head>
<body>
<div class="installer">
    <h1>🖥️ 主机性能监控平台</h1>
    <p class="subtitle">安装向导</p>
    
    <div class="steps">
        <div class="step-item <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>">1</div>
        <div class="step-item <?php echo $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>">2</div>
        <div class="step-item <?php echo $step >= 3 ? ($step > 3 ? 'done' : 'active') : ''; ?>">3</div>
        <div class="step-item <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
    </div>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($step === 1): ?>
    <!-- 步骤1: 环境检查 -->
    <h3 style="margin-bottom:16px;">环境检查</h3>
    <?php
    $checks = [
        'PHP版本 ≥ 7.2' => version_compare(PHP_VERSION, '7.2.0', '>='),
        'PDO扩展' => extension_loaded('pdo'),
        'PDO MySQL' => extension_loaded('pdo_mysql'),
        'JSON扩展' => extension_loaded('json'),
        'cURL扩展' => extension_loaded('curl'),
        'Mbstring扩展' => extension_loaded('mbstring'),
        'OpenSSL扩展' => extension_loaded('openssl'),
        'config目录可写' => is_writable(dirname(__DIR__) . '/config'),
    ];
    $allPassed = !in_array(false, $checks);
    ?>
    <ul class="check-list">
        <?php foreach ($checks as $name => $passed): ?>
        <li>
            <span><?php echo $name; ?></span>
            <span class="<?php echo $passed ? 'check-ok' : 'check-fail'; ?>"><?php echo $passed ? '✓ 通过' : '✗ 不通过'; ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <?php if ($allPassed): ?>
        <a href="install.php?step=2" class="btn" style="text-align:center;text-decoration:none;color:#fff;">下一步 →</a>
    <?php else: ?>
        <div class="error" style="margin-top:16px;">请先解决上述环境问题后再继续安装</div>
    <?php endif; ?>
    
    <?php elseif ($step === 2): ?>
    <!-- 步骤2: 数据库配置 -->
    <h3 style="margin-bottom:16px;">数据库配置</h3>
    <form method="POST">
        <div class="form-group">
            <label>数据库主机</label>
            <input type="text" name="db_host" value="127.0.0.1" required>
        </div>
        <div class="form-group">
            <label>端口</label>
            <input type="text" name="db_port" value="3306">
        </div>
        <div class="form-group">
            <label>数据库名</label>
            <input type="text" name="db_name" value="host_monitor" required>
        </div>
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="db_user" value="root" required>
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="db_pass">
        </div>
        <button type="submit" class="btn">创建数据库并初始化 →</button>
    </form>
    
    <?php elseif ($step === 3): ?>
    <!-- 步骤3: 管理员配置 -->
    <h3 style="margin-bottom:16px;">管理员配置</h3>
    <div class="info">默认管理员用户名为 <strong>admin</strong>，请设置新密码</div>
    <form method="POST">
        <div class="form-group">
            <label>站点URL</label>
            <input type="text" name="site_url" value="http://<?php echo $_SERVER['HTTP_HOST']; ?>" placeholder="http://your-server-ip">
        </div>
        <div class="form-group">
            <label>管理员密码</label>
            <input type="password" name="admin_password" required minlength="6">
        </div>
        <div class="form-group">
            <label>确认密码</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn">完成安装 →</button>
    </form>
    
    <?php elseif ($step === 4): ?>
    <!-- 步骤4: 安装完成 -->
    <div class="complete-icon">🎉</div>
    <h3 style="text-align:center;margin-bottom:16px;">安装完成！</h3>
    <div class="success">系统已成功安装，可以开始使用了。</div>
    <div class="info">
        <strong>后续配置：</strong><br>
        1. 添加定时任务（告警检查）：<br>
        <code style="font-size:12px;">* * * * * php <?php echo dirname(__DIR__); ?>/cron/check_alerts.php</code><br><br>
        2. 添加定时任务（数据清理）：<br>
        <code style="font-size:12px;">0 2 * * * php <?php echo dirname(__DIR__); ?>/cron/cleanup.php</code><br><br>
        3. 建议安装完成后删除或重命名此安装文件
    </div>
    <a href="../index.php" class="btn" style="text-align:center;text-decoration:none;color:#fff;">进入系统 →</a>
    <?php endif; ?>
</div>
</body>
</html>
