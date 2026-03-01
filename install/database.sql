-- ============================================
-- 多源数据融合的主机性能分析与故障预警平台
-- 数据库初始化脚本
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL COMMENT '用户名',
    `password` varchar(255) NOT NULL COMMENT '密码(bcrypt)',
    `email` varchar(100) DEFAULT NULL COMMENT '邮箱(用于告警通知)',
    `role` enum('admin','viewer') DEFAULT 'viewer' COMMENT '角色',
    `last_login` datetime DEFAULT NULL COMMENT '最后登录时间',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 默认管理员 admin/admin123
INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin');

-- ----------------------------
-- 服务器表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `servers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT '服务器名称',
    `host` varchar(100) NOT NULL COMMENT 'IP地址或主机名',
    `port` int(11) DEFAULT 22 COMMENT 'SSH端口',
    `agent_key` varchar(64) NOT NULL COMMENT 'Agent通信密钥',
    `os_info` varchar(255) DEFAULT NULL COMMENT '操作系统信息',
    `status` enum('online','offline','warning','danger') DEFAULT 'offline' COMMENT '状态',
    `last_heartbeat` datetime DEFAULT NULL COMMENT '最后心跳时间',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_agent_key` (`agent_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='服务器表';

-- ----------------------------
-- CPU指标表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `metrics_cpu` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `cpu_user` float DEFAULT 0 COMMENT '用户态CPU%',
    `cpu_system` float DEFAULT 0 COMMENT '系统态CPU%',
    `cpu_idle` float DEFAULT 0 COMMENT '空闲CPU%',
    `cpu_iowait` float DEFAULT 0 COMMENT 'IO等待%',
    `cpu_steal` float DEFAULT 0 COMMENT '虚拟化偷取%',
    `load_1` float DEFAULT 0 COMMENT '1分钟负载',
    `load_5` float DEFAULT 0 COMMENT '5分钟负载',
    `load_15` float DEFAULT 0 COMMENT '15分钟负载',
    `cpu_cores` int(11) DEFAULT 1 COMMENT 'CPU核数',
    `recorded_at` datetime NOT NULL COMMENT '采集时间',
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`),
    KEY `idx_recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='CPU指标表';

-- ----------------------------
-- 内存指标表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `metrics_memory` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `mem_total` bigint(20) DEFAULT 0 COMMENT '总内存(KB)',
    `mem_used` bigint(20) DEFAULT 0 COMMENT '已用内存(KB)',
    `mem_free` bigint(20) DEFAULT 0 COMMENT '空闲内存(KB)',
    `mem_available` bigint(20) DEFAULT 0 COMMENT '可用内存(KB)',
    `mem_buffers` bigint(20) DEFAULT 0 COMMENT '缓冲(KB)',
    `mem_cached` bigint(20) DEFAULT 0 COMMENT '缓存(KB)',
    `swap_total` bigint(20) DEFAULT 0 COMMENT '交换总量(KB)',
    `swap_used` bigint(20) DEFAULT 0 COMMENT '交换已用(KB)',
    `mem_usage_pct` float DEFAULT 0 COMMENT '内存使用率%',
    `recorded_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内存指标表';

-- ----------------------------
-- 磁盘指标表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `metrics_disk` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `mount_point` varchar(255) NOT NULL COMMENT '挂载点',
    `filesystem` varchar(255) DEFAULT NULL COMMENT '文件系统',
    `disk_total` bigint(20) DEFAULT 0 COMMENT '总容量(KB)',
    `disk_used` bigint(20) DEFAULT 0 COMMENT '已用(KB)',
    `disk_free` bigint(20) DEFAULT 0 COMMENT '可用(KB)',
    `disk_usage_pct` float DEFAULT 0 COMMENT '使用率%',
    `inode_usage_pct` float DEFAULT 0 COMMENT 'inode使用率%',
    `recorded_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='磁盘指标表';

-- ----------------------------
-- 磁盘IO指标表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `metrics_disk_io` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `device` varchar(50) NOT NULL COMMENT '设备名',
    `read_bytes` bigint(20) DEFAULT 0 COMMENT '读取字节数',
    `write_bytes` bigint(20) DEFAULT 0 COMMENT '写入字节数',
    `read_iops` float DEFAULT 0 COMMENT '读IOPS',
    `write_iops` float DEFAULT 0 COMMENT '写IOPS',
    `io_util_pct` float DEFAULT 0 COMMENT 'IO利用率%',
    `recorded_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='磁盘IO指标表';

-- ----------------------------
-- 网络指标表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `metrics_network` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `interface` varchar(50) NOT NULL COMMENT '网卡名',
    `bytes_in` bigint(20) DEFAULT 0 COMMENT '入流量(bytes)',
    `bytes_out` bigint(20) DEFAULT 0 COMMENT '出流量(bytes)',
    `packets_in` bigint(20) DEFAULT 0 COMMENT '入包数',
    `packets_out` bigint(20) DEFAULT 0 COMMENT '出包数',
    `errors_in` bigint(20) DEFAULT 0 COMMENT '入错误',
    `errors_out` bigint(20) DEFAULT 0 COMMENT '出错误',
    `bandwidth_in` float DEFAULT 0 COMMENT '入带宽(Mbps)',
    `bandwidth_out` float DEFAULT 0 COMMENT '出带宽(Mbps)',
    `recorded_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='网络指标表';

-- ----------------------------
-- 进程状态表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `metrics_process` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `pid` int(11) NOT NULL COMMENT '进程ID',
    `user` varchar(50) DEFAULT NULL COMMENT '运行用户',
    `process_name` varchar(255) NOT NULL COMMENT '进程名',
    `cpu_pct` float DEFAULT 0 COMMENT 'CPU占用%',
    `mem_pct` float DEFAULT 0 COMMENT '内存占用%',
    `mem_rss` bigint(20) DEFAULT 0 COMMENT '物理内存(KB)',
    `status` varchar(20) DEFAULT NULL COMMENT '进程状态',
    `command` text COMMENT '完整命令',
    `recorded_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`),
    KEY `idx_cpu_pct` (`cpu_pct`),
    KEY `idx_mem_pct` (`mem_pct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='进程状态表';

-- ----------------------------
-- TCP连接表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `metrics_tcp` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `established` int(11) DEFAULT 0,
    `time_wait` int(11) DEFAULT 0,
    `close_wait` int(11) DEFAULT 0,
    `listen` int(11) DEFAULT 0,
    `syn_sent` int(11) DEFAULT 0,
    `syn_recv` int(11) DEFAULT 0,
    `fin_wait1` int(11) DEFAULT 0,
    `fin_wait2` int(11) DEFAULT 0,
    `total_connections` int(11) DEFAULT 0,
    `recorded_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='TCP连接表';

-- ----------------------------
-- 端口监听表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `metrics_ports` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `port` int(11) NOT NULL COMMENT '端口号',
    `protocol` varchar(10) DEFAULT 'tcp' COMMENT '协议',
    `process_name` varchar(255) DEFAULT NULL COMMENT '进程名',
    `pid` int(11) DEFAULT NULL COMMENT '进程ID',
    `state` varchar(20) DEFAULT 'LISTEN',
    `recorded_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='端口监听表';

-- ----------------------------
-- 系统日志表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `log_type` enum('syslog','dmesg','auth','application','custom') DEFAULT 'syslog' COMMENT '日志类型',
    `level` enum('emergency','alert','critical','error','warning','notice','info','debug') DEFAULT 'info' COMMENT '日志级别',
    `source` varchar(100) DEFAULT NULL COMMENT '来源',
    `message` text COMMENT '日志内容',
    `log_time` datetime DEFAULT NULL COMMENT '日志原始时间',
    `recorded_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_server_time` (`server_id`, `recorded_at`),
    KEY `idx_level` (`level`),
    KEY `idx_log_type` (`log_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统日志表';

-- ----------------------------
-- 告警规则表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `alert_rules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT '规则名称',
    `metric_type` varchar(50) NOT NULL COMMENT '指标类型(cpu/memory/disk/network/process/tcp)',
    `metric_field` varchar(50) NOT NULL COMMENT '指标字段',
    `condition` enum('gt','gte','lt','lte','eq','neq') NOT NULL COMMENT '条件(大于/大于等于/小于等)',
    `threshold` float NOT NULL COMMENT '阈值',
    `duration` int(11) DEFAULT 1 COMMENT '持续次数(连续N次超阈值才告警)',
    `severity` enum('info','warning','danger','critical') DEFAULT 'warning' COMMENT '严重等级',
    `notify_email` tinyint(1) DEFAULT 1 COMMENT '是否邮件通知',
    `notify_site` tinyint(1) DEFAULT 1 COMMENT '是否站内通知',
    `enabled` tinyint(1) DEFAULT 1 COMMENT '是否启用',
    `server_id` int(11) DEFAULT NULL COMMENT '针对的服务器(NULL=所有)',
    `cooldown` int(11) DEFAULT 300 COMMENT '冷却时间(秒)',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='告警规则表';

-- 默认告警规则
INSERT INTO `alert_rules` (`name`, `metric_type`, `metric_field`, `condition`, `threshold`, `duration`, `severity`) VALUES
('CPU使用率过高', 'cpu', 'cpu_usage', 'gt', 90, 3, 'danger'),
('CPU负载过高', 'cpu', 'load_1', 'gt', 10, 3, 'warning'),
('内存使用率过高', 'memory', 'mem_usage_pct', 'gt', 90, 3, 'danger'),
('内存使用率警告', 'memory', 'mem_usage_pct', 'gt', 80, 5, 'warning'),
('磁盘使用率过高', 'disk', 'disk_usage_pct', 'gt', 90, 1, 'critical'),
('磁盘使用率警告', 'disk', 'disk_usage_pct', 'gt', 80, 1, 'warning'),
('磁盘即将满', 'disk', 'disk_usage_pct', 'gt', 95, 1, 'critical'),
('Swap使用过多', 'memory', 'swap_used_pct', 'gt', 50, 3, 'warning'),
('网络入流量突增', 'network', 'bandwidth_in', 'gt', 100, 3, 'warning'),
('TCP连接数过多', 'tcp', 'total_connections', 'gt', 5000, 3, 'warning'),
('服务器掉线', 'server', 'offline', 'eq', 1, 1, 'critical');

-- ----------------------------
-- 告警历史表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `alert_history` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `server_id` int(11) NOT NULL,
    `rule_id` int(11) DEFAULT NULL,
    `rule_name` varchar(100) DEFAULT NULL,
    `severity` enum('info','warning','danger','critical') DEFAULT 'warning',
    `metric_type` varchar(50) DEFAULT NULL,
    `metric_value` float DEFAULT NULL COMMENT '触发时的指标值',
    `threshold` float DEFAULT NULL COMMENT '阈值',
    `message` text COMMENT '告警详情',
    `status` enum('active','acknowledged','resolved') DEFAULT 'active' COMMENT '告警状态',
    `acknowledged_by` int(11) DEFAULT NULL,
    `acknowledged_at` datetime DEFAULT NULL,
    `resolved_at` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_server_id` (`server_id`),
    KEY `idx_status` (`status`),
    KEY `idx_severity` (`severity`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='告警历史表';

-- ----------------------------
-- 通知表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `type` enum('alert','system','info') DEFAULT 'alert',
    `title` varchar(200) NOT NULL,
    `content` text,
    `is_read` tinyint(1) DEFAULT 0,
    `related_id` bigint(20) DEFAULT NULL COMMENT '关联记录ID',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `is_read`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知表';

-- ----------------------------
-- 系统配置表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `key_name` varchar(100) NOT NULL,
    `value` text,
    `description` varchar(255) DEFAULT NULL,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('site_name', '多源数据融合主机性能分析与故障预警平台', '站点名称'),
('data_retention_days', '90', '数据保留天数'),
('collect_interval', '60', '数据采集间隔(秒)'),
('alert_check_interval', '60', '告警检查间隔(秒)'),
('smtp_host', '', 'SMTP服务器'),
('smtp_port', '465', 'SMTP端口'),
('smtp_user', '', 'SMTP用户名'),
('smtp_pass', '', 'SMTP密码'),
('smtp_from', '', '发件人地址'),
('smtp_encryption', 'ssl', 'SMTP加密方式'),
('enable_email_notify', '0', '是否启用邮件通知');

-- ----------------------------
-- 操作日志表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `operation_logs` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(50) NOT NULL,
    `target` varchar(100) DEFAULT NULL,
    `detail` text,
    `ip` varchar(45) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

SET FOREIGN_KEY_CHECKS = 1;
