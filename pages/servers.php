<?php

require_once __DIR__ . '/../includes/init.php';
requireLogin();
define('PAGE_TITLE', '服务器管理');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between mb-2">
    <div></div>
    <button class="btn btn-primary" onclick="showModal('addServerModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> 添加服务器</button>
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
                提示：在被监控的服务器上以root用户执行上述命令，即可自动安装Agent并开始采集数据。<br>
                Agent每分钟自动采集一次数据并上报到平台。
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="copyInstallCmd()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> 复制安装命令</button>
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
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" style="color:#ccc"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg></div><p>暂无服务器</p></div></td></tr>';
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
                    <a href="/pages/metrics.php?server_id=${s.id}" class="btn btn-sm">监控</a>
                    <button class="btn btn-sm" onclick="showAgentInfo(${s.id})">Agent</button>
                    <button class="btn btn-sm" onclick="editServer(${JSON.stringify(s).replace(/"/g, '&quot;')})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteServer(${s.id}, '${s.name}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
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
document.getElementById('addServerModal').addEventListener('click', function(e) {
    if (e.target === this) hideModal('addServerModal');
});
loadServers();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
