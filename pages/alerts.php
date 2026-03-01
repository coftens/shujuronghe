<?php

require_once __DIR__ . '/../includes/init.php';
requireLogin();
define('PAGE_TITLE', '故障预警');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="tabs mb-2">
    <div class="tab-item active" onclick="switchAlertTab('history')">告警历史</div>
    <div class="tab-item" onclick="switchAlertTab('rules')">告警规则</div>
    <div class="tab-item" onclick="switchAlertTab('stats')">告警统计</div>
</div>

<div id="tabHistory">
    <div class="flex-between mb-2">
        <div class="form-inline">
            <div class="form-group">
                <select id="filterStatus" class="form-control" onchange="loadAlertHistory()">
                    <option value="">全部状态</option>
                    <option value="active">活跃</option>
                    <option value="acknowledged">已确认</option>
                    <option value="resolved">已解决</option>
                </select>
            </div>
            <div class="form-group">
                <select id="filterSeverity" class="form-control" onchange="loadAlertHistory()">
                    <option value="">全部级别</option>
                    <option value="critical">紧急</option>
                    <option value="danger">严重</option>
                    <option value="warning">警告</option>
                    <option value="info">提示</option>
                </select>
            </div>
            <button class="btn" onclick="loadAlertHistory()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> 刷新</button>
        </div>
        <button class="btn btn-success" onclick="resolveAll()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> 全部解决</button>
    </div>
    
    <div class="card">
        <div class="card-body" id="alertList">
            <div class="loading"><div class="spinner"></div></div>
        </div>
        <div class="card-footer" id="alertPagination"></div>
    </div>
</div>

<div id="tabRules" style="display:none;">
    <div class="flex-between mb-2">
        <div></div>
        <button class="btn btn-primary" onclick="showRuleModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> 添加规则</button>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>规则名称</th>
                            <th>指标类型</th>
                            <th>条件</th>
                            <th>阈值</th>
                            <th>严重级别</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="rulesTable">
                        <tr><td colspan="7" class="text-center"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="tabStats" style="display:none;">
    <div class="grid grid-2 mb-2">
        <div class="card">
            <div class="card-header">告警趋势（最近7天）</div>
            <div class="card-body"><div id="alertTrendChart" class="chart-container"></div></div>
        </div>
        <div class="card">
            <div class="card-header">告警类型分布</div>
            <div class="card-body"><div id="alertTypeChart" class="chart-container"></div></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">各服务器告警统计</div>
        <div class="card-body"><div id="alertServerChart" class="chart-container" style="height:300px;"></div></div>
    </div>
</div>

<div class="modal-overlay" id="ruleModal">
    <div class="modal" style="width:560px;">
        <div class="modal-header">
            <span id="ruleModalTitle">添加告警规则</span>
            <button class="modal-close" onclick="hideModal('ruleModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ruleId" value="0">
            <div class="form-group">
                <label>规则名称</label>
                <input type="text" id="ruleName" class="form-control" placeholder="例如: CPU使用率过高">
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label>指标类型</label>
                    <select id="ruleMetricType" class="form-control" onchange="updateFieldOptions()">
                        <option value="cpu">CPU</option>
                        <option value="memory">内存</option>
                        <option value="disk">磁盘</option>
                        <option value="network">网络</option>
                        <option value="tcp">TCP连接</option>
                        <option value="server">服务器</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>指标字段</label>
                    <select id="ruleMetricField" class="form-control">
                        <option value="cpu_usage">CPU使用率</option>
                        <option value="load_1">1分钟负载</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label>条件</label>
                    <select id="ruleCondition" class="form-control">
                        <option value="gt">大于 ></option>
                        <option value="gte">大于等于 >=</option>
                        <option value="lt">小于 <</option>
                        <option value="lte">小于等于 <=</option>
                        <option value="eq">等于 =</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>阈值</label>
                    <input type="number" id="ruleThreshold" class="form-control" step="0.1" value="90">
                </div>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label>持续次数</label>
                    <input type="number" id="ruleDuration" class="form-control" value="3" min="1">
                    <small class="text-muted">连续N次超阈值才触发</small>
                </div>
                <div class="form-group">
                    <label>严重级别</label>
                    <select id="ruleSeverity" class="form-control">
                        <option value="info">提示</option>
                        <option value="warning" selected>警告</option>
                        <option value="danger">严重</option>
                        <option value="critical">紧急</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>冷却时间（秒）</label>
                <input type="number" id="ruleCooldown" class="form-control" value="300">
                <small class="text-muted">同一规则两次告警之间的最短间隔</small>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="hideModal('ruleModal')">取消</button>
            <button class="btn btn-primary" onclick="saveRule()">保存</button>
        </div>
    </div>
</div>

<script>
const fieldOptions = {
    cpu: [{ v: 'cpu_usage', l: 'CPU使用率(%)' }, { v: 'load_1', l: '1分钟负载' }, { v: 'cpu_iowait', l: 'IO等待(%)' }],
    memory: [{ v: 'mem_usage_pct', l: '内存使用率(%)' }, { v: 'swap_used_pct', l: 'Swap使用率(%)' }],
    disk: [{ v: 'disk_usage_pct', l: '磁盘使用率(%)' }, { v: 'inode_usage_pct', l: 'inode使用率(%)' }],
    network: [{ v: 'bandwidth_in', l: '入带宽(Mbps)' }, { v: 'bandwidth_out', l: '出带宽(Mbps)' }],
    tcp: [{ v: 'total_connections', l: '总连接数' }, { v: 'time_wait', l: 'TIME_WAIT数' }, { v: 'close_wait', l: 'CLOSE_WAIT数' }],
    server: [{ v: 'offline', l: '离线检测' }],
};

function updateFieldOptions() {
    const type = document.getElementById('ruleMetricType').value;
    const select = document.getElementById('ruleMetricField');
    select.innerHTML = (fieldOptions[type] || []).map(f => `<option value="${f.v}">${f.l}</option>`).join('');
}

let currentAlertPage = 1;

function switchAlertTab(tab) {
    document.querySelectorAll('.tabs .tab-item').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tabHistory').style.display = tab === 'history' ? 'block' : 'none';
    document.getElementById('tabRules').style.display = tab === 'rules' ? 'block' : 'none';
    document.getElementById('tabStats').style.display = tab === 'stats' ? 'block' : 'none';
    
    if (tab === 'rules') loadRules();
    if (tab === 'stats') loadAlertStats();
}

async function loadAlertHistory(page = 1) {
    currentAlertPage = page;
    const resp = await api('/api/alerts.php', {
        action: 'list',
        page: page,
        status: document.getElementById('filterStatus').value,
        severity: document.getElementById('filterSeverity').value,
    });
    
    if (!resp || resp.code !== 200) return;
    
    const container = document.getElementById('alertList');
    const data = resp.data;
    
    if (data.list.length === 0) {
        container.innerHTML = '<div class="empty-state" style="padding:30px"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" style="color:#52c41a"><polyline points="20 6 9 17 4 12"/></svg></div><p>暂无告警记录</p></div>';
        document.getElementById('alertPagination').innerHTML = '';
        return;
    }
    
    container.innerHTML = data.list.map(a => {
        const statusMap = { active: '活跃', acknowledged: '已确认', resolved: '已解决' };
        const statusBadgeMap = { active: 'badge-danger', acknowledged: 'badge-warning', resolved: 'badge-success' };
        
        return `
        <div class="alert-item ${a.severity}" style="margin-bottom:8px;">
            <div class="alert-content">
                <div class="alert-title">
                    ${severityBadge(a.severity)}
                    ${a.rule_name || '告警'}
                    <span class="badge ${statusBadgeMap[a.status]}" style="margin-left:8px;">${statusMap[a.status]}</span>
                </div>
                <div class="alert-meta" style="margin-top:4px;">
                    ${a.server_name || ''} (${a.server_host || ''})
                    ${a.message ? ' · ' + a.message : ''}
                    ${a.metric_value ? ' · 当前值: ' + a.metric_value + ' / 阈值: ' + a.threshold : ''}
                    · ${timeAgo(a.created_at)}
                </div>
            </div>
            <div class="btn-group">
                ${a.status === 'active' ? `<button class="btn btn-sm btn-warning" onclick="ackAlert(${a.id})">确认</button>` : ''}
                ${a.status !== 'resolved' ? `<button class="btn btn-sm btn-success" onclick="resolveAlert(${a.id})">解决</button>` : ''}
            </div>
        </div>`;
    }).join('');
    const p = data.pagination;
    let phtml = '';
    if (p.total_pages > 1) {
        if (p.current_page > 1) phtml += `<a onclick="loadAlertHistory(${p.current_page-1})">上一页</a>`;
        for (let i = 1; i <= p.total_pages && i <= 10; i++) {
            phtml += p.current_page === i ? `<span class="current">${i}</span>` : `<a onclick="loadAlertHistory(${i})">${i}</a>`;
        }
        if (p.current_page < p.total_pages) phtml += `<a onclick="loadAlertHistory(${p.current_page+1})">下一页</a>`;
    }
    document.getElementById('alertPagination').innerHTML = `<div class="pagination">${phtml}</div><div class="text-center text-muted mt-1" style="font-size:12px;">共 ${p.total} 条</div>`;
}

async function ackAlert(id) {
    const resp = await api('/api/alerts.php', { action: 'acknowledge', id: id }, 'POST');
    if (resp && resp.code === 200) { showToast('已确认'); loadAlertHistory(currentAlertPage); }
}

async function resolveAlert(id) {
    const resp = await api('/api/alerts.php', { action: 'resolve', id: id }, 'POST');
    if (resp && resp.code === 200) { showToast('已解决'); loadAlertHistory(currentAlertPage); }
}

async function resolveAll() {
    if (!confirmAction('确定要解决所有活跃告警吗？')) return;
    const resp = await api('/api/alerts.php', { action: 'resolve_all' }, 'POST');
    if (resp && resp.code === 200) { showToast('已全部解决'); loadAlertHistory(); }
}

async function loadRules() {
    const resp = await api('/api/alerts.php', { action: 'rules' });
    if (!resp || resp.code !== 200) return;
    
    const tbody = document.getElementById('rulesTable');
    const condMap = { gt: '>', gte: '>=', lt: '<', lte: '<=', eq: '=', neq: '!=' };
    
    tbody.innerHTML = resp.data.map(r => `
        <tr>
            <td><strong>${r.name}</strong></td>
            <td>${r.metric_type}</td>
            <td>${r.metric_field} ${condMap[r.condition] || r.condition}</td>
            <td>${r.threshold}</td>
            <td>${severityBadge(r.severity)}</td>
            <td>
                <label style="cursor:pointer;">
                    <input type="checkbox" ${r.enabled ? 'checked' : ''} onchange="toggleRule(${r.id})">
                    ${r.enabled ? '启用' : '禁用'}
                </label>
            </td>
            <td>
                <div class="btn-group">
                    <button class="btn btn-sm" onclick="editRule(${JSON.stringify(r).replace(/"/g, '&quot;')})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteRule(${r.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
                </div>
            </td>
        </tr>
    `).join('');
}

function showRuleModal(rule = null) {
    document.getElementById('ruleId').value = rule ? rule.id : 0;
    document.getElementById('ruleName').value = rule ? rule.name : '';
    document.getElementById('ruleMetricType').value = rule ? rule.metric_type : 'cpu';
    updateFieldOptions();
    if (rule) document.getElementById('ruleMetricField').value = rule.metric_field;
    document.getElementById('ruleCondition').value = rule ? rule.condition : 'gt';
    document.getElementById('ruleThreshold').value = rule ? rule.threshold : 90;
    document.getElementById('ruleDuration').value = rule ? rule.duration : 3;
    document.getElementById('ruleSeverity').value = rule ? rule.severity : 'warning';
    document.getElementById('ruleCooldown').value = rule ? rule.cooldown : 300;
    document.getElementById('ruleModalTitle').textContent = rule ? '编辑告警规则' : '添加告警规则';
    showModal('ruleModal');
}

function editRule(rule) { showRuleModal(rule); }

async function saveRule() {
    const resp = await api('/api/alerts.php', {
        action: 'save_rule',
        id: document.getElementById('ruleId').value,
        name: document.getElementById('ruleName').value,
        metric_type: document.getElementById('ruleMetricType').value,
        metric_field: document.getElementById('ruleMetricField').value,
        condition: document.getElementById('ruleCondition').value,
        threshold: document.getElementById('ruleThreshold').value,
        duration: document.getElementById('ruleDuration').value,
        severity: document.getElementById('ruleSeverity').value,
        cooldown: document.getElementById('ruleCooldown').value,
    }, 'POST');
    
    if (resp && resp.code === 200) { showToast('保存成功'); hideModal('ruleModal'); loadRules(); }
    else showToast(resp?.msg || '保存失败', 'error');
}

async function toggleRule(id) {
    await api('/api/alerts.php', { action: 'toggle_rule', id: id }, 'POST');
    loadRules();
}

async function deleteRule(id) {
    if (!confirmAction('确定删除此规则？')) return;
    const resp = await api('/api/alerts.php', { action: 'delete_rule', id: id }, 'POST');
    if (resp && resp.code === 200) { showToast('已删除'); loadRules(); }
}

async function loadAlertStats() {
    const resp = await api('/api/alerts.php', { action: 'stats', days: 7 });
    if (!resp || resp.code !== 200) return;
    const data = resp.data;
    const dailyData = {};
    (data.daily || []).forEach(d => {
        if (!dailyData[d.date]) dailyData[d.date] = { warning: 0, danger: 0, critical: 0 };
        dailyData[d.date][d.severity] = d.count;
    });
    const dates = Object.keys(dailyData).sort();
    
    createChart('alertTrendChart', {
        tooltip: { trigger: 'axis' },
        legend: { data: ['警告', '严重', '紧急'] },
        xAxis: { type: 'category', data: dates },
        yAxis: { type: 'value' },
        series: [
            { name: '警告', type: 'bar', stack: 'alert', data: dates.map(d => dailyData[d]?.warning || 0) },
            { name: '严重', type: 'bar', stack: 'alert', data: dates.map(d => dailyData[d]?.danger || 0) },
            { name: '紧急', type: 'bar', stack: 'alert', data: dates.map(d => dailyData[d]?.critical || 0) },
        ]
    });
    createChart('alertTypeChart', {
        tooltip: { trigger: 'item' },
        series: [{
            type: 'pie', radius: ['40%', '70%'],
            data: (data.by_type || []).map(t => ({ name: t.metric_type, value: t.count }))
        }]
    });
    createChart('alertServerChart', {
        tooltip: { trigger: 'axis' },
        xAxis: { type: 'category', data: (data.by_server || []).map(s => s.name || '未知') },
        yAxis: { type: 'value' },
        series: [{ type: 'bar', data: (data.by_server || []).map(s => s.count), itemStyle: { borderRadius: [4, 4, 0, 0] } }]
    });
}
loadAlertHistory();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
