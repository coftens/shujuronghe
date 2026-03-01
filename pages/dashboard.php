<?php
/**
 * 仪表盘 - 主页
 */
require_once __DIR__ . '/../includes/init.php';
requireLogin();
define('PAGE_TITLE', '仪表盘');
require_once __DIR__ . '/../includes/header.php';
?>

<!-- 统计卡片 -->
<div class="stat-cards" id="statCards">
    <div class="stat-card">
        <div class="stat-icon blue">🖥️</div>
        <div class="stat-info">
            <h3 id="totalServers">-</h3>
            <p>服务器总数</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✓</div>
        <div class="stat-info">
            <h3 id="onlineServers">-</h3>
            <p>在线服务器</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">⚠</div>
        <div class="stat-info">
            <h3 id="offlineServers">-</h3>
            <p>离线服务器</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">🔔</div>
        <div class="stat-info">
            <h3 id="activeAlerts">-</h3>
            <p>活跃告警</p>
        </div>
    </div>
</div>

<!-- 服务器总览 -->
<div class="card mb-2">
    <div class="card-header">
        <span>服务器总览</span>
        <a href="/pages/servers.php" class="btn btn-sm">管理服务器</a>
    </div>
    <div class="card-body">
        <div class="grid grid-3" id="serverCards" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
            <div class="loading"><div class="spinner"></div><p class="mt-1">加载中...</p></div>
        </div>
    </div>
</div>

<!-- 图表区 -->
<div class="grid grid-2">
    <div class="card">
        <div class="card-header">
            <span>CPU使用趋势</span>
            <select id="cpuServerSelect" class="form-control" style="width:auto;padding:4px 8px;font-size:12px;" onchange="loadCpuChart()"></select>
        </div>
        <div class="card-body">
            <div id="cpuChart" class="chart-container" style="height:280px;"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <span>内存使用趋势</span>
            <select id="memServerSelect" class="form-control" style="width:auto;padding:4px 8px;font-size:12px;" onchange="loadMemChart()"></select>
        </div>
        <div class="card-body">
            <div id="memChart" class="chart-container" style="height:280px;"></div>
        </div>
    </div>
</div>

<!-- 最近告警 -->
<div class="card mt-2">
    <div class="card-header">
        <span>最近告警</span>
        <a href="/pages/alerts.php" class="btn btn-sm">查看全部</a>
    </div>
    <div class="card-body" id="recentAlerts">
        <div class="loading"><div class="spinner"></div></div>
    </div>
</div>

<script>
let serverList = [];

async function loadDashboard() {
    const resp = await api('/api/metrics.php', { action: 'overview' });
    if (!resp || resp.code !== 200) return;
    
    const data = resp.data;
    serverList = data.servers;
    
    // 更新统计
    document.getElementById('totalServers').textContent = data.stats.total_servers;
    document.getElementById('onlineServers').textContent = data.stats.online;
    document.getElementById('offlineServers').textContent = data.stats.offline;
    document.getElementById('activeAlerts').textContent = data.stats.active_alerts;
    
    // 渲染服务器卡片
    renderServerCards(data.servers);
    
    // 填充图表选择器
    fillServerSelects(data.servers);
    
    // 加载图表
    if (data.servers.length > 0) {
        loadCpuChart();
        loadMemChart();
    }
    
    // 加载最近告警
    loadRecentAlerts();
}

function renderServerCards(servers) {
    const container = document.getElementById('serverCards');
    if (servers.length === 0) {
        container.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">🖥️</div><p>暂无服务器，请先添加服务器</p><a href="/pages/servers.php" class="btn btn-primary">添加服务器</a></div>';
        return;
    }
    
    container.innerHTML = servers.map(s => {
        const isOnline = s.is_online;
        const cpu = s.latest_cpu;
        const mem = s.latest_mem;
        const cpuUsage = cpu ? (100 - (cpu.cpu_idle || 0)).toFixed(1) : '-';
        const memUsage = mem ? (mem.mem_usage_pct || 0).toFixed(1) : '-';
        const maxDisk = s.latest_disk && s.latest_disk.length > 0 ? Math.max(...s.latest_disk.map(d => d.disk_usage_pct || 0)).toFixed(1) : '-';
        
        return `
        <div class="server-card" onclick="window.location.href='/pages/metrics.php?server_id=${s.id}'">
            <div class="server-card-header">
                <div>
                    <div class="server-card-name">${s.name}</div>
                    <div class="text-muted" style="font-size:12px;margin-top:2px;">${s.host}</div>
                </div>
                ${isOnline ? '<span class="badge badge-success"><span class="status-dot online"></span>在线</span>' : '<span class="badge badge-default"><span class="status-dot offline"></span>离线</span>'}
            </div>
            <div class="server-card-metrics">
                <div class="metric-item">
                    <div class="metric-label">CPU</div>
                    <div class="metric-value ${cpuUsage > 80 ? 'text-danger' : cpuUsage > 60 ? 'text-warning' : 'text-success'}">${cpuUsage}%</div>
                    ${cpuUsage !== '-' ? progressBar(parseFloat(cpuUsage), false) : ''}
                </div>
                <div class="metric-item">
                    <div class="metric-label">内存</div>
                    <div class="metric-value ${memUsage > 80 ? 'text-danger' : memUsage > 60 ? 'text-warning' : 'text-success'}">${memUsage}%</div>
                    ${memUsage !== '-' ? progressBar(parseFloat(memUsage), false) : ''}
                </div>
                <div class="metric-item">
                    <div class="metric-label">磁盘</div>
                    <div class="metric-value ${maxDisk > 80 ? 'text-danger' : maxDisk > 60 ? 'text-warning' : 'text-success'}">${maxDisk}%</div>
                    ${maxDisk !== '-' ? progressBar(parseFloat(maxDisk), false) : ''}
                </div>
            </div>
            <div style="text-align:right;margin-top:8px;font-size:11px;color:#999;">
                ${s.last_heartbeat ? '最后心跳: ' + timeAgo(s.last_heartbeat) : '未连接'}
            </div>
        </div>`;
    }).join('');
}

function fillServerSelects(servers) {
    const html = servers.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    document.getElementById('cpuServerSelect').innerHTML = html;
    document.getElementById('memServerSelect').innerHTML = html;
}

async function loadCpuChart() {
    const serverId = document.getElementById('cpuServerSelect').value;
    if (!serverId) return;
    
    const resp = await api('/api/metrics.php', { action: 'cpu_trend', server_id: serverId, hours: 1 });
    if (!resp || resp.code !== 200) return;
    
    const data = resp.data;
    const times = formatTimeAxis(data);
    
    createChart('cpuChart', {
        tooltip: { trigger: 'axis' },
        legend: { data: ['用户态', '系统态', 'IO等待', '负载'] },
        xAxis: { type: 'category', data: times, boundaryGap: false },
        yAxis: [
            { type: 'value', name: '%', max: 100 },
            { type: 'value', name: '负载', splitLine: { show: false } }
        ],
        series: [
            { name: '用户态', type: 'line', smooth: true, areaStyle: { opacity: 0.3 }, data: data.map(d => (d.cpu_user || 0).toFixed(1)) },
            { name: '系统态', type: 'line', smooth: true, areaStyle: { opacity: 0.3 }, data: data.map(d => (d.cpu_system || 0).toFixed(1)) },
            { name: 'IO等待', type: 'line', smooth: true, data: data.map(d => (d.cpu_iowait || 0).toFixed(1)) },
            { name: '负载', type: 'line', smooth: true, yAxisIndex: 1, data: data.map(d => d.load_1) },
        ]
    });
}

async function loadMemChart() {
    const serverId = document.getElementById('memServerSelect').value;
    if (!serverId) return;
    
    const resp = await api('/api/metrics.php', { action: 'memory_trend', server_id: serverId, hours: 1 });
    if (!resp || resp.code !== 200) return;
    
    const data = resp.data;
    const times = formatTimeAxis(data);
    
    createChart('memChart', {
        tooltip: {
            trigger: 'axis',
            formatter: function(params) {
                let html = params[0].axisValueLabel + '<br>';
                params.forEach(p => {
                    html += `${p.marker} ${p.seriesName}: ${formatKB(p.value)}<br>`;
                });
                return html;
            }
        },
        legend: { data: ['已用', '缓存', '缓冲', '空闲'] },
        xAxis: { type: 'category', data: times, boundaryGap: false },
        yAxis: { type: 'value', axisLabel: { formatter: v => formatKB(v) } },
        series: [
            { name: '已用', type: 'line', smooth: true, stack: 'mem', areaStyle: { opacity: 0.6 }, data: data.map(d => d.mem_used) },
            { name: '缓存', type: 'line', smooth: true, stack: 'mem', areaStyle: { opacity: 0.4 }, data: data.map(d => d.mem_cached) },
            { name: '缓冲', type: 'line', smooth: true, stack: 'mem', areaStyle: { opacity: 0.3 }, data: data.map(d => d.mem_buffers) },
            { name: '空闲', type: 'line', smooth: true, stack: 'mem', areaStyle: { opacity: 0.2 }, data: data.map(d => d.mem_free) },
        ]
    });
}

async function loadRecentAlerts() {
    const resp = await api('/api/alerts.php', { action: 'active' });
    const container = document.getElementById('recentAlerts');
    
    if (!resp || resp.code !== 200 || resp.data.length === 0) {
        container.innerHTML = '<div class="empty-state" style="padding:30px"><div class="empty-icon">✅</div><p>暂无活跃告警，一切正常！</p></div>';
        return;
    }
    
    container.innerHTML = resp.data.slice(0, 5).map(a => `
        <div class="alert-item ${a.severity}">
            <div class="alert-content">
                <div class="alert-title">${severityBadge(a.severity)} ${a.rule_name || '告警'}</div>
                <div class="alert-meta">${a.server_name || ''} (${a.server_host || ''}) · ${a.message || ''} · ${timeAgo(a.created_at)}</div>
            </div>
            <button class="btn btn-sm" onclick="event.stopPropagation();acknowledgeAlert(${a.id})">确认</button>
        </div>
    `).join('');
}

async function acknowledgeAlert(id) {
    const resp = await api('/api/alerts.php', { action: 'acknowledge', id: id }, 'POST');
    if (resp && resp.code === 200) {
        showToast('已确认');
        loadRecentAlerts();
    }
}

// 自动刷新
startAutoRefresh(loadDashboard, 60000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
