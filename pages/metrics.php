<?php
/**
 * 性能指标详情页
 */
require_once __DIR__ . '/../includes/init.php';
requireLogin();
define('PAGE_TITLE', '性能指标');
require_once __DIR__ . '/../includes/header.php';

$serverId = intval(input('server_id', 0));
?>

<div class="flex-between mb-2">
    <div class="form-inline">
        <div class="form-group">
            <label>选择服务器</label>
            <select id="serverSelect" class="form-control" onchange="switchServer()">
                <option value="">-- 请选择 --</option>
            </select>
        </div>
        <div class="form-group">
            <label>时间范围</label>
            <select id="timeRange" class="form-control" onchange="loadAllMetrics()">
                <option value="1">最近1小时</option>
                <option value="3">最近3小时</option>
                <option value="6">最近6小时</option>
                <option value="12">最近12小时</option>
                <option value="24" selected>最近24小时</option>
                <option value="72">最近3天</option>
                <option value="168">最近7天</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="loadAllMetrics()">🔄 刷新</button>
    </div>
</div>

<!-- 实时指标 -->
<div class="stat-cards" id="liveMetrics" style="display:none;">
    <div class="stat-card">
        <div class="stat-icon blue">⚡</div>
        <div class="stat-info">
            <h3 id="liveCpu">-</h3>
            <p>CPU使用率</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan">📊</div>
        <div class="stat-info">
            <h3 id="liveLoad">-</h3>
            <p>系统负载</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">💾</div>
        <div class="stat-info">
            <h3 id="liveMem">-</h3>
            <p>内存使用率</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">💿</div>
        <div class="stat-info">
            <h3 id="liveDisk">-</h3>
            <p>磁盘最高使用率</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">🔗</div>
        <div class="stat-info">
            <h3 id="liveTcp">-</h3>
            <p>TCP连接数</p>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs" id="metricTabs">
    <div class="tab-item active" data-tab="cpu" onclick="switchTab('cpu')">CPU & 负载</div>
    <div class="tab-item" data-tab="memory" onclick="switchTab('memory')">内存</div>
    <div class="tab-item" data-tab="disk" onclick="switchTab('disk')">磁盘</div>
    <div class="tab-item" data-tab="network" onclick="switchTab('network')">网络</div>
    <div class="tab-item" data-tab="tcp" onclick="switchTab('tcp')">TCP连接</div>
    <div class="tab-item" data-tab="process" onclick="switchTab('process')">进程</div>
    <div class="tab-item" data-tab="ports" onclick="switchTab('ports')">端口</div>
</div>

<!-- CPU -->
<div class="tab-content" id="tab-cpu">
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">CPU使用率趋势</div>
            <div class="card-body"><div id="cpuChart" class="chart-container"></div></div>
        </div>
        <div class="card">
            <div class="card-header">系统负载趋势</div>
            <div class="card-body"><div id="loadChart" class="chart-container"></div></div>
        </div>
    </div>
</div>

<!-- 内存 -->
<div class="tab-content" id="tab-memory" style="display:none;">
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">内存使用趋势</div>
            <div class="card-body"><div id="memChart" class="chart-container"></div></div>
        </div>
        <div class="card">
            <div class="card-header">内存使用率</div>
            <div class="card-body"><div id="memPctChart" class="chart-container"></div></div>
        </div>
    </div>
</div>

<!-- 磁盘 -->
<div class="tab-content" id="tab-disk" style="display:none;">
    <div class="card">
        <div class="card-header">
            <span>磁盘使用情况</span>
        </div>
        <div class="card-body" id="diskInfo">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    </div>
    <div class="card mt-2">
        <div class="card-header">磁盘使用趋势</div>
        <div class="card-body"><div id="diskChart" class="chart-container"></div></div>
    </div>
</div>

<!-- 网络 -->
<div class="tab-content" id="tab-network" style="display:none;">
    <div class="card">
        <div class="card-header">网络流量趋势</div>
        <div class="card-body"><div id="netChart" class="chart-container" style="height:350px;"></div></div>
    </div>
</div>

<!-- TCP -->
<div class="tab-content" id="tab-tcp" style="display:none;">
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">TCP连接状态分布</div>
            <div class="card-body"><div id="tcpPieChart" class="chart-container"></div></div>
        </div>
        <div class="card">
            <div class="card-header">TCP连接趋势</div>
            <div class="card-body"><div id="tcpLineChart" class="chart-container"></div></div>
        </div>
    </div>
</div>

<!-- 进程 -->
<div class="tab-content" id="tab-process" style="display:none;">
    <div class="card">
        <div class="card-header">
            <span>进程列表 (TOP 20)</span>
            <div class="btn-group">
                <button class="btn btn-sm active" onclick="loadProcesses('cpu_pct')">按CPU排序</button>
                <button class="btn btn-sm" onclick="loadProcesses('mem_pct')">按内存排序</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>PID</th><th>用户</th><th>进程名</th><th>CPU%</th><th>内存%</th><th>内存(RSS)</th><th>状态</th></tr>
                    </thead>
                    <tbody id="processTable"><tr><td colspan="7" class="text-center"><div class="spinner"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 端口 -->
<div class="tab-content" id="tab-ports" style="display:none;">
    <div class="card">
        <div class="card-header">监听端口</div>
        <div class="card-body">
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>端口</th><th>协议</th><th>进程</th><th>采集时间</th></tr></thead>
                    <tbody id="portsTable"><tr><td colspan="4" class="text-center"><div class="spinner"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const defaultServerId = <?php echo $serverId ?: 0; ?>;

// 初始化
(async function init() {
    const resp = await api('/api/servers.php', { action: 'list' });
    if (resp && resp.code === 200) {
        const select = document.getElementById('serverSelect');
        resp.data.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = `${s.name} (${s.host})`;
            if (s.id == defaultServerId) opt.selected = true;
            select.appendChild(opt);
        });
        
        if (defaultServerId || resp.data.length > 0) {
            if (!defaultServerId && resp.data.length > 0) {
                select.value = resp.data[0].id;
            }
            loadAllMetrics();
        }
    }
})();

function switchServer() {
    loadAllMetrics();
}

function getServerId() {
    return document.getElementById('serverSelect').value;
}

function getHours() {
    return document.getElementById('timeRange').value;
}

function switchTab(tab) {
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
    document.querySelector(`.tab-item[data-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    document.getElementById('tab-' + tab).style.display = 'block';
    
    // 加载对应数据
    loadTabData(tab);
}

async function loadAllMetrics() {
    const sid = getServerId();
    if (!sid) return;
    
    document.getElementById('liveMetrics').style.display = 'grid';
    loadLatest();
    loadTabData('cpu');
}

async function loadLatest() {
    const sid = getServerId();
    const resp = await api('/api/metrics.php', { action: 'latest', server_id: sid });
    if (!resp || resp.code !== 200) return;
    
    const d = resp.data;
    const cpuUsage = d.cpu ? (100 - d.cpu.cpu_idle).toFixed(1) : '-';
    document.getElementById('liveCpu').textContent = cpuUsage + '%';
    document.getElementById('liveLoad').textContent = d.cpu ? `${d.cpu.load_1} / ${d.cpu.load_5} / ${d.cpu.load_15}` : '-';
    document.getElementById('liveMem').textContent = d.memory ? d.memory.mem_usage_pct + '%' : '-';
    document.getElementById('liveTcp').textContent = d.tcp ? d.tcp.total_connections : '-';
    
    const maxDisk = d.disks && d.disks.length > 0 ? Math.max(...d.disks.map(x => x.disk_usage_pct)) : '-';
    document.getElementById('liveDisk').textContent = maxDisk + '%';
}

async function loadTabData(tab) {
    const sid = getServerId();
    const hours = getHours();
    if (!sid) return;
    
    switch (tab) {
        case 'cpu': loadCpuMetrics(sid, hours); break;
        case 'memory': loadMemMetrics(sid, hours); break;
        case 'disk': loadDiskMetrics(sid, hours); break;
        case 'network': loadNetMetrics(sid, hours); break;
        case 'tcp': loadTcpMetrics(sid, hours); break;
        case 'process': loadProcesses('cpu_pct'); break;
        case 'ports': loadPorts(); break;
    }
}

async function loadCpuMetrics(sid, hours) {
    const resp = await api('/api/metrics.php', { action: 'cpu_trend', server_id: sid, hours: hours });
    if (!resp || resp.code !== 200) return;
    const data = resp.data;
    const times = formatTimeAxis(data);
    
    createChart('cpuChart', {
        tooltip: { trigger: 'axis' },
        legend: { data: ['用户态', '系统态', 'IO等待'] },
        xAxis: { type: 'category', data: times, boundaryGap: false },
        yAxis: { type: 'value', name: '%', max: 100 },
        series: [
            { name: '用户态', type: 'line', smooth: true, areaStyle: { opacity: 0.3 }, data: data.map(d => +(d.cpu_user||0).toFixed(1)) },
            { name: '系统态', type: 'line', smooth: true, areaStyle: { opacity: 0.3 }, data: data.map(d => +(d.cpu_system||0).toFixed(1)) },
            { name: 'IO等待', type: 'line', smooth: true, data: data.map(d => +(d.cpu_iowait||0).toFixed(1)) },
        ]
    });
    
    createChart('loadChart', {
        tooltip: { trigger: 'axis' },
        legend: { data: ['1分钟', '5分钟', '15分钟'] },
        xAxis: { type: 'category', data: times, boundaryGap: false },
        yAxis: { type: 'value', name: '负载' },
        series: [
            { name: '1分钟', type: 'line', smooth: true, data: data.map(d => d.load_1) },
            { name: '5分钟', type: 'line', smooth: true, data: data.map(d => d.load_5) },
            { name: '15分钟', type: 'line', smooth: true, data: data.map(d => d.load_15) },
        ]
    });
}

async function loadMemMetrics(sid, hours) {
    const resp = await api('/api/metrics.php', { action: 'memory_trend', server_id: sid, hours: hours });
    if (!resp || resp.code !== 200) return;
    const data = resp.data;
    const times = formatTimeAxis(data);
    
    createChart('memChart', {
        tooltip: { trigger: 'axis', formatter: params => { let h = params[0].axisValueLabel+'<br>'; params.forEach(p => { h += p.marker+' '+p.seriesName+': '+formatKB(p.value)+'<br>'; }); return h; }},
        legend: { data: ['已用', '缓存', '缓冲', '空闲'] },
        xAxis: { type: 'category', data: times, boundaryGap: false },
        yAxis: { type: 'value', axisLabel: { formatter: v => formatKB(v) }},
        series: [
            { name: '已用', type: 'line', smooth: true, stack: 'mem', areaStyle:{opacity:0.6}, data: data.map(d => d.mem_used) },
            { name: '缓存', type: 'line', smooth: true, stack: 'mem', areaStyle:{opacity:0.4}, data: data.map(d => d.mem_cached) },
            { name: '缓冲', type: 'line', smooth: true, stack: 'mem', areaStyle:{opacity:0.3}, data: data.map(d => d.mem_buffers) },
            { name: '空闲', type: 'line', smooth: true, stack: 'mem', areaStyle:{opacity:0.2}, data: data.map(d => d.mem_free) },
        ]
    });
    
    createChart('memPctChart', {
        tooltip: { trigger: 'axis' },
        xAxis: { type: 'category', data: times, boundaryGap: false },
        yAxis: { type: 'value', name: '%', max: 100 },
        series: [{
            name: '内存使用率', type: 'line', smooth: true,
            areaStyle: { opacity: 0.3, color: { type: 'linear', x: 0, y: 0, x2: 0, y2: 1, colorStops: [{ offset: 0, color: '#ff4d4f' }, { offset: 1, color: '#52c41a' }] }},
            data: data.map(d => d.mem_usage_pct),
            markLine: { data: [{ yAxis: 80, name: '警告线', lineStyle: { color: '#faad14' }}, { yAxis: 90, name: '危险线', lineStyle: { color: '#ff4d4f' }}]}
        }]
    });
}

async function loadDiskMetrics(sid, hours) {
    // 当前磁盘使用
    const latestResp = await api('/api/metrics.php', { action: 'latest', server_id: sid });
    if (latestResp && latestResp.code === 200 && latestResp.data.disks) {
        const disks = latestResp.data.disks;
        document.getElementById('diskInfo').innerHTML = `
            <div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(240px,1fr));gap:16px;">
                ${disks.map(d => `
                    <div style="padding:16px;border:1px solid #e8e8e8;border-radius:6px;">
                        <div class="flex-between mb-1">
                            <strong>${d.mount_point}</strong>
                            <span class="text-muted">${d.filesystem}</span>
                        </div>
                        ${progressBar(d.disk_usage_pct)}
                        <div class="flex-between mt-1" style="font-size:12px;color:#999;">
                            <span>已用: ${formatKB(d.disk_used)}</span>
                            <span>总计: ${formatKB(d.disk_total)}</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        
        // 趋势图
        if (disks.length > 0) {
            const trendResp = await api('/api/metrics.php', { action: 'disk_trend', server_id: sid, hours: hours, mount: disks[0].mount_point });
            if (trendResp && trendResp.code === 200) {
                const data = trendResp.data;
                const times = formatTimeAxis(data);
                createChart('diskChart', {
                    tooltip: { trigger: 'axis' },
                    xAxis: { type: 'category', data: times, boundaryGap: false },
                    yAxis: { type: 'value', name: '%', max: 100 },
                    series: [{ name: '使用率', type: 'line', smooth: true, areaStyle: { opacity: 0.3 }, data: data.map(d => d.disk_usage_pct),
                        markLine: { data: [{ yAxis: 80, name: '警告', lineStyle: { color: '#faad14' }}, { yAxis: 90, name: '危险', lineStyle: { color: '#ff4d4f' }}]}
                    }]
                });
            }
        }
    }
}

async function loadNetMetrics(sid, hours) {
    const resp = await api('/api/metrics.php', { action: 'network_trend', server_id: sid, hours: hours });
    if (!resp || resp.code !== 200) return;
    const data = resp.data;
    const times = formatTimeAxis(data);
    
    createChart('netChart', {
        tooltip: { trigger: 'axis', formatter: params => { let h = params[0].axisValueLabel+'<br>'; params.forEach(p => { h += p.marker+' '+p.seriesName+': '+p.value+' Mbps<br>'; }); return h; }},
        legend: { data: ['入流量', '出流量'] },
        xAxis: { type: 'category', data: times, boundaryGap: false },
        yAxis: { type: 'value', name: 'Mbps' },
        series: [
            { name: '入流量', type: 'line', smooth: true, areaStyle: { opacity: 0.3 }, data: data.map(d => d.bandwidth_in || 0) },
            { name: '出流量', type: 'line', smooth: true, areaStyle: { opacity: 0.3 }, data: data.map(d => d.bandwidth_out || 0) },
        ]
    });
}

async function loadTcpMetrics(sid, hours) {
    const resp = await api('/api/metrics.php', { action: 'tcp_trend', server_id: sid, hours: hours });
    if (!resp || resp.code !== 200) return;
    const data = resp.data;
    
    // 最新的状态饼图
    if (data.length > 0) {
        const latest = data[data.length - 1];
        createChart('tcpPieChart', {
            tooltip: { trigger: 'item' },
            series: [{
                type: 'pie', radius: ['40%', '70%'],
                data: [
                    { name: 'ESTABLISHED', value: latest.established },
                    { name: 'TIME_WAIT', value: latest.time_wait },
                    { name: 'CLOSE_WAIT', value: latest.close_wait },
                    { name: 'LISTEN', value: latest.listen },
                    { name: 'SYN_SENT', value: latest.syn_sent },
                    { name: 'FIN_WAIT', value: (latest.fin_wait1||0) + (latest.fin_wait2||0) },
                ].filter(d => d.value > 0)
            }]
        });
    }
    
    const times = formatTimeAxis(data);
    createChart('tcpLineChart', {
        tooltip: { trigger: 'axis' },
        legend: { data: ['总连接', 'ESTABLISHED', 'TIME_WAIT'] },
        xAxis: { type: 'category', data: times, boundaryGap: false },
        yAxis: { type: 'value' },
        series: [
            { name: '总连接', type: 'line', smooth: true, data: data.map(d => d.total_connections) },
            { name: 'ESTABLISHED', type: 'line', smooth: true, data: data.map(d => d.established) },
            { name: 'TIME_WAIT', type: 'line', smooth: true, data: data.map(d => d.time_wait) },
        ]
    });
}

async function loadProcesses(sort) {
    const sid = getServerId();
    const resp = await api('/api/metrics.php', { action: 'processes', server_id: sid, sort: sort });
    if (!resp || resp.code !== 200) return;
    
    const tbody = document.getElementById('processTable');
    if (resp.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">暂无数据</td></tr>';
        return;
    }
    
    tbody.innerHTML = resp.data.map(p => `
        <tr>
            <td>${p.pid}</td>
            <td>${p.user}</td>
            <td><strong>${p.process_name}</strong></td>
            <td><span class="${p.cpu_pct > 50 ? 'text-danger' : ''}">${p.cpu_pct}%</span></td>
            <td><span class="${p.mem_pct > 50 ? 'text-danger' : ''}">${p.mem_pct}%</span></td>
            <td>${formatKB(p.mem_rss)}</td>
            <td>${p.status}</td>
        </tr>
    `).join('');
}

async function loadPorts() {
    const sid = getServerId();
    const resp = await api('/api/metrics.php', { action: 'ports', server_id: sid });
    if (!resp || resp.code !== 200) return;
    
    const tbody = document.getElementById('portsTable');
    if (resp.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">暂无数据</td></tr>';
        return;
    }
    
    tbody.innerHTML = resp.data.map(p => `
        <tr><td><strong>${p.port}</strong></td><td>${p.protocol}</td><td>${p.process_name || '-'}</td><td>${timeAgo(p.recorded_at)}</td></tr>
    `).join('');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
