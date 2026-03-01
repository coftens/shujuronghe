#!/bin/bash
# ============================================
# Agent安装脚本
# 在被监控服务器上执行此脚本来安装采集Agent
# 用法: curl -sSL http://SERVER/agent/install_agent.sh | bash -s -- <API_URL> <AGENT_KEY>
# ============================================

API_URL="${1}"
AGENT_KEY="${2}"
INSTALL_DIR="/opt/host-monitor"

if [ -z "$API_URL" ] || [ -z "$AGENT_KEY" ]; then
    echo "[错误] 用法: bash install_agent.sh <API_URL> <AGENT_KEY>"
    echo "  例如: bash install_agent.sh http://121.196.229.47:31132/api/collect.php abc123"
    exit 1
fi

# 获取服务器基础URL（从API_URL推导）
BASE_URL=$(echo "$API_URL" | sed 's|/api/collect.php||')

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
echo "[1/4] 创建安装目录 ${INSTALL_DIR} ..."
mkdir -p ${INSTALL_DIR}

# 从服务器下载agent.sh
echo "[2/4] 下载Agent脚本..."
curl -sSL "${BASE_URL}/agent/agent.sh" -o ${INSTALL_DIR}/agent.sh
if [ ! -s ${INSTALL_DIR}/agent.sh ]; then
    echo "[错误] 下载agent.sh失败，请检查URL: ${BASE_URL}/agent/agent.sh"
    exit 1
fi

# 写入配置
sed -i "s|^API_URL=.*|API_URL=\"${API_URL}\"|" ${INSTALL_DIR}/agent.sh
sed -i "s|^AGENT_KEY=.*|AGENT_KEY=\"${AGENT_KEY}\"|" ${INSTALL_DIR}/agent.sh

chmod +x ${INSTALL_DIR}/agent.sh

# 设置crontab
echo "[3/4] 配置定时任务(每分钟采集一次)..."
CRON_LINE="* * * * * ${INSTALL_DIR}/agent.sh >> /tmp/host-monitor-agent.log 2>&1"
(crontab -l 2>/dev/null | grep -v "host-monitor"; echo "$CRON_LINE") | crontab -

# 测试运行
echo "[4/4] 测试运行..."
bash ${INSTALL_DIR}/agent.sh
RESULT=$?
echo ""

if [ $RESULT -eq 0 ]; then
    echo "======================================"
    echo "  ✓ 安装完成！"
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
else
    echo "[警告] 测试运行返回错误码: $RESULT"
    echo "请检查 API_URL 和 AGENT_KEY 是否正确"
fi
