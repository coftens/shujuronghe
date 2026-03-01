# 多源数据融合的主机性能分析与故障预警平台

一个基于 PHP + MySQL 的轻量级服务器监控平台，支持多服务器性能数据采集、实时监控、趋势分析、故障预警和邮件通知。

## 功能特性

- **多源数据采集**：CPU、内存、磁盘、网络、TCP连接、进程、端口、系统日志等全方位数据采集
- **实时仪表盘**：服务器状态总览、CPU/内存趋势图、活跃告警一目了然
- **性能分析**：综合评分、瓶颈检测、趋势预测（线性回归）、高峰时段分析
- **故障预警**：灵活的告警规则配置、多级告警、冷却机制、自动解决
- **通知推送**：站内通知 + SMTP邮件通知
- **日志管理**：系统日志集中采集与查询
- **用户管理**：多用户、角色权限（管理员/查看者）

## 技术栈

- **后端**：PHP 7.2+（纯PHP，无框架依赖）
- **数据库**：MySQL 5.7+
- **前端**：HTML + CSS + JavaScript + ECharts 5.4
- **数据采集**：Shell Agent（Bash脚本 + cron）

## 部署步骤

### 1. 环境要求
- CentOS 7+ / Ubuntu 18+ 
- PHP 7.2+ (需要 PDO、pdo_mysql、curl、mbstring、openssl 扩展)
- MySQL 5.7+
- Nginx / Apache

### 2. 安装

```bash
# 克隆代码
git clone https://github.com/coftens/shujuronghe.git /www/wwwroot/monitor

# 设置权限
chmod -R 755 /www/wwwroot/monitor
chown -R www:www /www/wwwroot/monitor
```

### 3. Web安装

浏览器访问 `http://your-server-ip/install/install.php`，按照向导完成：
1. 环境检查
2. 数据库配置
3. 管理员密码设置

### 4. 配置定时任务

```bash
# 告警检查（每分钟）
* * * * * php /www/wwwroot/monitor/cron/check_alerts.php >> /var/log/monitor_alert.log 2>&1

# 数据清理（每天凌晨2点）
0 2 * * * php /www/wwwroot/monitor/cron/cleanup.php >> /var/log/monitor_cleanup.log 2>&1
```

### 5. 安装Agent

在需要监控的服务器上执行：
```bash
curl -sSL http://your-server-ip/agent/install_agent.sh | bash -s -- http://your-server-ip YOUR_AGENT_KEY
```

Agent Key 可在「服务器管理」页面添加服务器后获取。

## 项目结构

```
├── index.php              # 入口文件
├── config/                # 配置文件
│   ├── config.php         # 全局配置
│   └── database.php       # 数据库配置
├── includes/              # 核心模块
│   ├── init.php           # 初始化引导
│   ├── db.php             # 数据库类
│   ├── auth.php           # 认证模块
│   ├── functions.php      # 工具函数
│   ├── header.php         # 页面头部
│   └── footer.php         # 页面尾部
├── api/                   # API接口
│   ├── collect.php        # 数据采集接口
│   ├── metrics.php        # 指标查询
│   ├── servers.php        # 服务器管理
│   ├── alerts.php         # 告警管理
│   ├── analysis.php       # 性能分析
│   ├── logs.php           # 日志查询
│   ├── notifications.php  # 通知管理
│   ├── auth.php           # 认证接口
│   └── settings.php       # 设置接口
├── pages/                 # 前端页面
│   ├── login.php          # 登录
│   ├── dashboard.php      # 仪表盘
│   ├── servers.php        # 服务器管理
│   ├── metrics.php        # 性能指标
│   ├── analysis.php       # 性能分析
│   ├── alerts.php         # 故障预警
│   ├── logs.php           # 系统日志
│   └── settings.php       # 系统设置
├── assets/                # 静态资源
│   ├── css/style.css      # 样式
│   └── js/app.js          # 前端JS
├── agent/                 # 采集Agent
│   ├── agent.sh           # 采集脚本
│   └── install_agent.sh   # 安装脚本
├── cron/                  # 定时任务
│   ├── check_alerts.php   # 告警检查
│   └── cleanup.php        # 数据清理
└── install/               # 安装程序
    ├── install.php        # Web安装向导
    └── database.sql       # 数据库初始化脚本
```

## 默认账号

- 用户名：`admin`
- 密码：`admin123`（请通过安装向导或系统设置修改）

## 许可证

MIT License
