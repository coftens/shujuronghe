<?php

define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$action = input('action', 'list');

switch ($action) {
    
    case 'list':
        $page = max(1, intval(input('page', 1)));
        $perPage = 50;
        $serverId = intval(input('server_id', 0));
        $level = input('level', '');
        $keyword = input('keyword', '');
        
        $where = "1=1";
        $params = [];
        
        if ($serverId) {
            $where .= " AND sl.server_id = ?";
            $params[] = $serverId;
        }
        if ($level) {
            $where .= " AND sl.level = ?";
            $params[] = $level;
        }
        if ($keyword) {
            $where .= " AND sl.message LIKE ?";
            $params[] = "%{$keyword}%";
        }
        
        $total = db()->fetchColumn("SELECT COUNT(*) FROM system_logs sl WHERE {$where}", $params);
        $pagination = paginate($total, $page, $perPage);
        
        $params[] = $pagination['offset'];
        $params[] = $perPage;
        
        $logs = db()->fetchAll(
            "SELECT sl.*, s.name as server_name 
             FROM system_logs sl 
             LEFT JOIN servers s ON sl.server_id = s.id 
             WHERE {$where} 
             ORDER BY sl.recorded_at DESC 
             LIMIT ?, ?",
            $params
        );
        
        jsonResponse(200, 'success', [
            'list' => $logs,
            'pagination' => $pagination
        ]);
        break;
    case 'stats':
        $serverId = intval(input('server_id', 0));
        $hours = intval(input('hours', 24));
        
        $where = "recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
        $params = [$hours];
        if ($serverId) {
            $where .= " AND server_id = ?";
            $params[] = $serverId;
        }
        
        $byLevel = db()->fetchAll(
            "SELECT level, COUNT(*) as count FROM system_logs WHERE {$where} GROUP BY level ORDER BY count DESC",
            $params
        );
        
        $byHour = db()->fetchAll(
            "SELECT HOUR(recorded_at) as hour, COUNT(*) as count FROM system_logs WHERE {$where} GROUP BY HOUR(recorded_at) ORDER BY hour",
            $params
        );
        
        jsonResponse(200, 'success', [
            'by_level' => $byLevel,
            'by_hour' => $byHour,
        ]);
        break;
    
    default:
        jsonResponse(400, '未知操作');
}
