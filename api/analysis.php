<?php
/**
 * 性能分析API
 * 提供深度分析功能
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$action = input('action', 'summary');
$serverId = intval(input('server_id', 0));
$hours = intval(input('hours', 24));

if (!$serverId && $action !== 'global_summary') {
    jsonResponse(400, '缺少server_id');
}

switch ($action) {

    // ====== 全局摘要 ======
    case 'global_summary':
        $servers = db()->fetchAll("SELECT * FROM servers");
        $summary = [];
        
        foreach ($servers as $s) {
            $sid = $s['id'];
            $cpu = db()->fetch("SELECT AVG(100-cpu_idle) as avg_cpu, MAX(100-cpu_idle) as max_cpu, MAX(load_1) as max_load FROM metrics_cpu WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)", [$sid, $hours]);
            $mem = db()->fetch("SELECT AVG(mem_usage_pct) as avg_mem, MAX(mem_usage_pct) as max_mem FROM metrics_memory WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)", [$sid, $hours]);
            $disk = db()->fetch("SELECT MAX(disk_usage_pct) as max_disk FROM metrics_disk WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)", [$sid, $hours]);
            
            $summary[] = [
                'server' => $s,
                'cpu_avg' => round($cpu['avg_cpu'] ?? 0, 1),
                'cpu_max' => round($cpu['max_cpu'] ?? 0, 1),
                'load_max' => round($cpu['max_load'] ?? 0, 2),
                'mem_avg' => round($mem['avg_mem'] ?? 0, 1),
                'mem_max' => round($mem['max_mem'] ?? 0, 1),
                'disk_max' => round($disk['max_disk'] ?? 0, 1),
                'is_online' => isServerOnline($s['last_heartbeat']),
            ];
        }
        
        jsonResponse(200, 'success', $summary);
        break;

    // ====== 性能评分 ======
    case 'score':
        $score = calculatePerformanceScore($serverId, $hours);
        jsonResponse(200, 'success', $score);
        break;
    
    // ====== 瓶颈分析 ======
    case 'bottleneck':
        $bottlenecks = analyzeBottlenecks($serverId, $hours);
        jsonResponse(200, 'success', $bottlenecks);
        break;
    
    // ====== 趋势预测 ======
    case 'prediction':
        $predictions = predictTrends($serverId);
        jsonResponse(200, 'success', $predictions);
        break;
    
    // ====== 资源消耗TOP进程 ======
    case 'top_processes':
        $topCpu = db()->fetchAll(
            "SELECT process_name, AVG(cpu_pct) as avg_cpu, MAX(cpu_pct) as max_cpu, COUNT(*) as samples
             FROM metrics_process WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
             GROUP BY process_name ORDER BY avg_cpu DESC LIMIT 10",
            [$serverId, $hours]
        );
        
        $topMem = db()->fetchAll(
            "SELECT process_name, AVG(mem_pct) as avg_mem, MAX(mem_pct) as max_mem, AVG(mem_rss) as avg_rss
             FROM metrics_process WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
             GROUP BY process_name ORDER BY avg_mem DESC LIMIT 10",
            [$serverId, $hours]
        );
        
        jsonResponse(200, 'success', [
            'top_cpu' => $topCpu,
            'top_mem' => $topMem,
        ]);
        break;
    
    // ====== 高峰时段分析 ======
    case 'peak_hours':
        $cpuByHour = db()->fetchAll(
            "SELECT HOUR(recorded_at) as hour, AVG(100-cpu_idle) as avg_cpu, MAX(100-cpu_idle) as max_cpu
             FROM metrics_cpu WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY HOUR(recorded_at) ORDER BY hour",
            [$serverId]
        );
        
        $memByHour = db()->fetchAll(
            "SELECT HOUR(recorded_at) as hour, AVG(mem_usage_pct) as avg_mem
             FROM metrics_memory WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY HOUR(recorded_at) ORDER BY hour",
            [$serverId]
        );
        
        jsonResponse(200, 'success', [
            'cpu_by_hour' => $cpuByHour,
            'mem_by_hour' => $memByHour,
        ]);
        break;
    
    // ====== 对比分析 ======
    case 'compare':
        $compareServerId = intval(input('compare_server_id', 0));
        if (!$compareServerId) jsonResponse(400, '缺少对比服务器ID');
        
        $s1 = getServerStats($serverId, $hours);
        $s2 = getServerStats($compareServerId, $hours);
        
        jsonResponse(200, 'success', [
            'server1' => $s1,
            'server2' => $s2,
        ]);
        break;
    
    default:
        jsonResponse(400, '未知操作');
}

// ====== 辅助函数 ======

/**
 * 计算性能评分（0-100）
 */
function calculatePerformanceScore($serverId, $hours) {
    $cpu = db()->fetch(
        "SELECT AVG(100-cpu_idle) as avg_cpu, AVG(load_1) as avg_load, MAX(cpu_cores) as cores
         FROM metrics_cpu WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    
    $mem = db()->fetch(
        "SELECT AVG(mem_usage_pct) as avg_mem FROM metrics_memory 
         WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    
    $disk = db()->fetch(
        "SELECT MAX(disk_usage_pct) as max_disk FROM metrics_disk 
         WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    
    // 评分规则
    $cpuScore = max(0, 100 - ($cpu['avg_cpu'] ?? 0));
    $loadScore = 100;
    if (($cpu['cores'] ?? 1) > 0) {
        $loadRatio = ($cpu['avg_load'] ?? 0) / $cpu['cores'];
        $loadScore = max(0, 100 - $loadRatio * 50);
    }
    $memScore = max(0, 100 - ($mem['avg_mem'] ?? 0));
    $diskScore = max(0, 100 - ($disk['max_disk'] ?? 0));
    
    // 综合评分（加权）
    $overall = round($cpuScore * 0.3 + $loadScore * 0.2 + $memScore * 0.3 + $diskScore * 0.2);
    
    // 评级
    if ($overall >= 90) $grade = 'A';
    elseif ($overall >= 80) $grade = 'B';
    elseif ($overall >= 60) $grade = 'C';
    elseif ($overall >= 40) $grade = 'D';
    else $grade = 'F';
    
    return [
        'overall' => $overall,
        'grade' => $grade,
        'cpu_score' => round($cpuScore),
        'load_score' => round($loadScore),
        'mem_score' => round($memScore),
        'disk_score' => round($diskScore),
        'details' => [
            'avg_cpu' => round($cpu['avg_cpu'] ?? 0, 1),
            'avg_load' => round($cpu['avg_load'] ?? 0, 2),
            'avg_mem' => round($mem['avg_mem'] ?? 0, 1),
            'max_disk' => round($disk['max_disk'] ?? 0, 1),
        ]
    ];
}

/**
 * 瓶颈分析
 */
function analyzeBottlenecks($serverId, $hours) {
    $issues = [];
    
    // CPU分析
    $cpu = db()->fetch(
        "SELECT AVG(100-cpu_idle) as avg_cpu, MAX(100-cpu_idle) as max_cpu, AVG(cpu_iowait) as avg_iowait, AVG(load_1) as avg_load, MAX(cpu_cores) as cores
         FROM metrics_cpu WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    
    if (($cpu['avg_cpu'] ?? 0) > 80) {
        $issues[] = ['type' => 'cpu', 'severity' => 'danger', 'title' => 'CPU使用率过高',
            'desc' => "平均CPU使用率 {$cpu['avg_cpu']}%，最高达 {$cpu['max_cpu']}%，建议排查高CPU进程或考虑升级配置"];
    } elseif (($cpu['avg_cpu'] ?? 0) > 60) {
        $issues[] = ['type' => 'cpu', 'severity' => 'warning', 'title' => 'CPU使用率偏高',
            'desc' => "平均CPU使用率 {$cpu['avg_cpu']}%，需关注趋势"];
    }
    
    if (($cpu['avg_iowait'] ?? 0) > 20) {
        $issues[] = ['type' => 'cpu', 'severity' => 'warning', 'title' => 'IO等待过高',
            'desc' => "平均IO等待 {$cpu['avg_iowait']}%，可能存在磁盘瓶颈"];
    }
    
    $cores = $cpu['cores'] ?? 1;
    if (($cpu['avg_load'] ?? 0) > $cores * 2) {
        $issues[] = ['type' => 'cpu', 'severity' => 'danger', 'title' => '系统负载过高',
            'desc' => "平均负载 {$cpu['avg_load']}，超过核数({$cores})的2倍"];
    }
    
    // 内存分析
    $mem = db()->fetch(
        "SELECT AVG(mem_usage_pct) as avg_mem, MAX(mem_usage_pct) as max_mem, AVG(swap_used) as avg_swap, AVG(swap_total) as swap_total
         FROM metrics_memory WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    
    if (($mem['avg_mem'] ?? 0) > 90) {
        $issues[] = ['type' => 'memory', 'severity' => 'danger', 'title' => '内存使用率过高',
            'desc' => "平均内存使用率 {$mem['avg_mem']}%，有OOM风险"];
    } elseif (($mem['avg_mem'] ?? 0) > 80) {
        $issues[] = ['type' => 'memory', 'severity' => 'warning', 'title' => '内存使用率偏高',
            'desc' => "平均内存使用率 {$mem['avg_mem']}%"];
    }
    
    if (($mem['swap_total'] ?? 0) > 0) {
        $swapPct = ($mem['avg_swap'] / $mem['swap_total']) * 100;
        if ($swapPct > 30) {
            $issues[] = ['type' => 'memory', 'severity' => 'warning', 'title' => 'Swap使用较多',
                'desc' => "Swap使用率 " . round($swapPct) . "%，可能内存不足"];
        }
    }
    
    // 磁盘分析
    $disks = db()->fetchAll(
        "SELECT mount_point, MAX(disk_usage_pct) as max_usage, MAX(inode_usage_pct) as max_inode
         FROM metrics_disk WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
         GROUP BY mount_point",
        [$serverId, $hours]
    );
    
    foreach ($disks as $d) {
        if ($d['max_usage'] > 90) {
            $issues[] = ['type' => 'disk', 'severity' => 'critical', 'title' => "磁盘 {$d['mount_point']} 空间不足",
                'desc' => "使用率已达 {$d['max_usage']}%，需立即清理"];
        } elseif ($d['max_usage'] > 80) {
            $issues[] = ['type' => 'disk', 'severity' => 'warning', 'title' => "磁盘 {$d['mount_point']} 空间偏满",
                'desc' => "使用率 {$d['max_usage']}%"];
        }
        if ($d['max_inode'] > 80) {
            $issues[] = ['type' => 'disk', 'severity' => 'warning', 'title' => "磁盘 {$d['mount_point']} inode不足",
                'desc' => "inode使用率 {$d['max_inode']}%"];
        }
    }
    
    // TCP分析
    $tcp = db()->fetch(
        "SELECT AVG(total_connections) as avg_conn, MAX(total_connections) as max_conn, AVG(time_wait) as avg_tw, AVG(close_wait) as avg_cw
         FROM metrics_tcp WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    
    if (($tcp['avg_tw'] ?? 0) > 500) {
        $issues[] = ['type' => 'network', 'severity' => 'warning', 'title' => 'TIME_WAIT连接过多',
            'desc' => "平均 {$tcp['avg_tw']} 个TIME_WAIT连接，可能需要调优内核参数"];
    }
    
    if (($tcp['avg_cw'] ?? 0) > 100) {
        $issues[] = ['type' => 'network', 'severity' => 'warning', 'title' => 'CLOSE_WAIT连接过多',
            'desc' => "平均 {$tcp['avg_cw']} 个CLOSE_WAIT连接，可能存在程序未正确关闭连接"];
    }
    
    // 排序：严重程度
    $severityOrder = ['critical' => 0, 'danger' => 1, 'warning' => 2, 'info' => 3];
    usort($issues, function($a, $b) use ($severityOrder) {
        return ($severityOrder[$a['severity']] ?? 9) - ($severityOrder[$b['severity']] ?? 9);
    });
    
    return $issues;
}

/**
 * 趋势预测（简单线性回归）
 */
function predictTrends($serverId) {
    $predictions = [];
    
    // 磁盘增长预测
    $diskData = db()->fetchAll(
        "SELECT mount_point, 
                DATE(recorded_at) as date,
                AVG(disk_usage_pct) as avg_usage,
                AVG(disk_used) as avg_used,
                MAX(disk_total) as total
         FROM metrics_disk WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY mount_point, DATE(recorded_at) ORDER BY mount_point, date",
        [$serverId]
    );
    
    // 按挂载点分组
    $byMount = [];
    foreach ($diskData as $d) {
        $byMount[$d['mount_point']][] = $d;
    }
    
    foreach ($byMount as $mount => $data) {
        if (count($data) < 2) continue;
        
        // 简单线性回归
        $n = count($data);
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        
        foreach ($data as $i => $d) {
            $x = $i;
            $y = $d['avg_usage'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / max(1, ($n * $sumX2 - $sumX * $sumX));
        $intercept = ($sumY - $slope * $sumX) / max(1, $n);
        
        $currentUsage = end($data)['avg_usage'];
        $daysToFull = 0;
        
        if ($slope > 0) {
            // 预测多少天后到100%
            $remaining = 100 - $currentUsage;
            $daysToFull = round($remaining / $slope);
        }
        
        $predictions[] = [
            'type' => 'disk',
            'mount' => $mount,
            'current' => round($currentUsage, 1),
            'daily_growth' => round($slope, 2),
            'days_to_full' => $daysToFull > 0 ? $daysToFull : null,
            'trend' => $slope > 0.5 ? 'rising' : ($slope < -0.5 ? 'falling' : 'stable'),
            'warning' => $daysToFull > 0 && $daysToFull < 30 ? "预计 {$daysToFull} 天后磁盘满" : null,
        ];
    }
    
    // 内存增长预测
    $memData = db()->fetchAll(
        "SELECT DATE(recorded_at) as date, AVG(mem_usage_pct) as avg_usage
         FROM metrics_memory WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(recorded_at) ORDER BY date",
        [$serverId]
    );
    
    if (count($memData) >= 2) {
        $n = count($memData);
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        
        foreach ($memData as $i => $d) {
            $x = $i; $y = $d['avg_usage'];
            $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / max(1, ($n * $sumX2 - $sumX * $sumX));
        
        $predictions[] = [
            'type' => 'memory',
            'current' => round(end($memData)['avg_usage'], 1),
            'daily_growth' => round($slope, 2),
            'trend' => $slope > 1 ? 'rising' : ($slope < -1 ? 'falling' : 'stable'),
            'warning' => $slope > 2 ? "内存使用呈上升趋势，可能存在内存泄漏" : null,
        ];
    }
    
    // CPU趋势
    $cpuData = db()->fetchAll(
        "SELECT DATE(recorded_at) as date, AVG(100-cpu_idle) as avg_usage
         FROM metrics_cpu WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(recorded_at) ORDER BY date",
        [$serverId]
    );
    
    if (count($cpuData) >= 2) {
        $n = count($cpuData);
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        foreach ($cpuData as $i => $d) {
            $x = $i; $y = $d['avg_usage'];
            $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumX2 += $x * $x;
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / max(1, ($n * $sumX2 - $sumX * $sumX));
        
        $predictions[] = [
            'type' => 'cpu',
            'current' => round(end($cpuData)['avg_usage'], 1),
            'daily_growth' => round($slope, 2),
            'trend' => $slope > 2 ? 'rising' : ($slope < -2 ? 'falling' : 'stable'),
            'warning' => $slope > 5 ? "CPU使用率持续上升，需要关注" : null,
        ];
    }
    
    return $predictions;
}

/**
 * 获取服务器统计汇总
 */
function getServerStats($serverId, $hours) {
    $server = db()->fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);
    $cpu = db()->fetch(
        "SELECT AVG(100-cpu_idle) as avg_cpu, MAX(100-cpu_idle) as max_cpu, AVG(load_1) as avg_load
         FROM metrics_cpu WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    $mem = db()->fetch(
        "SELECT AVG(mem_usage_pct) as avg_mem, MAX(mem_usage_pct) as max_mem
         FROM metrics_memory WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    $disk = db()->fetch(
        "SELECT MAX(disk_usage_pct) as max_disk
         FROM metrics_disk WHERE server_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$serverId, $hours]
    );
    
    return [
        'server' => $server,
        'cpu' => $cpu,
        'memory' => $mem,
        'disk' => $disk,
    ];
}
