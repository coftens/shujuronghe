<?php
/**
 * 定时任务 - 数据清理
 * 建议每天凌晨执行一次: 0 2 * * * php /path/to/cron/cleanup.php
 */
define('CRON_MODE', true);
require_once __DIR__ . '/../includes/init.php';

$db = db();
$retentionDays = (int) getSetting('data_retention_days') ?: 90;
$cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

echo date('[Y-m-d H:i:s]') . " 开始数据清理，保留 {$retentionDays} 天内的数据 (>{$cutoff})\n";

$tables = [
    'metrics_cpu'     => 'recorded_at',
    'metrics_memory'  => 'recorded_at',
    'metrics_disk'    => 'recorded_at',
    'metrics_disk_io' => 'recorded_at',
    'metrics_network' => 'recorded_at',
    'metrics_process' => 'recorded_at',
    'metrics_tcp'     => 'recorded_at',
    'metrics_ports'   => 'recorded_at',
    'system_logs'     => 'recorded_at',
    'alert_history'   => 'created_at',
    'notifications'   => 'created_at',
    'operation_logs'  => 'created_at',
];

$totalCleaned = 0;

foreach ($tables as $table => $timeField) {
    try {
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$timeField}` < ?",
            [$cutoff]
        );
        
        if ($count > 0) {
            // 分批删除避免锁表
            $batchSize = 10000;
            $deleted = 0;
            do {
                $affected = $db->execute(
                    "DELETE FROM `{$table}` WHERE `{$timeField}` < ? LIMIT {$batchSize}",
                    [$cutoff]
                );
                $deleted += $affected;
            } while ($affected >= $batchSize);
            
            echo "  {$table}: 清理了 {$deleted} 条记录\n";
            $totalCleaned += $deleted;
        }
    } catch (Exception $e) {
        echo "  {$table}: 清理失败 - " . $e->getMessage() . "\n";
    }
}

// 检查离线服务器
$offlineThreshold = 5 * 60; // 5分钟无数据视为离线
$db->execute(
    "UPDATE servers SET status = 'offline' 
     WHERE status = 'online' AND last_heartbeat < DATE_SUB(NOW(), INTERVAL ? SECOND)",
    [$offlineThreshold]
);

// 自动解决24小时前的活跃告警
$autoResolve = $db->execute(
    "UPDATE alert_history SET status = 'resolved', resolved_at = NOW() 
     WHERE status = 'active' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);

echo date('[Y-m-d H:i:s]') . " 清理完成，共清理 {$totalCleaned} 条历史数据\n";

// 优化表（每月第一天执行）
if (date('d') === '01') {
    echo "执行表优化...\n";
    foreach (array_keys($tables) as $table) {
        try {
            $db->execute("OPTIMIZE TABLE `{$table}`");
            echo "  {$table}: 优化完成\n";
        } catch (Exception $e) {
            echo "  {$table}: 优化失败\n";
        }
    }
}
