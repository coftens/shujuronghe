<?php
/**
 * 指标数据查询API
 * 为前端图表提供数据
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$action = input('action', 'overview');
$serverId = intval(input('server_id', 0));
$hours = intval(input('hours', 1)); // 查询最近N小时
$startTime = input('start_time', date('Y-m-d H:i:s', strtotime("-{$hours} hours")));
$endTime = input('end_time', date('Y-m-d H:i:s'));

switch ($action) {
    
    // ====== 概览数据 ======
    case 'overview':
        $servers = db()->fetchAll("SELECT * FROM servers ORDER BY id");
        $totalServers = count($servers);
        $onlineCount = 0;
        $warningCount = 0;
        $dangerCount = 0;
        
        foreach ($servers as &$s) {
            $s['is_online'] = isServerOnline($s['last_heartbeat']);
            if ($s['is_online']) {
                $onlineCount++;
            }
            // 获取最新指标
            $s['latest_cpu'] = db()->fetch(
                "SELECT * FROM metrics_cpu WHERE server_id = ? ORDER BY recorded_at DESC LIMIT 1",
                [$s['id']]
            );
            $s['latest_mem'] = db()->fetch(
                "SELECT * FROM metrics_memory WHERE server_id = ? ORDER BY recorded_at DESC LIMIT 1",
                [$s['id']]
            );
            $s['latest_disk'] = db()->fetchAll(
                "SELECT * FROM metrics_disk WHERE server_id = ? AND recorded_at = (SELECT MAX(recorded_at) FROM metrics_disk WHERE server_id = ?)",
                [$s['id'], $s['id']]
            );
        }
        
        // 活跃告警数
        $activeAlerts = db()->fetchColumn("SELECT COUNT(*) FROM alert_history WHERE status = 'active'");
        
        jsonResponse(200, 'success', [
            'servers' => $servers,
            'stats' => [
                'total_servers' => $totalServers,
                'online' => $onlineCount,
                'offline' => $totalServers - $onlineCount,
                'active_alerts' => intval($activeAlerts),
            ]
        ]);
        break;
    
    // ====== CPU趋势 ======
    case 'cpu_trend':
        if (!$serverId) jsonResponse(400, '缺少server_id');
        
        $data = db()->fetchAll(
            "SELECT cpu_user, cpu_system, cpu_idle, cpu_iowait, load_1, load_5, load_15, cpu_cores, recorded_at 
             FROM metrics_cpu WHERE server_id = ? AND recorded_at BETWEEN ? AND ? ORDER BY recorded_at",
            [$serverId, $startTime, $endTime]
        );
        
        jsonResponse(200, 'success', $data);
        break;
    
    // ====== 内存趋势 ======
    case 'memory_trend':
        if (!$serverId) jsonResponse(400, '缺少server_id');
        
        $data = db()->fetchAll(
            "SELECT mem_total, mem_used, mem_free, mem_available, mem_buffers, mem_cached, swap_total, swap_used, mem_usage_pct, recorded_at 
             FROM metrics_memory WHERE server_id = ? AND recorded_at BETWEEN ? AND ? ORDER BY recorded_at",
            [$serverId, $startTime, $endTime]
        );
        
        jsonResponse(200, 'success', $data);
        break;
    
    // ====== 磁盘趋势 ======
    case 'disk_trend':
        if (!$serverId) jsonResponse(400, '缺少server_id');
        
        $mountPoint = input('mount', '/');
        $data = db()->fetchAll(
            "SELECT mount_point, disk_total, disk_used, disk_free, disk_usage_pct, inode_usage_pct, recorded_at 
             FROM metrics_disk WHERE server_id = ? AND mount_point = ? AND recorded_at BETWEEN ? AND ? ORDER BY recorded_at",
            [$serverId, $mountPoint, $startTime, $endTime]
        );
        
        jsonResponse(200, 'success', $data);
        break;
    
    // ====== 网络趋势 ======
    case 'network_trend':
        if (!$serverId) jsonResponse(400, '缺少server_id');
        
        $iface = input('interface', '');
        $params = [$serverId, $startTime, $endTime];
        $where = '';
        if ($iface) {
            $where = " AND interface = ?";
            $params[] = $iface;
        }
        
        $data = db()->fetchAll(
            "SELECT interface, bytes_in, bytes_out, packets_in, packets_out, errors_in, errors_out, recorded_at 
             FROM metrics_network WHERE server_id = ? AND recorded_at BETWEEN ? AND ? {$where} ORDER BY recorded_at",
            $params
        );
        
        // 计算带宽（相邻两条记录之差）
        $result = [];
        $prev = [];
        foreach ($data as $row) {
            $iface = $row['interface'];
            if (isset($prev[$iface])) {
                $timeDiff = strtotime($row['recorded_at']) - strtotime($prev[$iface]['recorded_at']);
                if ($timeDiff > 0) {
                    $row['bandwidth_in'] = round(($row['bytes_in'] - $prev[$iface]['bytes_in']) * 8 / $timeDiff / 1024 / 1024, 3);
                    $row['bandwidth_out'] = round(($row['bytes_out'] - $prev[$iface]['bytes_out']) * 8 / $timeDiff / 1024 / 1024, 3);
                    if ($row['bandwidth_in'] < 0) $row['bandwidth_in'] = 0;
                    if ($row['bandwidth_out'] < 0) $row['bandwidth_out'] = 0;
                    $result[] = $row;
                }
            }
            $prev[$iface] = $row;
        }
        
        jsonResponse(200, 'success', $result);
        break;
    
    // ====== 进程列表 ======
    case 'processes':
        if (!$serverId) jsonResponse(400, '缺少server_id');
        
        $sortBy = input('sort', 'cpu_pct');
        $allowSort = ['cpu_pct', 'mem_pct', 'mem_rss'];
        if (!in_array($sortBy, $allowSort)) $sortBy = 'cpu_pct';
        
        $data = db()->fetchAll(
            "SELECT * FROM metrics_process WHERE server_id = ? AND recorded_at = (SELECT MAX(recorded_at) FROM metrics_process WHERE server_id = ?) ORDER BY {$sortBy} DESC LIMIT 20",
            [$serverId, $serverId]
        );
        
        jsonResponse(200, 'success', $data);
        break;
    
    // ====== TCP连接趋势 ======
    case 'tcp_trend':
        if (!$serverId) jsonResponse(400, '缺少server_id');
        
        $data = db()->fetchAll(
            "SELECT * FROM metrics_tcp WHERE server_id = ? AND recorded_at BETWEEN ? AND ? ORDER BY recorded_at",
            [$serverId, $startTime, $endTime]
        );
        
        jsonResponse(200, 'success', $data);
        break;
    
    // ====== 端口列表 ======
    case 'ports':
        if (!$serverId) jsonResponse(400, '缺少server_id');
        
        $data = db()->fetchAll(
            "SELECT * FROM metrics_ports WHERE server_id = ? AND recorded_at = (SELECT MAX(recorded_at) FROM metrics_ports WHERE server_id = ?) ORDER BY port",
            [$serverId, $serverId]
        );
        
        jsonResponse(200, 'success', $data);
        break;
    
    // ====== 最新指标快照 ======
    case 'latest':
        if (!$serverId) jsonResponse(400, '缺少server_id');
        
        $cpu = db()->fetch("SELECT * FROM metrics_cpu WHERE server_id = ? ORDER BY recorded_at DESC LIMIT 1", [$serverId]);
        $mem = db()->fetch("SELECT * FROM metrics_memory WHERE server_id = ? ORDER BY recorded_at DESC LIMIT 1", [$serverId]);
        $disks = db()->fetchAll(
            "SELECT * FROM metrics_disk WHERE server_id = ? AND recorded_at = (SELECT MAX(recorded_at) FROM metrics_disk WHERE server_id = ?)",
            [$serverId, $serverId]
        );
        $tcp = db()->fetch("SELECT * FROM metrics_tcp WHERE server_id = ? ORDER BY recorded_at DESC LIMIT 1", [$serverId]);
        $server = db()->fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);
        
        jsonResponse(200, 'success', [
            'server' => $server,
            'cpu' => $cpu,
            'memory' => $mem,
            'disks' => $disks,
            'tcp' => $tcp,
        ]);
        break;
    
    default:
        jsonResponse(400, '未知操作');
}
