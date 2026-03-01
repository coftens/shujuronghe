<?php
/**
 * 服务器管理API
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$action = input('action', 'list');

switch ($action) {
    
    // 服务器列表
    case 'list':
        $servers = db()->fetchAll("SELECT * FROM servers ORDER BY id");
        foreach ($servers as &$s) {
            $s['is_online'] = isServerOnline($s['last_heartbeat']);
        }
        jsonResponse(200, 'success', $servers);
        break;
    
    // 添加服务器
    case 'add':
        requireAdmin();
        $name = input('name');
        $host = input('host');
        $port = intval(input('port', 22));
        
        if (empty($name) || empty($host)) {
            jsonResponse(400, '名称和IP不能为空');
        }
        
        $agentKey = generateKey(32);
        
        $id = db()->insert(
            "INSERT INTO servers (name, host, port, agent_key) VALUES (?, ?, ?, ?)",
            [$name, $host, $port, $agentKey]
        );
        
        logOperation('add_server', "server#{$id}", "添加服务器: {$name} ({$host})");
        
        jsonResponse(200, '添加成功', [
            'id' => $id,
            'agent_key' => $agentKey,
            'install_command' => "curl -sSL http://121.196.229.4/agent/install_agent.sh | bash -s http://121.196.229.4/api/collect.php {$agentKey}"
        ]);
        break;
    
    // 编辑服务器
    case 'edit':
        requireAdmin();
        $id = intval(input('id'));
        $name = input('name');
        $host = input('host');
        $port = intval(input('port', 22));
        
        if (!$id || empty($name) || empty($host)) {
            jsonResponse(400, '参数不完整');
        }
        
        db()->execute(
            "UPDATE servers SET name = ?, host = ?, port = ? WHERE id = ?",
            [$name, $host, $port, $id]
        );
        
        logOperation('edit_server', "server#{$id}", "编辑服务器: {$name}");
        jsonResponse(200, '修改成功');
        break;
    
    // 删除服务器
    case 'delete':
        requireAdmin();
        $id = intval(input('id'));
        if (!$id) jsonResponse(400, '缺少ID');
        
        $server = db()->fetch("SELECT name FROM servers WHERE id = ?", [$id]);
        if (!$server) jsonResponse(404, '服务器不存在');
        
        // 删除关联数据
        $tables = ['metrics_cpu', 'metrics_memory', 'metrics_disk', 'metrics_disk_io', 
                    'metrics_network', 'metrics_process', 'metrics_tcp', 'metrics_ports', 
                    'system_logs', 'alert_history'];
        foreach ($tables as $table) {
            db()->execute("DELETE FROM {$table} WHERE server_id = ?", [$id]);
        }
        db()->execute("DELETE FROM servers WHERE id = ?", [$id]);
        
        logOperation('delete_server', "server#{$id}", "删除服务器: {$server['name']}");
        jsonResponse(200, '删除成功');
        break;
    
    // 获取Agent安装信息
    case 'agent_info':
        $id = intval(input('id'));
        $server = db()->fetch("SELECT id, name, host, agent_key FROM servers WHERE id = ?", [$id]);
        if (!$server) jsonResponse(404, '服务器不存在');
        
        jsonResponse(200, 'success', [
            'agent_key' => $server['agent_key'],
            'install_command' => "curl -sSL http://121.196.229.4/agent/install_agent.sh | bash -s http://121.196.229.4/api/collect.php {$server['agent_key']}"
        ]);
        break;
    
    // 重新生成Agent密钥
    case 'regenerate_key':
        requireAdmin();
        $id = intval(input('id'));
        $newKey = generateKey(32);
        
        db()->execute("UPDATE servers SET agent_key = ? WHERE id = ?", [$newKey, $id]);
        logOperation('regenerate_key', "server#{$id}", '重新生成Agent密钥');
        
        jsonResponse(200, '密钥已更新', ['agent_key' => $newKey]);
        break;
    
    default:
        jsonResponse(400, '未知操作');
}
