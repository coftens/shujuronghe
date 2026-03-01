#!/bin/bash
# ============================================
# 多源数据融合 - 主机数据采集Agent
# 部署在被监控的服务器上，定时采集各项指标
# 用法: ./agent.sh
# Crontab: * * * * * /opt/host-monitor/agent.sh
# ============================================

# ====== 配置 ======
# 平台API地址（install_agent.sh会自动填充）
API_URL="${API_URL:-YOUR_API_URL}"
# Agent密钥（install_agent.sh会自动填充）
AGENT_KEY="${AGENT_KEY:-YOUR_AGENT_KEY}"
# ==================

# 获取当前时间
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# ====== CPU 指标 ======
collect_cpu() {
    # 通过 /proc/stat 两次采样差值计算 CPU 使用率（最准确）
    read_cpu_stat() {
        awk '/^cpu /{print $2,$3,$4,$5,$6,$7,$8,$9}' /proc/stat
    }
    
    STAT1=$(read_cpu_stat)
    sleep 1
    STAT2=$(read_cpu_stat)
    
    CPU_USER=$(awk -v s1="$STAT1" -v s2="$STAT2" 'BEGIN{
        split(s1,a," "); split(s2,b," ");
        user=b[1]-a[1]; nice=b[2]-a[2]; sys=b[3]-a[3]; idle=b[4]-a[4];
        iowait=b[5]-a[5]; irq=b[6]-a[6]; softirq=b[7]-a[7]; steal=b[8]-a[8];
        total=user+nice+sys+idle+iowait+irq+softirq+steal;
        if(total==0) total=1;
        printf "%.2f", (user+nice)/total*100
    }')
    CPU_SYSTEM=$(awk -v s1="$STAT1" -v s2="$STAT2" 'BEGIN{
        split(s1,a," "); split(s2,b," ");
        sys=b[3]-a[3];
        total=(b[1]-a[1])+(b[2]-a[2])+(b[3]-a[3])+(b[4]-a[4])+(b[5]-a[5])+(b[6]-a[6])+(b[7]-a[7])+(b[8]-a[8]);
        if(total==0) total=1;
        printf "%.2f", sys/total*100
    }')
    CPU_IDLE=$(awk -v s1="$STAT1" -v s2="$STAT2" 'BEGIN{
        split(s1,a," "); split(s2,b," ");
        idle=b[4]-a[4];
        total=(b[1]-a[1])+(b[2]-a[2])+(b[3]-a[3])+(b[4]-a[4])+(b[5]-a[5])+(b[6]-a[6])+(b[7]-a[7])+(b[8]-a[8]);
        if(total==0) total=1;
        printf "%.2f", idle/total*100
    }')
    CPU_IOWAIT=$(awk -v s1="$STAT1" -v s2="$STAT2" 'BEGIN{
        split(s1,a," "); split(s2,b," ");
        iowait=b[5]-a[5];
        total=(b[1]-a[1])+(b[2]-a[2])+(b[3]-a[3])+(b[4]-a[4])+(b[5]-a[5])+(b[6]-a[6])+(b[7]-a[7])+(b[8]-a[8]);
        if(total==0) total=1;
        printf "%.2f", iowait/total*100
    }')
    CPU_STEAL=$(awk -v s1="$STAT1" -v s2="$STAT2" 'BEGIN{
        split(s1,a," "); split(s2,b," ");
        steal=b[8]-a[8];
        total=(b[1]-a[1])+(b[2]-a[2])+(b[3]-a[3])+(b[4]-a[4])+(b[5]-a[5])+(b[6]-a[6])+(b[7]-a[7])+(b[8]-a[8]);
        if(total==0) total=1;
        printf "%.2f", steal/total*100
    }')
    
    # 负载
    LOAD_1=$(awk '{print $1}' /proc/loadavg)
    LOAD_5=$(awk '{print $2}' /proc/loadavg)
    LOAD_15=$(awk '{print $3}' /proc/loadavg)
    
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
    DISK_DATA=$(df -Pk | grep -vE "^Filesystem|tmpfs|devtmpfs|overlay|udev" | awk '{
        pct = int($5);
        printf "{\"mount_point\":\"%s\",\"filesystem\":\"%s\",\"disk_total\":%s,\"disk_used\":%s,\"disk_free\":%s,\"disk_usage_pct\":%s,\"inode_usage_pct\":0},", $6, $1, $2, $3, $4, pct
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
    if command -v journalctl &> /dev/null; then
        LOGS=$(journalctl --since "1 minutes ago" -p err --no-pager -o short 2>/dev/null | tail -20)
    elif [ -f /var/log/messages ]; then
        LOGS=$(tail -50 /var/log/messages | grep -iE "error|fail|critical|warning" | tail -20)
    else
        LOGS=""
    fi

    if [ -z "$LOGS" ]; then
        echo "[]"
        return
    fi

    LOG_DATA=$(echo "$LOGS" | head -20 | while IFS= read -r line; do
        ESCAPED=$(echo "$line" | sed 's/\\/\\\\/g;s/"/\\"/g;s/\t/\\t/g' | head -c 500)
        printf '{"level":"error","source":"syslog","message":"%s"},\n' "$ESCAPED"
    done | sed '$ s/,$//')

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

# 构建完整JSON，写入临时文件避免heredoc换行问题
PAYLOAD_FILE=$(mktemp /tmp/host-monitor-payload.XXXXXX)

# 各字段压缩为单行
CPU_LINE=$(echo "$CPU_DATA" | tr -d '\n\r')
MEM_LINE=$(echo "$MEM_DATA" | tr -d '\n\r')
DISK_LINE=$(echo "$DISK_DATA" | tr -d '\n\r')
NET_LINE=$(echo "$NET_DATA" | tr -d '\n\r')
PROC_LINE=$(echo "$PROC_DATA" | tr -d '\n\r')
TCP_LINE=$(echo "$TCP_DATA" | tr -d '\n\r')
PORT_LINE=$(echo "$PORT_DATA" | tr -d '\n\r')
LOG_LINE=$(echo "$LOG_DATA" | tr -d '\n\r')
SYS_LINE=$(echo "$SYS_DATA" | tr -d '\n\r')
TS_LINE=$(echo "$TIMESTAMP" | tr -d '\n\r')

printf '{"agent_key":"%s","timestamp":"%s","sysinfo":%s,"cpu":%s,"memory":%s,"disk":%s,"network":%s,"processes":%s,"tcp":%s,"ports":%s,"logs":%s}' \
    "$AGENT_KEY" "$TS_LINE" "$SYS_LINE" "$CPU_LINE" "$MEM_LINE" "$DISK_LINE" "$NET_LINE" "$PROC_LINE" "$TCP_LINE" "$PORT_LINE" "$LOG_LINE" \
    > "$PAYLOAD_FILE"

# 发送到平台
RESPONSE=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    --data-binary "@${PAYLOAD_FILE}" \
    --connect-timeout 10 \
    --max-time 30 \
    "${API_URL}" 2>/dev/null)

# 记录结果（调试时可查看payload）
LOG_FILE="/tmp/host-monitor-agent.log"
echo "[$(date)] Response: ${RESPONSE}" >> "$LOG_FILE"
if echo "$RESPONSE" | grep -q '"code":400'; then
    echo "[$(date)] Payload dump:" >> "$LOG_FILE"
    cat "$PAYLOAD_FILE" >> "$LOG_FILE"
    echo "" >> "$LOG_FILE"
fi

# 清理临时文件
rm -f "$PAYLOAD_FILE"

exit 0
