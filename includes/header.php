<?php
/**
 * 公共头部模板
 */
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', '仪表盘');
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(PAGE_TITLE); ?> - 多源数据融合主机性能分析与故障预警平台</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- ECharts -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script src="/assets/js/app.js"></script>
</head>
<body>
<div class="layout">
    <!-- 侧边栏 -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <h1><span class="logo-icon">📊</span> 性能监控平台</h1>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/pages/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <span class="menu-icon">📈</span> 仪表盘
            </a></li>
            <li><a href="/pages/servers.php" class="<?php echo $currentPage === 'servers' ? 'active' : ''; ?>">
                <span class="menu-icon">🖥️</span> 服务器管理
            </a></li>
            <li><a href="/pages/metrics.php" class="<?php echo $currentPage === 'metrics' ? 'active' : ''; ?>">
                <span class="menu-icon">📉</span> 性能指标
            </a></li>
            <li><a href="/pages/analysis.php" class="<?php echo $currentPage === 'analysis' ? 'active' : ''; ?>">
                <span class="menu-icon">🔍</span> 性能分析
            </a></li>
            <div class="menu-divider"></div>
            <li><a href="/pages/alerts.php" class="<?php echo $currentPage === 'alerts' ? 'active' : ''; ?>">
                <span class="menu-icon">🔔</span> 故障预警
            </a></li>
            <li><a href="/pages/logs.php" class="<?php echo $currentPage === 'logs' ? 'active' : ''; ?>">
                <span class="menu-icon">📋</span> 系统日志
            </a></li>
            <div class="menu-divider"></div>
            <li><a href="/pages/settings.php" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                <span class="menu-icon">⚙️</span> 系统设置
            </a></li>
        </ul>
    </aside>
    
    <!-- 主内容 -->
    <div class="main-content">
        <!-- 顶部导航 -->
        <header class="header">
            <div class="header-left">
                <button class="btn btn-sm" onclick="document.getElementById('sidebar').classList.toggle('show')" style="display:none" id="menuToggle">☰</button>
                <h2><?php echo e(PAGE_TITLE); ?></h2>
            </div>
            <div class="header-right">
                <span class="notification-bell" onclick="window.location.href='/pages/alerts.php'">
                    🔔
                    <span class="badge notification-badge" style="display:none">0</span>
                </span>
                <div class="user-info" onclick="document.getElementById('userDropdown').style.display = document.getElementById('userDropdown').style.display === 'block' ? 'none' : 'block'">
                    <div class="user-avatar"><?php echo mb_substr($_SESSION['username'] ?? 'U', 0, 1); ?></div>
                    <span><?php echo e($_SESSION['username'] ?? '未登录'); ?></span>
                </div>
                <div id="userDropdown" style="display:none;position:absolute;right:24px;top:50px;background:#fff;border:1px solid #e8e8e8;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:999;min-width:140px;">
                    <a href="/pages/settings.php" style="display:block;padding:10px 16px;color:#333;border-bottom:1px solid #f0f0f0;">⚙️ 设置</a>
                    <a href="/api/auth.php?action=logout" style="display:block;padding:10px 16px;color:#ff4d4f;">🚪 退出登录</a>
                </div>
            </div>
        </header>
        
        <!-- 页面内容 -->
        <div class="page-content">
