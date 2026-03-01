#!/bin/bash
# ============================================
# Agent安装脚本
# 在被监控服务器上执行此脚本来安装采集Agent
# 用法: bash install_agent.sh <API_URL> <AGENT_KEY>
# ============================================

set -e

API_URL="${1:-http://121.196.229.4/api/collect.php}"
AGENT_KEY="${2:-YOUR_AGENT_KEY}"
INSTALL_DIR="/opt/host-monitor"

echo "======================================"
echo "  主机性能监控 Agent 安装程序"
echo "======================================"
echo ""

# 检查root权限
if [ "$(id -u)" != "0" ]; then
    echo "[错误] 请使用root用户执行此脚本"
    exit 1
fi

# 检查curl
if ! command -v curl &> /dev/null; then
    echo "[提示] 正在安装curl..."
    yum install -y curl 2>/dev/null || apt-get install -y curl 2>/dev/null
fi

# 创建安装目录
echo "[1/4] 创建安装目录..."
mkdir -p ${INSTALL_DIR}

# 下载/复制agent脚本
echo "[2/4] 安装Agent脚本..."
cat > ${INSTALL_DIR}/agent.sh << 'AGENT_EOF'
AGENT_PLACEHOLDER
AGENT_EOF

# 替换配置
sed -i "s|API_URL=\".*\"|API_URL=\"${API_URL}\"|" ${INSTALL_DIR}/agent.sh
sed -i "s|AGENT_KEY=\".*\"|AGENT_KEY=\"${AGENT_KEY}\"|" ${INSTALL_DIR}/agent.sh

chmod +x ${INSTALL_DIR}/agent.sh

# 设置crontab
echo "[3/4] 配置定时任务(每分钟采集一次)..."
CRON_LINE="* * * * * ${INSTALL_DIR}/agent.sh > /dev/null 2>&1"
(crontab -l 2>/dev/null | grep -v "host-monitor"; echo "$CRON_LINE") | crontab -

# 测试运行
echo "[4/4] 测试运行..."
bash ${INSTALL_DIR}/agent.sh
echo ""

echo "======================================"
echo "  安装完成！"
echo "  安装目录: ${INSTALL_DIR}"
echo "  API地址:  ${API_URL}"
echo "  Agent密钥: ${AGENT_KEY}"
echo "  采集间隔: 每分钟"
echo ""
echo "  管理命令:"
echo "  查看日志: tail -f /tmp/host-monitor-agent.log"
echo "  手动执行: bash ${INSTALL_DIR}/agent.sh"
echo "  卸载: crontab -l | grep -v host-monitor | crontab -"
echo "         rm -rf ${INSTALL_DIR}"
echo "======================================"
