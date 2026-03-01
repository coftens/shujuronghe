<?php
/**
 * 系统日志页面
 */
require_once __DIR__ . '/../includes/init.php';
requireLogin();
define('PAGE_TITLE', '系统日志');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between mb-2">
    <div class="form-inline">
        <div class="form-group">
            <select id="logServer" class="form-control" onchange="loadLogs()">
                <option value="">全部服务器</option>
            </select>
        </div>
        <div class="form-group">
            <select id="logLevel" class="form-control" onchange="loadLogs()">
                <option value="">全部级别</option>
                <option value="emergency">紧急</option>
                <option value="alert">告警</option>
                <option value="critical">严重</option>
                <option value="error">错误</option>
                <option value="warning">警告</option>
                <option value="notice">通知</option>
                <option value="info">信息</option>
                <option value="debug">调试</option>
            </select>
        </div>
        <div class="form-group">
            <input type="text" id="logKeyword" class="form-control" placeholder="搜索关键字..." style="width:200px;">
        </div>
        <button class="btn btn-primary" onclick="loadLogs()">🔍 搜索</button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>服务器</th>
                        <th>级别</th>
                        <th>来源</th>
                        <th>内容</th>
                    </tr>
                </thead>
                <tbody id="logTable">
                    <tr><td colspan="5" class="text-center"><div class="spinner"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer" id="logPagination"></div>
</div>

<script>
// 加载服务器列表
(async function init() {
    const resp = await api('/api/servers.php', { action: 'list' });
    if (resp && resp.code === 200) {
        const select = document.getElementById('logServer');
        resp.data.forEach(s => {
            select.innerHTML += `<option value="${s.id}">${s.name}</option>`;
        });
    }
    loadLogs();
})();

async function loadLogs(page = 1) {
    const resp = await api('/api/logs.php', {
        action: 'list',
        page: page,
        server_id: document.getElementById('logServer').value,
        level: document.getElementById('logLevel').value,
        keyword: document.getElementById('logKeyword').value,
    });
    
    if (!resp || resp.code !== 200) return;
    
    const tbody = document.getElementById('logTable');
    const data = resp.data;
    
    const levelColors = {
        emergency: '#721c24', alert: '#ff4d4f', critical: '#ff4d4f',
        error: '#ff7875', warning: '#faad14', notice: '#13c2c2',
        info: '#1890ff', debug: '#8c8c8c'
    };
    
    if (data.list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">暂无日志</td></tr>';
        document.getElementById('logPagination').innerHTML = '';
        return;
    }
    
    tbody.innerHTML = data.list.map(log => `
        <tr>
            <td class="nowrap">${log.recorded_at}</td>
            <td>${log.server_name || '-'}</td>
            <td><span class="badge" style="background:${levelColors[log.level] || '#ccc'};color:#fff;">${log.level}</span></td>
            <td>${log.source || '-'}</td>
            <td style="max-width:500px;word-break:break-all;">${log.message || '-'}</td>
        </tr>
    `).join('');
    
    // 分页
    const p = data.pagination;
    let phtml = '';
    if (p.total_pages > 1) {
        if (p.current_page > 1) phtml += `<a onclick="loadLogs(${p.current_page-1})">上一页</a>`;
        for (let i = Math.max(1, p.current_page-3); i <= Math.min(p.total_pages, p.current_page+3); i++) {
            phtml += p.current_page === i ? `<span class="current">${i}</span>` : `<a onclick="loadLogs(${i})">${i}</a>`;
        }
        if (p.current_page < p.total_pages) phtml += `<a onclick="loadLogs(${p.current_page+1})">下一页</a>`;
    }
    document.getElementById('logPagination').innerHTML = `<div class="pagination">${phtml}</div><div class="text-center text-muted mt-1" style="font-size:12px;">共 ${p.total} 条</div>`;
}

// 回车搜索
document.getElementById('logKeyword').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') loadLogs();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
