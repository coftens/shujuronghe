#!/bin/bash
# ============================================
# 多源数据融合 - 主机数据采集Agent
# 部署在被监控的服务器上，定时采集各项指标
# 用法: ./agent.sh
# Crontab: * * * * * /opt/host-monitor/agent.sh
# ============================================

# ====== 配置 ======
# 平台API地址（修改为你的实际地址）
API_URL="http://121.196.229.4/api/collect.php"
# Agent密钥（添加服务器后获取）
AGENT_KEY="YOUR_AGENT_KEY_HERE"
# ==================

# 获取当前时间
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# ====== CPU 指标 ======
collect_cpu() {
    # CPU使用率（取1秒采样）
    CPU_STATS=$(top -bn1 | grep "Cpu(s)" | head -1)
    CPU_USER=$(echo "$CPU_STATS" | awk '{print $2}' | sed 's/%//;s/us,//')
    CPU_SYSTEM=$(echo "$CPU_STATS" | awk '{print $4}' | sed 's/%//;s/sy,//')
    CPU_IDLE=$(echo "$CPU_STATS" | awk '{print $8}' | sed 's/%//;s/id,//')
    CPU_IOWAIT=$(echo "$CPU_STATS" | awk '{print $10}' | sed 's/%//;s/wa,//')
    CPU_STEAL=$(echo "$CPU_STATS" | awk '{print $16}' | sed 's/%//;s/st//' 2>/dev/null || echo "0")
    
    # 如果top格式不同,用mpstat
    if [ -z "$CPU_USER" ] || [ "$CPU_USER" = "" ]; then
        if command -v mpstat &> /dev/null; then
            MPSTAT=$(mpstat 1 1 | tail -1)
            CPU_USER=$(echo "$MPSTAT" | awk '{print $3}')
            CPU_SYSTEM=$(echo "$MPSTAT" | awk '{print $5}')
            CPU_IDLE=$(echo "$MPSTAT" | awk '{print $NF}')
            CPU_IOWAIT=$(echo "$MPSTAT" | awk '{print $6}')
            CPU_STEAL=$(echo "$MPSTAT" | awk '{print $(NF-1)}')
        fi
    fi
    
    # 负载
    LOAD_1=$(cat /proc/loadavg | awk '{print $1}')
    LOAD_5=$(cat /proc/loadavg | awk '{print $2}')
    LOAD_15=$(cat /proc/loadavg | awk '{print $3}')
    
    # CPU核数
    CPU_CORES=$(nproc 2>/dev/null || grep -c processor /proc/cpuinfo)
    
    echo "{\"cpu_user\":${CPU_USER:-0},\"cpu_system\":${CPU_SYSTEM:-0},\"cpu_idle\":${CPU_IDLE:-0},\"cpu_iowait\":${CPU_IOWAIT:-0},\"cpu_steal\":${CPU_STEAL:-0},\"load_1\":${LOAD_1:-0},\"load_5\":${LOAD_5:-0},\"load_15\":${LOAD_15:-0},\"cpu_cores\":${CPU_CORES:-1}}"
}

# ====== 内存指标 ======
collect_memory() {
    MEM_INFO=$(cat /proc/meminfo)
    MEM_TOTAL=$(echo "$MEM_INFO" | grep "^MemTotal:" | awk '{print $2}')
    MEM_FREE=$(echo "$MEM_INFO" | grep "^MemFree:" | awk '{print $2}')
    MEM_AVAILABLE=$(echo "$MEM_INFO" | grep "^MemAvailable:" | awk '{print $2}')
    MEM_BUFFERS=$(echo "$MEM_INFO" | grep "^Buffers:" | awk '{print $2}')
    MEM_CACHED=$(echo "$MEM_INFO" | grep "^Cached:" | awk '{print $2}')
    SWAP_TOTAL=$(echo "$MEM_INFO" | grep "^SwapTotal:" | awk '{print $2}')
    SWAP_FREE=$(echo "$MEM_INFO" | grep "^SwapFree:" | awk '{print $2}')
    
    MEM_USED=$((MEM_TOTAL - MEM_FREE - MEM_BUFFERS - MEM_CACHED))
    SWAP_USED=$((SWAP_TOTAL - SWAP_FREE))
    
    if [ "$MEM_TOTAL" -gt 0 ]; then
        MEM_USAGE_PCT=$(awk "BEGIN {printf \"%.1f\", ($MEM_USED/$MEM_TOTAL)*100}")
    else
        MEM_USAGE_PCT="0"
    fi
    
    echo "{\"mem_total\":${MEM_TOTAL:-0},\"mem_used\":${MEM_USED:-0},\"mem_free\":${MEM_FREE:-0},\"mem_available\":${MEM_AVAILABLE:-0},\"mem_buffers\":${MEM_BUFFERS:-0},\"mem_cached\":${MEM_CACHED:-0},\"swap_total\":${SWAP_TOTAL:-0},\"swap_used\":${SWAP_USED:-0},\"mem_usage_pct\":${MEM_USAGE_PCT:-0}}"
}

# ====== 磁盘指标 ======
collect_disk() {
    DISK_JSON="["
    FIRST=1
    
    df -Pk | grep -vE "^Filesystem|tmpfs|devtmpfs|overlay" | while read line; do
        FS=$(echo "$line" | awk '{print $1}')
        TOTAL=$(echo "$line" | awk '{print $2}')
        USED=$(echo "$line" | awk '{print $3}')
        FREE=$(echo "$line" | awk '{print $4}')
        PCT=$(echo "$line" | awk '{print $5}' | sed 's/%//')
        MOUNT=$(echo "$line" | awk '{print $6}')
        
        # inode使用率
        INODE_PCT=$(df -i "$MOUNT" 2>/dev/null | tail -1 | awk '{print $5}' | sed 's/%//' 2>/dev/null || echo "0")
        
        if [ $FIRST -eq 1 ]; then
            FIRST=0
        else
            echo ","
        fi
        echo "{\"mount_point\":\"${MOUNT}\",\"filesystem\":\"${FS}\",\"disk_total\":${TOTAL},\"disk_used\":${USED},\"disk_free\":${FREE},\"disk_usage_pct\":${PCT:-0},\"inode_usage_pct\":${INODE_PCT:-0}}"
    done
    
    # 用子shell方式正确生成JSON数组
    DISK_DATA=$(df -Pk | grep -vE "^Filesystem|tmpfs|devtmpfs|overlay" | awk '{
        printf "{\"mount_point\":\"%s\",\"filesystem\":\"%s\",\"disk_total\":%s,\"disk_used\":%s,\"disk_free\":%s,\"disk_usage_pct\":%s},", $6, $1, $2, $3, $4, int($5)
    }' | sed 's/,$//')
    
    echo "[${DISK_DATA}]"
}

# ====== 网络指标 ======
collect_network() {
    NET_DATA="["
    FIRST=1
    
    for iface in $(ls /sys/class/net/ | grep -v lo); do
        RX1=$(cat /sys/class/net/$iface/statistics/rx_bytes 2>/dev/null || echo 0)
        TX1=$(cat /sys/class/net/$iface/statistics/tx_bytes 2>/dev/null || echo 0)
        RX_PACKETS=$(cat /sys/class/net/$iface/statistics/rx_packets 2>/dev/null || echo 0)
        TX_PACKETS=$(cat /sys/class/net/$iface/statistics/tx_packets 2>/dev/null || echo 0)
        RX_ERRORS=$(cat /sys/class/net/$iface/statistics/rx_errors 2>/dev/null || echo 0)
        TX_ERRORS=$(cat /sys/class/net/$iface/statistics/tx_errors 2>/dev/null || echo 0)
        
        if [ $FIRST -eq 1 ]; then
            FIRST=0
        else
            NET_DATA="${NET_DATA},"
        fi
        
        NET_DATA="${NET_DATA}{\"interface\":\"${iface}\",\"bytes_in\":${RX1},\"bytes_out\":${TX1},\"packets_in\":${RX_PACKETS},\"packets_out\":${TX_PACKETS},\"errors_in\":${RX_ERRORS},\"errors_out\":${TX_ERRORS}}"
    done
    
    NET_DATA="${NET_DATA}]"
    echo "$NET_DATA"
}

# ====== 进程 TOP 10 (CPU+内存) ======
collect_processes() {
    PS_DATA=$(ps aux --sort=-%cpu | head -11 | tail -10 | awk '{
        gsub(/"/, "\\\"", $11);
        printf "{\"pid\":%s,\"user\":\"%s\",\"cpu_pct\":%s,\"mem_pct\":%s,\"mem_rss\":%s,\"status\":\"%s\",\"process_name\":\"%s\",\"command\":\"%s\"},", $2, $1, $3, $4, $6, $8, $11, $11
    }' | sed 's/,$//')
    
    echo "[${PS_DATA}]"
}

# ====== TCP连接状态 ======
collect_tcp() {
    if command -v ss &> /dev/null; then
        TCP_STATS=$(ss -tan | tail -n +2 | awk '{print $1}' | sort | uniq -c | sort -rn)
    else
        TCP_STATS=$(netstat -tan | tail -n +3 | awk '{print $6}' | sort | uniq -c | sort -rn)
    fi
    
    ESTABLISHED=$(echo "$TCP_STATS" | grep -i "ESTAB" | awk '{print $1}' || echo 0)
    TIME_WAIT=$(echo "$TCP_STATS" | grep -i "TIME-WAIT\|TIME_WAIT" | awk '{print $1}' || echo 0)
    CLOSE_WAIT=$(echo "$TCP_STATS" | grep -i "CLOSE-WAIT\|CLOSE_WAIT" | awk '{print $1}' || echo 0)
    LISTEN=$(echo "$TCP_STATS" | grep -i "LISTEN" | awk '{print $1}' || echo 0)
    SYN_SENT=$(echo "$TCP_STATS" | grep -i "SYN-SENT\|SYN_SENT" | awk '{print $1}' || echo 0)
    SYN_RECV=$(echo "$TCP_STATS" | grep -i "SYN-RECV\|SYN_RECV" | awk '{print $1}' || echo 0)
    FIN_WAIT1=$(echo "$TCP_STATS" | grep -i "FIN-WAIT-1\|FIN_WAIT1" | awk '{print $1}' || echo 0)
    FIN_WAIT2=$(echo "$TCP_STATS" | grep -i "FIN-WAIT-2\|FIN_WAIT2" | awk '{print $1}' || echo 0)
    
    TOTAL=$((${ESTABLISHED:-0} + ${TIME_WAIT:-0} + ${CLOSE_WAIT:-0} + ${LISTEN:-0} + ${SYN_SENT:-0} + ${SYN_RECV:-0} + ${FIN_WAIT1:-0} + ${FIN_WAIT2:-0}))
    
    echo "{\"established\":${ESTABLISHED:-0},\"time_wait\":${TIME_WAIT:-0},\"close_wait\":${CLOSE_WAIT:-0},\"listen\":${LISTEN:-0},\"syn_sent\":${SYN_SENT:-0},\"syn_recv\":${SYN_RECV:-0},\"fin_wait1\":${FIN_WAIT1:-0},\"fin_wait2\":${FIN_WAIT2:-0},\"total_connections\":${TOTAL}}"
}

# ====== 端口监听 ======
collect_ports() {
    if command -v ss &> /dev/null; then
        PORT_DATA=$(ss -tlnp | tail -n +2 | awk '{
            split($4, a, ":");
            port = a[length(a)];
            proc = $6;
            gsub(/.*"/, "", proc);
            gsub(/".*/, "", proc);
            printf "{\"port\":%s,\"protocol\":\"tcp\",\"process_name\":\"%s\"},", port, proc
        }' | sed 's/,$//')
    else
        PORT_DATA=$(netstat -tlnp 2>/dev/null | tail -n +3 | awk '{
            split($4, a, ":");
            port = a[length(a)];
            proc = $7;
            gsub(/.*\//, "", proc);
            printf "{\"port\":%s,\"protocol\":\"tcp\",\"process_name\":\"%s\"},", port, proc
        }' | sed 's/,$//')
    fi
    
    echo "[${PORT_DATA}]"
}

# ====== 系统日志（最近的错误和警告） ======
collect_logs() {
    LOG_DATA="["
    FIRST=1
    
    # 从 /var/log/messages 或 journalctl 获取最近的错误
    if command -v journalctl &> /dev/null; then
        LOGS=$(journalctl --since "1 minutes ago" -p err --no-pager -o short 2>/dev/null | tail -20)
    elif [ -f /var/log/messages ]; then
        LOGS=$(tail -50 /var/log/messages | grep -iE "error|fail|critical|warning" | tail -20)
    else
        LOGS=""
    fi
    
    if [ -n "$LOGS" ]; then
        LOG_DATA=$(echo "$LOGS" | head -20 | while IFS= read -r line; do
            # 转义JSON特殊字符
            ESCAPED=$(echo "$line" | sed 's/\\/\\\\/g;s/"/\\"/g;s/\t/\\t/g' | head -c 500)
            echo "{\"level\":\"error\",\"source\":\"syslog\",\"message\":\"${ESCAPED}\"},"
        done | sed '$ s/,$//')
    fi
    
    echo "[${LOG_DATA}]"
}

# ====== 系统信息 ======
collect_sysinfo() {
    HOSTNAME=$(hostname)
    OS_INFO=$(cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | cut -d'"' -f2 || uname -a)
    KERNEL=$(uname -r)
    UPTIME_SECONDS=$(cat /proc/uptime | awk '{print int($1)}')
    
    echo "{\"hostname\":\"${HOSTNAME}\",\"os_info\":\"${OS_INFO}\",\"kernel\":\"${KERNEL}\",\"uptime\":${UPTIME_SECONDS}}"
}

# ====== 汇总并发送 ======
CPU_DATA=$(collect_cpu)
MEM_DATA=$(collect_memory)
DISK_DATA=$(collect_disk)
NET_DATA=$(collect_network)
PROC_DATA=$(collect_processes)
TCP_DATA=$(collect_tcp)
PORT_DATA=$(collect_ports)
LOG_DATA=$(collect_logs)
SYS_DATA=$(collect_sysinfo)

# 构建完整JSON
PAYLOAD=$(cat <<EOF
{
    "agent_key": "${AGENT_KEY}",
    "timestamp": "${TIMESTAMP}",
    "sysinfo": ${SYS_DATA},
    "cpu": ${CPU_DATA},
    "memory": ${MEM_DATA},
    "disk": ${DISK_DATA},
    "network": ${NET_DATA},
    "processes": ${PROC_DATA},
    "tcp": ${TCP_DATA},
    "ports": ${PORT_DATA},
    "logs": ${LOG_DATA}
}
EOF
)

# 发送到平台
RESPONSE=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -d "${PAYLOAD}" \
    --connect-timeout 10 \
    --max-time 30 \
    "${API_URL}" 2>/dev/null)

# 记录结果（可选调试）
if [ "${DEBUG:-0}" = "1" ]; then
    echo "[$(date)] Response: ${RESPONSE}" >> /tmp/host-monitor-agent.log
fi

exit 0
