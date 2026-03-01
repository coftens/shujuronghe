<?php
/**
 * 全局配置文件
 */
return [
    // 站点信息
    'site_name'    => '多源数据融合主机性能分析与故障预警平台',
    'site_version' => '1.0.0',
    
    // 时区
    'timezone'     => 'Asia/Shanghai',
    
    // 会话配置
    'session_lifetime' => 7200, // 2小时
    
    // API密钥(Agent通信时验证用)
    'api_secret'   => 'CHANGE_THIS_TO_RANDOM_STRING',
    
    // 数据保留天数
    'data_retention_days' => 90,
    
    // 采集间隔(秒)
    'collect_interval' => 60,
    
    // 告警冷却时间(秒)
    'alert_cooldown' => 300,
    
    // 分页
    'per_page' => 20,
    
    // 调试模式
    'debug' => false,
    
    // 服务器地址
    'server_url' => 'http://121.196.229.4',
];
