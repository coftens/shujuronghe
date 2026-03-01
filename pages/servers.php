<?php
/**
 * 服务器管理页面
 */
require_once __DIR__ . '/../includes/init.php';
requireLogin();
define('PAGE_TITLE', '服务器管理');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between mb-2">
    <div></div>
    <button class="btn btn-primary" onclick="showModal('addServerModal')">➕ 添加服务器</button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>名称</th>
                        <th>IP地址</th>
                        <th>状态</th>
                        <th>系统信息</th>
                        <th>最后心跳</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="serverTable">
                    <tr><td colspan="7" class="text-center"><div class="spinner"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 添加服务器模态框 -->
<div class="modal-overlay" id="addServerModal">
    <div class="modal">
        <div class="modal-header">
            <span id="modalTitle">添加服务器</span>
            <button class="modal-close" onclick="hideModal('addServerModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editServerId" value="0">
            <div class="form-group">
                <label>服务器名称</label>
                <input type="text" id="serverName" class="form-control" placeholder="例如: Web服务器">
            </div>
            <div class="form-group">
                <label>IP地址</label>
                <input type="text" id="serverHost" class="form-control" placeholder="例如: 192.168.1.100">
            </div>
            <div class="form-group">
                <label>SSH端口</label>
                <input type="number" id="serverPort" class="form-control" value="22">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="hideModal('addServerModal')">取消</button>
            <button class="btn btn-primary" onclick="saveServer()">保存</button>
        </div>
    </div>
</div>

<!-- Agent信息模态框 -->
<div class="modal-overlay" id="agentInfoModal">
    <div class="modal" style="width:640px;">
        <div class="modal-header">
            <span>Agent安装信息</span>
            <button class="modal-close" onclick="hideModal('agentInfoModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Agent密钥</label>
                <input type="text" id="agentKeyDisplay" class="form-control" readonly style="font-family:monospace;">
            </div>
            <div class="form-group">
                <label>安装命令（在被监控服务器上执行）</label>
                <textarea id="installCmdDisplay" class="form-control" readonly rows="3" style="font-family:monospace;font-size:12px;"></textarea>
            </div>
            <p class="text-muted" style="font-size:12px;">
                💡 在被监控的服务器上以root用户执行上述命令，即可自动安装Agent并开始采集数据。<br>
                Agent每分钟自动采集一次数据并上报到平台。
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="copyInstallCmd()">📋 复制安装命令</button>
            <button class="btn" onclick="hideModal('agentInfoModal')">关闭</button>
        </div>
    </div>
</div>

<script>
async function loadServers() {
    const resp = await api('/api/servers.php', { action: 'list' });
    if (!resp || resp.code !== 200) return;
    
    const tbody = document.getElementById('serverTable');
    
    if (resp.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-icon">🖥️</div><p>暂无服务器</p></div></td></tr>';
        return;
    }
    
    tbody.innerHTML = resp.data.map(s => `
        <tr>
            <td>${s.id}</td>
            <td><strong>${s.name}</strong></td>
            <td><code>${s.host}</code></td>
            <td>${s.is_online ? '<span class="badge badge-success"><span class="status-dot online"></span>在线</span>' : '<span class="badge badge-default"><span class="status-dot offline"></span>离线</span>'}</td>
            <td><span class="truncate">${s.os_info || '-'}</span></td>
            <td>${s.last_heartbeat ? timeAgo(s.last_heartbeat) : '未连接'}</td>
            <td>
                <div class="btn-group">
                    <a href="/pages/metrics.php?server_id=${s.id}" class="btn btn-sm">📊 监控</a>
                    <button class="btn btn-sm" onclick="showAgentInfo(${s.id})">🔑 Agent</button>
                    <button class="btn btn-sm" onclick="editServer(${JSON.stringify(s).replace(/"/g, '&quot;')})">✏️</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteServer(${s.id}, '${s.name}')">🗑️</button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function saveServer() {
    const id = document.getElementById('editServerId').value;
    const params = {
        action: id > 0 ? 'edit' : 'add',
        id: id,
        name: document.getElementById('serverName').value,
        host: document.getElementById('serverHost').value,
        port: document.getElementById('serverPort').value,
    };
    
    if (!params.name || !params.host) {
        showToast('请填写完整信息', 'error');
        return;
    }
    
    const resp = await api('/api/servers.php', params, 'POST');
    if (resp && resp.code === 200) {
        showToast(resp.msg);
        hideModal('addServerModal');
        loadServers();
        
        // 新添加的显示Agent信息
        if (id == 0 && resp.data) {
            document.getElementById('agentKeyDisplay').value = resp.data.agent_key;
            document.getElementById('installCmdDisplay').value = resp.data.install_command;
            showModal('agentInfoModal');
        }
    } else {
        showToast(resp?.msg || '操作失败', 'error');
    }
}

function editServer(server) {
    document.getElementById('editServerId').value = server.id;
    document.getElementById('serverName').value = server.name;
    document.getElementById('serverHost').value = server.host;
    document.getElementById('serverPort').value = server.port;
    document.getElementById('modalTitle').textContent = '编辑服务器';
    showModal('addServerModal');
}

async function deleteServer(id, name) {
    if (!confirmAction(`确定要删除服务器 "${name}" 及其所有监控数据吗？此操作不可恢复！`)) return;
    
    const resp = await api('/api/servers.php', { action: 'delete', id: id }, 'POST');
    if (resp && resp.code === 200) {
        showToast('删除成功');
        loadServers();
    }
}

async function showAgentInfo(id) {
    const resp = await api('/api/servers.php', { action: 'agent_info', id: id });
    if (resp && resp.code === 200) {
        document.getElementById('agentKeyDisplay').value = resp.data.agent_key;
        document.getElementById('installCmdDisplay').value = resp.data.install_command;
        showModal('agentInfoModal');
    }
}

function copyInstallCmd() {
    const textarea = document.getElementById('installCmdDisplay');
    textarea.select();
    document.execCommand('copy');
    showToast('已复制到剪贴板');
}

// 重置表单
document.getElementById('addServerModal').addEventListener('click', function(e) {
    if (e.target === this) hideModal('addServerModal');
});

// 初始化
loadServers();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
