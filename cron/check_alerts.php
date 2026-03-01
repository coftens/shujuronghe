<?php
/**
 * 定时任务 - 告警检查
 * 建议每分钟执行一次: * * * * * php /path/to/cron/check_alerts.php
 */
define('CRON_MODE', true);
require_once __DIR__ . '/../includes/init.php';

$db = db();

// 获取所有启用的告警规则
$rules = $db->fetchAll("SELECT * FROM alert_rules WHERE enabled = 1");
if (empty($rules)) exit;

// 获取所有在线服务器
$servers = $db->fetchAll("SELECT * FROM servers WHERE status = 'online'");
if (empty($servers)) exit;

foreach ($servers as $server) {
    foreach ($rules as $rule) {
        checkRule($db, $server, $rule);
    }
}

function checkRule($db, $server, $rule) {
    $serverId = $server['id'];
    $metricType = $rule['metric_type'];
    $metricField = $rule['metric_field'];
    $condition = $rule['condition'];
    $threshold = (float) $rule['threshold'];
    $severity = $rule['severity'];
    $cooldown = (int) $rule['cooldown'];
    
    // 冷却检查 - 避免重复告警
    if ($cooldown > 0) {
        $lastAlert = $db->fetch(
            "SELECT created_at FROM alert_history 
             WHERE server_id = ? AND rule_id = ? AND status != 'resolved'
             AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
             ORDER BY id DESC LIMIT 1",
            [$serverId, $rule['id'], $cooldown]
        );
        if ($lastAlert) return;
    }
    
    // 获取最新指标值
    $value = getLatestMetricValue($db, $serverId, $metricType, $metricField);
    if ($value === null) return;
    
    // 条件判断
    $triggered = false;
    switch ($condition) {
        case '>':  $triggered = $value > $threshold; break;
        case '>=': $triggered = $value >= $threshold; break;
        case '<':  $triggered = $value < $threshold; break;
        case '<=': $triggered = $value <= $threshold; break;
        case '=':  $triggered = $value == $threshold; break;
        case '!=': $triggered = $value != $threshold; break;
    }
    
    if (!$triggered) return;
    
    // 生成告警
    $message = sprintf(
        '服务器 [%s] %s.%s = %.2f %s %.2f',
        $server['name'], $metricType, $metricField, $value, $condition, $threshold
    );
    
    $db->insert('alert_history', [
        'server_id'  => $serverId,
        'rule_id'    => $rule['id'],
        'severity'   => $severity,
        'message'    => $message,
        'value'      => $value,
        'threshold'  => $threshold,
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    
    // 站内通知
    notifyAllAdmins("⚠ {$message}", $severity === 'critical' ? 'alert' : 'warning');
    
    // 邮件通知
    if (getSetting('enable_email_notify') === '1') {
        sendAlertEmail($db, $server, $rule, $message, $value);
    }
    
    echo date('[Y-m-d H:i:s]') . " ALERT: $message\n";
}

function getLatestMetricValue($db, $serverId, $metricType, $field) {
    $tableMap = [
        'cpu'     => 'metrics_cpu',
        'memory'  => 'metrics_memory',
        'disk'    => 'metrics_disk',
        'disk_io' => 'metrics_disk_io',
        'network' => 'metrics_network',
        'tcp'     => 'metrics_tcp',
    ];
    
    $table = $tableMap[$metricType] ?? null;
    if (!$table) return null;
    
    // 检查字段是否存在于该表
    $allowedFields = [
        'metrics_cpu'     => ['usage_percent', 'load_1', 'load_5', 'load_15', 'io_wait'],
        'metrics_memory'  => ['used_percent', 'swap_used_percent', 'available_kb'],
        'metrics_disk'    => ['used_percent', 'used_gb', 'available_gb'],
        'metrics_disk_io' => ['read_speed', 'write_speed', 'io_util'],
        'metrics_network' => ['rx_bytes', 'tx_bytes', 'rx_packets', 'tx_packets'],
        'metrics_tcp'     => ['established', 'time_wait', 'close_wait'],
    ];
    
    if (!in_array($field, $allowedFields[$table] ?? [])) return null;
    
    $row = $db->fetch(
        "SELECT `{$field}` as val FROM `{$table}` WHERE server_id = ? ORDER BY recorded_at DESC LIMIT 1",
        [$serverId]
    );
    
    return $row ? (float)$row['val'] : null;
}

function sendAlertEmail($db, $server, $rule, $message, $value) {
    $smtpHost = getSetting('smtp_host');
    $smtpPort = getSetting('smtp_port') ?: 465;
    $smtpEncryption = getSetting('smtp_encryption') ?: 'ssl';
    $smtpUser = getSetting('smtp_user');
    $smtpPass = getSetting('smtp_pass');
    $smtpFrom = getSetting('smtp_from');
    
    if (!$smtpHost || !$smtpUser || !$smtpPass) return;
    
    // 获取所有管理员邮箱
    $admins = $db->fetchAll("SELECT email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''");
    if (empty($admins)) return;
    
    $subject = "=?UTF-8?B?" . base64_encode("[告警] " . $server['name'] . " - " . $rule['name']) . "?=";
    
    $body = "<html><body>";
    $body .= "<h2 style='color:#ff4d4f;'>⚠ 服务器告警通知</h2>";
    $body .= "<table style='border-collapse:collapse;width:100%;max-width:600px;'>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>服务器</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$server['name']} ({$server['ip_address']})</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>规则</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$rule['name']}</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>当前值</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$value}</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>阈值</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$rule['condition']} {$rule['threshold']}</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>级别</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$rule['severity']}</td></tr>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>时间</strong></td><td style='padding:8px;border:1px solid #ddd;'>" . date('Y-m-d H:i:s') . "</td></tr>";
    $body .= "</table>";
    $body .= "<p style='color:#999;font-size:12px;margin-top:20px;'>此邮件由主机性能监控平台自动发送</p>";
    $body .= "</body></html>";
    
    $headers  = "From: {$smtpFrom}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    foreach ($admins as $admin) {
        $to = $admin['email'];
        // 使用 fsockopen 发送SMTP邮件
        smtpSend($smtpHost, $smtpPort, $smtpEncryption, $smtpUser, $smtpPass, $smtpFrom, $to, $subject, $body);
    }
}

/**
 * 使用 SMTP 发送邮件（不依赖第三方库）
 */
function smtpSend($host, $port, $encryption, $user, $pass, $from, $to, $subject, $body) {
    try {
        $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("SMTP连接失败: $errstr ($errno)");
            return false;
        }
        
        stream_set_timeout($socket, 10);
        
        $response = fgets($socket, 1024);
        
        fputs($socket, "EHLO localhost\r\n");
        $response = '';
        while ($line = fgets($socket, 1024)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            fgets($socket, 1024);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO localhost\r\n");
            while ($line = fgets($socket, 1024)) {
                if (substr($line, 3, 1) == ' ') break;
            }
        }
        
        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket, 1024);
        
        fputs($socket, base64_encode($user) . "\r\n");
        fgets($socket, 1024);
        
        fputs($socket, base64_encode($pass) . "\r\n");
        $resp = fgets($socket, 1024);
        if (substr($resp, 0, 3) != '235') {
            error_log("SMTP认证失败: $resp");
            fclose($socket);
            return false;
        }
        
        fputs($socket, "MAIL FROM: <{$from}>\r\n");
        fgets($socket, 1024);
        
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        fgets($socket, 1024);
        
        fputs($socket, "DATA\r\n");
        fgets($socket, 1024);
        
        $message  = "To: {$to}\r\n";
        $message .= "From: {$from}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $body . "\r\n.\r\n";
        
        fputs($socket, $message);
        fgets($socket, 1024);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
    } catch (Exception $e) {
        error_log("邮件发送异常: " . $e->getMessage());
        return false;
    }
}
