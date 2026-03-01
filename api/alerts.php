<?php
/**
 * 告警管理API
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$action = input('action', 'list');

switch ($action) {
    
    // 告警历史列表
    case 'list':
        $page = max(1, intval(input('page', 1)));
        $perPage = 20;
        $serverId = intval(input('server_id', 0));
        $status = input('status', '');
        $severity = input('severity', '');
        
        $where = "1=1";
        $params = [];
        
        if ($serverId) {
            $where .= " AND ah.server_id = ?";
            $params[] = $serverId;
        }
        if ($status) {
            $where .= " AND ah.status = ?";
            $params[] = $status;
        }
        if ($severity) {
            $where .= " AND ah.severity = ?";
            $params[] = $severity;
        }
        
        $total = db()->fetchColumn("SELECT COUNT(*) FROM alert_history ah WHERE {$where}", $params);
        $pagination = paginate($total, $page, $perPage);
        
        $params[] = $pagination['offset'];
        $params[] = $perPage;
        
        $alerts = db()->fetchAll(
            "SELECT ah.*, s.name as server_name, s.host as server_host 
             FROM alert_history ah 
             LEFT JOIN servers s ON ah.server_id = s.id 
             WHERE {$where} 
             ORDER BY ah.created_at DESC 
             LIMIT ?, ?",
            $params
        );
        
        jsonResponse(200, 'success', [
            'list' => $alerts,
            'pagination' => $pagination
        ]);
        break;
    
    // 活跃告警
    case 'active':
        $alerts = db()->fetchAll(
            "SELECT ah.*, s.name as server_name, s.host as server_host 
             FROM alert_history ah 
             LEFT JOIN servers s ON ah.server_id = s.id 
             WHERE ah.status = 'active' 
             ORDER BY ah.created_at DESC 
             LIMIT 50"
        );
        jsonResponse(200, 'success', $alerts);
        break;
    
    // 确认告警
    case 'acknowledge':
        $id = intval(input('id'));
        if (!$id) jsonResponse(400, '缺少ID');
        
        db()->execute(
            "UPDATE alert_history SET status = 'acknowledged', acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ?",
            [$_SESSION['user_id'], $id]
        );
        
        logOperation('ack_alert', "alert#{$id}", '确认告警');
        jsonResponse(200, '已确认');
        break;
    
    // 解决告警
    case 'resolve':
        $id = intval(input('id'));
        if (!$id) jsonResponse(400, '缺少ID');
        
        db()->execute(
            "UPDATE alert_history SET status = 'resolved', resolved_at = NOW() WHERE id = ?",
            [$id]
        );
        
        logOperation('resolve_alert', "alert#{$id}", '解决告警');
        jsonResponse(200, '已解决');
        break;
    
    // 批量解决
    case 'resolve_all':
        requireAdmin();
        $serverId = intval(input('server_id', 0));
        
        if ($serverId) {
            db()->execute(
                "UPDATE alert_history SET status = 'resolved', resolved_at = NOW() WHERE server_id = ? AND status = 'active'",
                [$serverId]
            );
        } else {
            db()->execute(
                "UPDATE alert_history SET status = 'resolved', resolved_at = NOW() WHERE status = 'active'"
            );
        }
        
        jsonResponse(200, '已全部解决');
        break;
    
    // 告警规则列表
    case 'rules':
        $rules = db()->fetchAll("SELECT ar.*, s.name as server_name FROM alert_rules ar LEFT JOIN servers s ON ar.server_id = s.id ORDER BY ar.id");
        jsonResponse(200, 'success', $rules);
        break;
    
    // 添加/编辑告警规则
    case 'save_rule':
        requireAdmin();
        $id = intval(input('id', 0));
        $name = input('name');
        $metricType = input('metric_type');
        $metricField = input('metric_field');
        $condition = input('condition');
        $threshold = floatval(input('threshold'));
        $duration = intval(input('duration', 1));
        $severity = input('severity', 'warning');
        $serverId = intval(input('server_id', 0)) ?: null;
        $enabled = intval(input('enabled', 1));
        $cooldown = intval(input('cooldown', 300));
        
        if (empty($name) || empty($metricType) || empty($metricField)) {
            jsonResponse(400, '参数不完整');
        }
        
        if ($id > 0) {
            db()->execute(
                "UPDATE alert_rules SET name=?, metric_type=?, metric_field=?, `condition`=?, threshold=?, duration=?, severity=?, server_id=?, enabled=?, cooldown=? WHERE id=?",
                [$name, $metricType, $metricField, $condition, $threshold, $duration, $severity, $serverId, $enabled, $cooldown, $id]
            );
            logOperation('edit_rule', "rule#{$id}", "编辑告警规则: {$name}");
        } else {
            $id = db()->insert(
                "INSERT INTO alert_rules (name, metric_type, metric_field, `condition`, threshold, duration, severity, server_id, enabled, cooldown) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$name, $metricType, $metricField, $condition, $threshold, $duration, $severity, $serverId, $enabled, $cooldown]
            );
            logOperation('add_rule', "rule#{$id}", "添加告警规则: {$name}");
        }
        
        jsonResponse(200, '保存成功', ['id' => $id]);
        break;
    
    // 删除告警规则
    case 'delete_rule':
        requireAdmin();
        $id = intval(input('id'));
        if (!$id) jsonResponse(400, '缺少ID');
        
        db()->execute("DELETE FROM alert_rules WHERE id = ?", [$id]);
        logOperation('delete_rule', "rule#{$id}", '删除告警规则');
        jsonResponse(200, '删除成功');
        break;
    
    // 切换规则启用/禁用
    case 'toggle_rule':
        requireAdmin();
        $id = intval(input('id'));
        if (!$id) jsonResponse(400, '缺少ID');
        
        db()->execute("UPDATE alert_rules SET enabled = 1 - enabled WHERE id = ?", [$id]);
        jsonResponse(200, '已切换');
        break;
    
    // 告警统计
    case 'stats':
        $days = intval(input('days', 7));
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        // 按天统计
        $daily = db()->fetchAll(
            "SELECT DATE(created_at) as date, severity, COUNT(*) as count 
             FROM alert_history WHERE created_at >= ? 
             GROUP BY DATE(created_at), severity 
             ORDER BY date",
            [$startDate]
        );
        
        // 按类型统计
        $byType = db()->fetchAll(
            "SELECT metric_type, COUNT(*) as count 
             FROM alert_history WHERE created_at >= ? 
             GROUP BY metric_type ORDER BY count DESC",
            [$startDate]
        );
        
        // 按服务器统计
        $byServer = db()->fetchAll(
            "SELECT s.name, COUNT(*) as count 
             FROM alert_history ah 
             LEFT JOIN servers s ON ah.server_id = s.id 
             WHERE ah.created_at >= ? 
             GROUP BY ah.server_id ORDER BY count DESC",
            [$startDate]
        );
        
        jsonResponse(200, 'success', [
            'daily' => $daily,
            'by_type' => $byType,
            'by_server' => $byServer,
        ]);
        break;
    
    default:
        jsonResponse(400, '未知操作');
}
