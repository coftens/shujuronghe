<?php
/**
 * 性能分析页面
 */
require_once __DIR__ . '/../includes/init.php';
requireLogin();
define('PAGE_TITLE', '性能分析');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between mb-2">
    <div class="form-inline">
        <div class="form-group">
            <label>选择服务器</label>
            <select id="serverSelect" class="form-control" onchange="loadAnalysis()">
                <option value="">-- 请选择 --</option>
            </select>
        </div>
        <div class="form-group">
            <label>分析周期</label>
            <select id="analysisPeriod" class="form-control" onchange="loadAnalysis()">
                <option value="1">最近1小时</option>
                <option value="6">最近6小时</option>
                <option value="24" selected>最近24小时</option>
                <option value="72">最近3天</option>
                <option value="168">最近7天</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="loadAnalysis()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> 分析</button>
    </div>
</div>

<!-- 性能评分 -->
<div class="grid grid-2 mb-2">
    <div class="card">
        <div class="card-header">综合性能评分</div>
        <div class="card-body text-center" id="scoreSection">
            <div class="empty-state"><p>请选择服务器进行分析</p></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">各维度评分</div>
        <div class="card-body">
            <div id="radarChart" class="chart-container" style="height:280px;"></div>
        </div>
    </div>
</div>

<!-- 瓶颈分析 -->
<div class="card mb-2">
    <div class="card-header">
        <span>瓶颈分析</span>
    </div>
    <div class="card-body" id="bottleneckSection">
        <div class="empty-state"><p>请选择服务器进行分析</p></div>
    </div>
</div>

<!-- 趋势预测 -->
<div class="card mb-2">
    <div class="card-header">
        <span>趋势预测</span>
    </div>
    <div class="card-body" id="predictionSection">
        <div class="empty-state"><p>请选择服务器进行分析</p></div>
    </div>
</div>

<!-- 高峰时段 & TOP进程 -->
<div class="grid grid-2">
    <div class="card">
        <div class="card-header">高峰时段分析（最近7天）</div>
        <div class="card-body">
            <div id="peakChart" class="chart-container" style="height:280px;"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">资源消耗 TOP 进程</div>
        <div class="card-body" id="topProcessSection">
            <div class="empty-state"><p>请选择服务器</p></div>
        </div>
    </div>
</div>

<script>
// 初始化
(async function init() {
    const resp = await api('/api/servers.php', { action: 'list' });
    if (resp && resp.code === 200) {
        const select = document.getElementById('serverSelect');
        resp.data.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = `${s.name} (${s.host})`;
            select.appendChild(opt);
        });
    }
})();

async function loadAnalysis() {
    const sid = document.getElementById('serverSelect').value;
    const hours = document.getElementById('analysisPeriod').value;
    if (!sid) return;
    
    // 并行加载所有分析数据
    const [scoreResp, bottleneckResp, predResp, peakResp, procResp] = await Promise.all([
        api('/api/analysis.php', { action: 'score', server_id: sid, hours: hours }),
        api('/api/analysis.php', { action: 'bottleneck', server_id: sid, hours: hours }),
        api('/api/analysis.php', { action: 'prediction', server_id: sid }),
        api('/api/analysis.php', { action: 'peak_hours', server_id: sid }),
        api('/api/analysis.php', { action: 'top_processes', server_id: sid, hours: hours }),
    ]);
    
    // 渲染评分
    if (scoreResp && scoreResp.code === 200) {
        renderScore(scoreResp.data);
    }
    
    // 渲染瓶颈
    if (bottleneckResp && bottleneckResp.code === 200) {
        renderBottlenecks(bottleneckResp.data);
    }
    
    // 渲染预测
    if (predResp && predResp.code === 200) {
        renderPredictions(predResp.data);
    }
    
    // 渲染高峰时段
    if (peakResp && peakResp.code === 200) {
        renderPeakHours(peakResp.data);
    }
    
    // 渲染TOP进程
    if (procResp && procResp.code === 200) {
        renderTopProcesses(procResp.data);
    }
}

function renderScore(score) {
    const section = document.getElementById('scoreSection');
    section.innerHTML = `
        <div class="score-circle grade-${score.grade}">
            <div class="score-value">${score.overall}</div>
            <div class="score-grade">${score.grade}级</div>
        </div>
        <div class="grid grid-4" style="text-align:center;margin-top:16px;">
            <div>
                <div class="text-muted" style="font-size:12px;">CPU评分</div>
                <div style="font-size:20px;font-weight:700;">${score.cpu_score}</div>
                <div class="text-muted" style="font-size:11px;">均值 ${score.details.avg_cpu}%</div>
            </div>
            <div>
                <div class="text-muted" style="font-size:12px;">负载评分</div>
                <div style="font-size:20px;font-weight:700;">${score.load_score}</div>
                <div class="text-muted" style="font-size:11px;">均值 ${score.details.avg_load}</div>
            </div>
            <div>
                <div class="text-muted" style="font-size:12px;">内存评分</div>
                <div style="font-size:20px;font-weight:700;">${score.mem_score}</div>
                <div class="text-muted" style="font-size:11px;">均值 ${score.details.avg_mem}%</div>
            </div>
            <div>
                <div class="text-muted" style="font-size:12px;">磁盘评分</div>
                <div style="font-size:20px;font-weight:700;">${score.disk_score}</div>
                <div class="text-muted" style="font-size:11px;">最高 ${score.details.max_disk}%</div>
            </div>
        </div>
    `;
    
    // 雷达图
    createChart('radarChart', {
        legend: { show: false },
        radar: {
            center: ['50%', '54%'],
            radius: '68%',
            indicator: [
                { name: 'CPU', max: 100 },
                { name: '负载', max: 100 },
                { name: '内存', max: 100 },
                { name: '磁盘', max: 100 },
            ]
        },
        series: [{
            type: 'radar',
            data: [{ value: [score.cpu_score, score.load_score, score.mem_score, score.disk_score], name: '性能评分' }],
            areaStyle: { opacity: 0.3 }
        }]
    });
}

function renderBottlenecks(issues) {
    const section = document.getElementById('bottleneckSection');
    
    if (issues.length === 0) {
        section.innerHTML = '<div class="empty-state" style="padding:20px"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="48" height="48" style="color:#52c41a"><polyline points="20 6 9 17 4 12"/></svg></div><p>未发现性能瓶颈，服务器运行良好！</p></div>';
        return;
    }
    
    section.innerHTML = issues.map(issue => `
        <div class="alert-item ${issue.severity}" style="margin-bottom:10px;">
            <div class="alert-content">
                <div class="alert-title">${severityBadge(issue.severity)} ${issue.title}</div>
                <div class="alert-meta" style="margin-top:4px;">${issue.desc}</div>
            </div>
        </div>
    `).join('');
}

function renderPredictions(predictions) {
    const section = document.getElementById('predictionSection');
    
    if (predictions.length === 0) {
        section.innerHTML = '<div class="empty-state" style="padding:20px"><p>数据不足，无法预测趋势</p></div>';
        return;
    }
    
    const trendIcons = { rising: '↑', falling: '↓', stable: '→' };
    const trendLabels = { rising: '上升', falling: '下降', stable: '稳定' };
    const typeLabels = { disk: '磁盘', memory: '内存', cpu: 'CPU' };
    
    section.innerHTML = `
        <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
            ${predictions.map(p => `
                <div style="padding:16px;border:1px solid #e8e8e8;border-radius:6px;${p.warning ? 'border-color:#ff4d4f;background:#fff2f0;' : ''}">
                    <div class="flex-between">
                        <strong>${typeLabels[p.type] || p.type} ${p.mount || ''}</strong>
                        <span>${trendIcons[p.trend]} ${trendLabels[p.trend]}</span>
                    </div>
                    <div style="margin-top:8px;font-size:13px;">
                        <div>当前: <strong>${p.current}%</strong></div>
                        <div>日均变化: <strong>${p.daily_growth > 0 ? '+' : ''}${p.daily_growth}%</strong></div>
                        ${p.days_to_full ? `<div class="text-danger" style="margin-top:4px;"><strong>警告：</strong>预计 <strong>${p.days_to_full}</strong> 天后满</div>` : ''}
                        ${p.warning ? `<div class="text-danger" style="margin-top:4px;"><strong>警告：</strong>${p.warning}</div>` : ''}
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderPeakHours(data) {
    if (!data.cpu_by_hour || data.cpu_by_hour.length === 0) return;
    
    const hours = Array.from({length: 24}, (_, i) => `${String(i).padStart(2, '0')}:00`);
    const cpuData = new Array(24).fill(0);
    const memData = new Array(24).fill(0);
    
    data.cpu_by_hour.forEach(d => { cpuData[d.hour] = +((d.avg_cpu || 0).toFixed(1)); });
    if (data.mem_by_hour) {
        data.mem_by_hour.forEach(d => { memData[d.hour] = +((d.avg_mem || 0).toFixed(1)); });
    }
    
    createChart('peakChart', {
        tooltip: { trigger: 'axis' },
        legend: { data: ['CPU均值', '内存均值'] },
        xAxis: { type: 'category', data: hours },
        yAxis: { type: 'value', name: '%', max: 100 },
        series: [
            { name: 'CPU均值', type: 'bar', data: cpuData, itemStyle: { borderRadius: [4, 4, 0, 0] } },
            { name: '内存均值', type: 'line', smooth: true, data: memData },
        ]
    });
}

function renderTopProcesses(data) {
    const section = document.getElementById('topProcessSection');
    
    let html = '<h4 style="margin-bottom:8px;">CPU消耗TOP</h4>';
    html += '<table><thead><tr><th>进程</th><th>平均CPU%</th><th>最高CPU%</th></tr></thead><tbody>';
    (data.top_cpu || []).forEach(p => {
        html += `<tr><td><strong>${p.process_name}</strong></td><td>${(+p.avg_cpu).toFixed(1)}%</td><td>${(+p.max_cpu).toFixed(1)}%</td></tr>`;
    });
    html += '</tbody></table>';
    
    html += '<h4 style="margin:16px 0 8px;">内存消耗TOP</h4>';
    html += '<table><thead><tr><th>进程</th><th>平均内存%</th><th>平均RSS</th></tr></thead><tbody>';
    (data.top_mem || []).forEach(p => {
        html += `<tr><td><strong>${p.process_name}</strong></td><td>${(+p.avg_mem).toFixed(1)}%</td><td>${formatKB(+p.avg_rss)}</td></tr>`;
    });
    html += '</tbody></table>';
    
    section.innerHTML = html;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
