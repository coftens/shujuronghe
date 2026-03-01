<?php
/**
 * 数据采集接口
 * Agent通过POST将采集的数据发送到此接口
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';

// 只接受POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, '方法不允许');
}

// 获取JSON数据
$data = getJsonInput();
if ($data === null) {
    jsonResponse(400, '无效的请求数据: ' . json_last_error_msg());
}

// 验证Agent密钥
$agentKey = $data['agent_key'] ?? '';
if (empty($agentKey)) {
    jsonResponse(401, '缺少Agent密钥');
}

$server = verifyAgentKey($agentKey);
if (!$server) {
    jsonResponse(401, '无效的Agent密钥');
}

$serverId = $server['id'];
$timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');

try {
    db()->beginTransaction();
    
    // 1. 更新服务器心跳和系统信息
    $sysinfo = $data['sysinfo'] ?? [];
    db()->execute(
        "UPDATE servers SET status = 'online', last_heartbeat = NOW(), os_info = ? WHERE id = ?",
        [$sysinfo['os_info'] ?? $server['os_info'], $serverId]
    );
    
    // 2. 存储CPU指标
    if (!empty($data['cpu'])) {
        $cpu = $data['cpu'];
        db()->execute(
            "INSERT INTO metrics_cpu (server_id, cpu_user, cpu_system, cpu_idle, cpu_iowait, cpu_steal, load_1, load_5, load_15, cpu_cores, recorded_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $serverId,
                $cpu['cpu_user'] ?? 0,
                $cpu['cpu_system'] ?? 0,
                $cpu['cpu_idle'] ?? 0,
                $cpu['cpu_iowait'] ?? 0,
                $cpu['cpu_steal'] ?? 0,
                $cpu['load_1'] ?? 0,
                $cpu['load_5'] ?? 0,
                $cpu['load_15'] ?? 0,
                $cpu['cpu_cores'] ?? 1,
                $timestamp
            ]
        );
    }
    
    // 3. 存储内存指标
    if (!empty($data['memory'])) {
        $mem = $data['memory'];
        db()->execute(
            "INSERT INTO metrics_memory (server_id, mem_total, mem_used, mem_free, mem_available, mem_buffers, mem_cached, swap_total, swap_used, mem_usage_pct, recorded_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $serverId,
                $mem['mem_total'] ?? 0,
                $mem['mem_used'] ?? 0,
                $mem['mem_free'] ?? 0,
                $mem['mem_available'] ?? 0,
                $mem['mem_buffers'] ?? 0,
                $mem['mem_cached'] ?? 0,
                $mem['swap_total'] ?? 0,
                $mem['swap_used'] ?? 0,
                $mem['mem_usage_pct'] ?? 0,
                $timestamp
            ]
        );
    }
    
    // 4. 存储磁盘指标
    if (!empty($data['disk']) && is_array($data['disk'])) {
        foreach ($data['disk'] as $disk) {
            db()->execute(
                "INSERT INTO metrics_disk (server_id, mount_point, filesystem, disk_total, disk_used, disk_free, disk_usage_pct, inode_usage_pct, recorded_at) VALUES (?,?,?,?,?,?,?,?,?)",
                [
                    $serverId,
                    $disk['mount_point'] ?? '/',
                    $disk['filesystem'] ?? '',
                    $disk['disk_total'] ?? 0,
                    $disk['disk_used'] ?? 0,
                    $disk['disk_free'] ?? 0,
                    $disk['disk_usage_pct'] ?? 0,
                    $disk['inode_usage_pct'] ?? 0,
                    $timestamp
                ]
            );
        }
    }
    
    // 5. 存储网络指标
    if (!empty($data['network']) && is_array($data['network'])) {
        foreach ($data['network'] as $net) {
            db()->execute(
                "INSERT INTO metrics_network (server_id, interface, bytes_in, bytes_out, packets_in, packets_out, errors_in, errors_out, recorded_at) VALUES (?,?,?,?,?,?,?,?,?)",
                [
                    $serverId,
                    $net['interface'] ?? 'eth0',
                    $net['bytes_in'] ?? 0,
                    $net['bytes_out'] ?? 0,
                    $net['packets_in'] ?? 0,
                    $net['packets_out'] ?? 0,
                    $net['errors_in'] ?? 0,
                    $net['errors_out'] ?? 0,
                    $timestamp
                ]
            );
        }
    }
    
    // 6. 存储进程信息
    if (!empty($data['processes']) && is_array($data['processes'])) {
        foreach ($data['processes'] as $proc) {
            db()->execute(
                "INSERT INTO metrics_process (server_id, pid, user, process_name, cpu_pct, mem_pct, mem_rss, status, command, recorded_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $serverId,
                    $proc['pid'] ?? 0,
                    $proc['user'] ?? '',
                    $proc['process_name'] ?? '',
                    $proc['cpu_pct'] ?? 0,
                    $proc['mem_pct'] ?? 0,
                    $proc['mem_rss'] ?? 0,
                    $proc['status'] ?? '',
                    $proc['command'] ?? '',
                    $timestamp
                ]
            );
        }
    }
    
    // 7. 存储TCP连接状态
    if (!empty($data['tcp'])) {
        $tcp = $data['tcp'];
        db()->execute(
            "INSERT INTO metrics_tcp (server_id, established, time_wait, close_wait, listen, syn_sent, syn_recv, fin_wait1, fin_wait2, total_connections, recorded_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $serverId,
                $tcp['established'] ?? 0,
                $tcp['time_wait'] ?? 0,
                $tcp['close_wait'] ?? 0,
                $tcp['listen'] ?? 0,
                $tcp['syn_sent'] ?? 0,
                $tcp['syn_recv'] ?? 0,
                $tcp['fin_wait1'] ?? 0,
                $tcp['fin_wait2'] ?? 0,
                $tcp['total_connections'] ?? 0,
                $timestamp
            ]
        );
    }
    
    // 8. 存储端口信息（先清除旧数据再插入）
    if (!empty($data['ports']) && is_array($data['ports'])) {
        foreach ($data['ports'] as $port) {
            db()->execute(
                "INSERT INTO metrics_ports (server_id, port, protocol, process_name, recorded_at) VALUES (?,?,?,?,?)",
                [
                    $serverId,
                    $port['port'] ?? 0,
                    $port['protocol'] ?? 'tcp',
                    $port['process_name'] ?? '',
                    $timestamp
                ]
            );
        }
    }
    
    // 9. 存储系统日志
    if (!empty($data['logs']) && is_array($data['logs'])) {
        foreach ($data['logs'] as $log) {
            db()->execute(
                "INSERT INTO system_logs (server_id, log_type, level, source, message, log_time, recorded_at) VALUES (?,?,?,?,?,?,NOW())",
                [
                    $serverId,
                    'syslog',
                    $log['level'] ?? 'info',
                    $log['source'] ?? 'agent',
                    $log['message'] ?? '',
                    $timestamp
                ]
            );
        }
    }
    
    db()->commit();
    jsonResponse(200, '数据接收成功');
    
} catch (Exception $e) {
    db()->rollback();
    jsonResponse(500, '数据存储失败: ' . $e->getMessage());
}
