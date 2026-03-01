function showToast(msg, type = 'success', duration = 3000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const icons = { success: '✓', error: '✗', warning: '⚠' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${icons[type] || 'ℹ'}</span><span>${msg}</span>`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
async function api(url, params = {}, method = 'GET') {
    try {
        let options = { method, headers: {} };
        
        if (method === 'GET') {
            const query = new URLSearchParams(params).toString();
            if (query) url += '?' + query;
        } else {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.body = new URLSearchParams(params).toString();
        }
        
        const resp = await fetch(url, options);
        const data = await resp.json();
        
        if (data.code === 401) {
            window.location.href = '/pages/login.php';
            return null;
        }
        
        return data;
    } catch (e) {
        console.error('API Error:', e);
        showToast('网络请求失败', 'error');
        return null;
    }
}
function formatBytes(bytes, precision = 2) {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const k = 1024;
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(precision) + ' ' + units[i];
}
function formatKB(kb, precision = 2) {
    return formatBytes(kb * 1024, precision);
}
function timeAgo(datetime) {
    const now = new Date();
    const past = new Date(datetime);
    const diff = Math.floor((now - past) / 1000);
    
    if (diff < 0) return '刚刚';
    if (diff < 60) return diff + '秒前';
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
    if (diff < 2592000) return Math.floor(diff / 86400) + '天前';
    return datetime;
}
function getProgressColor(pct) {
    if (pct >= 90) return 'red';
    if (pct >= 70) return 'yellow';
    return 'green';
}
function progressBar(pct, showText = true) {
    const color = getProgressColor(pct);
    let html = `<div class="progress-bar"><div class="progress-fill ${color}" style="width:${Math.min(pct, 100)}%"></div></div>`;
    if (showText) {
        html += `<span class="progress-text">${pct}%</span>`;
    }
    return html;
}
function statusBadge(status) {
    const map = {
        'online': '<span class="badge badge-success"><span class="status-dot online"></span>在线</span>',
        'offline': '<span class="badge badge-default"><span class="status-dot offline"></span>离线</span>',
        'warning': '<span class="badge badge-warning"><span class="status-dot warning"></span>告警</span>',
        'danger': '<span class="badge badge-danger"><span class="status-dot danger"></span>异常</span>',
    };
    return map[status] || map['offline'];
}
function severityBadge(severity) {
    const map = {
        'info': '<span class="badge badge-info">提示</span>',
        'warning': '<span class="badge badge-warning">警告</span>',
        'danger': '<span class="badge badge-danger">严重</span>',
        'critical': '<span class="badge badge-critical">紧急</span>',
    };
    return map[severity] || map['info'];
}
function confirmAction(msg) {
    return confirm(msg);
}
function showModal(id) {
    document.getElementById(id).classList.add('show');
}

function hideModal(id) {
    document.getElementById(id).classList.remove('show');
}
let autoRefreshTimer = null;
function startAutoRefresh(callback, interval = 60000) {
    stopAutoRefresh();
    callback();
    autoRefreshTimer = setInterval(callback, interval);
}

function stopAutoRefresh() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
}
async function updateNotificationCount() {
    const resp = await api('/api/notifications.php', { action: 'unread_count' });
    if (resp && resp.code === 200) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            const count = resp.data.count;
            badge.textContent = count;
            badge.style.display = count > 0 ? 'block' : 'none';
        }
    }
}
document.addEventListener('DOMContentLoaded', function() {
    updateNotificationCount();
    setInterval(updateNotificationCount, 30000);
    const path = window.location.pathname;
    document.querySelectorAll('.sidebar-menu a').forEach(a => {
        if (a.getAttribute('href') === path) {
            a.classList.add('active');
        }
    });
});
const chartTheme = {
    color: ['#1890ff', '#52c41a', '#faad14', '#ff4d4f', '#13c2c2', '#722ed1', '#eb2f96', '#fa8c16'],
    grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        top: '12%',
        containLabel: true
    },
    tooltip: {
        trigger: 'axis',
        backgroundColor: 'rgba(255,255,255,0.95)',
        borderColor: '#e8e8e8',
        borderWidth: 1,
        textStyle: { color: '#333', fontSize: 13 },
    },
    legend: {
        textStyle: { fontSize: 12 }
    },
};
function createChart(domId, option) {
    const dom = document.getElementById(domId);
    if (!dom) return null;
    
    let chart = echarts.getInstanceByDom(dom);
    if (!chart) {
        chart = echarts.init(dom);
    }
    
    const mergedOption = Object.assign({}, chartTheme, option);
    chart.setOption(mergedOption, true);
    window.addEventListener('resize', () => chart.resize());
    return chart;
}
function formatTimeAxis(data, field = 'recorded_at') {
    return data.map(d => {
        const t = new Date(d[field]);
        return `${String(t.getHours()).padStart(2, '0')}:${String(t.getMinutes()).padStart(2, '0')}`;
    });
}
