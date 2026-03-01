<?php
/**
 * 系统设置页面
 */
require_once __DIR__ . '/../includes/init.php';
requireLogin();
define('PAGE_TITLE', '系统设置');
require_once __DIR__ . '/../includes/header.php';

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$currentUser = db()->fetch("SELECT email FROM users WHERE id = ?", [$_SESSION['user_id']]);
?>

<div class="tabs mb-2">
    <div class="tab-item active" onclick="switchSettingTab('profile')">个人设置</div>
    <?php if ($isAdmin): ?>
    <div class="tab-item" onclick="switchSettingTab('system')">系统配置</div>
    <div class="tab-item" onclick="switchSettingTab('email')">邮件通知</div>
    <div class="tab-item" onclick="switchSettingTab('users')">用户管理</div>
    <?php endif; ?>
</div>

<!-- 个人设置 -->
<div id="tabProfile">
    <div class="card" style="max-width:600px;">
        <div class="card-header">个人信息</div>
        <div class="card-body">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" class="form-control" value="<?php echo e($_SESSION['username']); ?>" disabled>
            </div>
            <div class="form-group">
                <label>角色</label>
                <input type="text" class="form-control" value="<?php echo $_SESSION['user_role'] === 'admin' ? '管理员' : '查看者'; ?>" disabled>
            </div>
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" id="profileEmail" class="form-control" value="<?php echo e($currentUser['email'] ?? ''); ?>" placeholder="用于接收告警通知">
            </div>
            <button class="btn btn-primary" onclick="saveProfile()">保存</button>
        </div>
    </div>
    
    <div class="card mt-2" style="max-width:600px;">
        <div class="card-header">修改密码</div>
        <div class="card-body">
            <div class="form-group">
                <label>原密码</label>
                <input type="password" id="oldPassword" class="form-control">
            </div>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" id="newPassword" class="form-control" placeholder="至少6位">
            </div>
            <div class="form-group">
                <label>确认新密码</label>
                <input type="password" id="confirmPassword" class="form-control">
            </div>
            <button class="btn btn-primary" onclick="changePassword()">修改密码</button>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- 系统配置 -->
<div id="tabSystem" style="display:none;">
    <div class="card" style="max-width:600px;">
        <div class="card-header">系统配置</div>
        <div class="card-body" id="systemSettings">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<!-- 邮件通知 -->
<div id="tabEmail" style="display:none;">
    <div class="card" style="max-width:600px;">
        <div class="card-header">邮件通知配置</div>
        <div class="card-body" id="emailSettings">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<!-- 用户管理 -->
<div id="tabUsers" style="display:none;">
    <div class="flex-between mb-2">
        <div></div>
        <button class="btn btn-primary" onclick="showModal('addUserModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> 添加用户</button>
    </div>
    <div class="card">
        <div class="card-body">
            <table>
                <thead>
                    <tr><th>ID</th><th>用户名</th><th>邮箱</th><th>角色</th><th>最后登录</th><th>操作</th></tr>
                </thead>
                <tbody id="usersTable">
                    <tr><td colspan="6" class="text-center"><div class="spinner"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 添加用户模态框 -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <span>添加用户</span>
            <button class="modal-close" onclick="hideModal('addUserModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" id="newUsername" class="form-control">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" id="newUserPassword" class="form-control">
            </div>
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" id="newUserEmail" class="form-control">
            </div>
            <div class="form-group">
                <label>角色</label>
                <select id="newUserRole" class="form-control">
                    <option value="viewer">查看者</option>
                    <option value="admin">管理员</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="hideModal('addUserModal')">取消</button>
            <button class="btn btn-primary" onclick="addUser()">添加</button>
        </div>
    </div>
</div>

<!-- 编辑用户模态框 -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <span>编辑用户</span>
            <button class="modal-close" onclick="hideModal('editUserModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editUserId">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" id="editUsername" class="form-control" disabled style="background:#f5f5f5;">
            </div>
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" id="editUserEmail" class="form-control" placeholder="用于接收告警通知">
            </div>
            <div class="form-group">
                <label>角色</label>
                <select id="editUserRole" class="form-control">
                    <option value="viewer">查看者</option>
                    <option value="admin">管理员</option>
                </select>
            </div>
            <div class="form-group">
                <label>新密码 <small class="text-muted">（不填则不修改）</small></label>
                <input type="password" id="editUserPassword" class="form-control" placeholder="留空表示不修改">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="hideModal('editUserModal')">取消</button>
            <button class="btn btn-primary" onclick="saveEditUser()">保存</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function switchSettingTab(tab) {
    document.querySelectorAll('.tabs .tab-item').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    
    ['tabProfile', 'tabSystem', 'tabEmail', 'tabUsers'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    
    const tabMap = { profile: 'tabProfile', system: 'tabSystem', email: 'tabEmail', users: 'tabUsers' };
    const el = document.getElementById(tabMap[tab]);
    if (el) el.style.display = 'block';
    
    if (tab === 'system') loadSystemSettings();
    if (tab === 'email') loadEmailSettings();
    if (tab === 'users') loadUsers();
}

async function saveProfile() {
    const resp = await api('/api/auth.php', {
        action: 'update_profile',
        email: document.getElementById('profileEmail').value,
    }, 'POST');
    if (resp && resp.code === 200) {
        showToast('保存成功');
        // 同步更新页面显示
        document.getElementById('profileEmail').value = resp.data?.email || document.getElementById('profileEmail').value;
    } else showToast(resp?.msg || '保存失败', 'error');
}

async function changePassword() {
    const newPwd = document.getElementById('newPassword').value;
    const confirmPwd = document.getElementById('confirmPassword').value;
    
    if (newPwd !== confirmPwd) { showToast('两次密码不一致', 'error'); return; }
    
    const resp = await api('/api/auth.php', {
        action: 'change_password',
        old_password: document.getElementById('oldPassword').value,
        new_password: newPwd,
    }, 'POST');
    
    if (resp && resp.code === 200) {
        showToast('密码修改成功');
        document.getElementById('oldPassword').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
    } else {
        showToast(resp?.msg || '修改失败', 'error');
    }
}

async function loadSystemSettings() {
    const resp = await api('/api/settings.php', { action: 'get' });
    if (!resp || resp.code !== 200) return;
    const s = resp.data;
    
    document.getElementById('systemSettings').innerHTML = `
        <div class="form-group">
            <label>站点名称</label>
            <input type="text" id="set_site_name" class="form-control" value="${s.site_name || ''}">
        </div>
        <div class="form-group">
            <label>数据保留天数</label>
            <input type="number" id="set_data_retention_days" class="form-control" value="${s.data_retention_days || 90}">
        </div>
        <div class="form-group">
            <label>数据采集间隔（秒）</label>
            <input type="number" id="set_collect_interval" class="form-control" value="${s.collect_interval || 60}">
        </div>
        <div class="form-group">
            <label>告警检查间隔（秒）</label>
            <input type="number" id="set_alert_check_interval" class="form-control" value="${s.alert_check_interval || 60}">
        </div>
        <button class="btn btn-primary" onclick="saveSystemSettings()">保存</button>
    `;
}

async function saveSystemSettings() {
    const resp = await api('/api/settings.php', {
        action: 'save',
        site_name: document.getElementById('set_site_name').value,
        data_retention_days: document.getElementById('set_data_retention_days').value,
        collect_interval: document.getElementById('set_collect_interval').value,
        alert_check_interval: document.getElementById('set_alert_check_interval').value,
    }, 'POST');
    if (resp && resp.code === 200) showToast('保存成功');
}

async function loadEmailSettings() {
    const resp = await api('/api/settings.php', { action: 'get' });
    if (!resp || resp.code !== 200) return;
    const s = resp.data;
    
    document.getElementById('emailSettings').innerHTML = `
        <div class="form-group">
            <label>启用邮件通知</label>
            <select id="set_enable_email" class="form-control">
                <option value="0" ${s.enable_email_notify === '0' ? 'selected' : ''}>否</option>
                <option value="1" ${s.enable_email_notify === '1' ? 'selected' : ''}>是</option>
            </select>
        </div>
        <div class="form-group">
            <label>SMTP服务器</label>
            <input type="text" id="set_smtp_host" class="form-control" value="${s.smtp_host || ''}" placeholder="例如: smtp.qq.com">
        </div>
        <div class="form-group">
            <label>SMTP端口</label>
            <input type="number" id="set_smtp_port" class="form-control" value="${s.smtp_port || 465}">
        </div>
        <div class="form-group">
            <label>加密方式</label>
            <select id="set_smtp_encryption" class="form-control">
                <option value="ssl" ${s.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                <option value="tls" ${s.smtp_encryption === 'tls' ? 'selected' : ''}>TLS</option>
                <option value="" ${!s.smtp_encryption ? 'selected' : ''}>无</option>
            </select>
        </div>
        <div class="form-group">
            <label>SMTP用户名</label>
            <input type="text" id="set_smtp_user" class="form-control" value="${s.smtp_user || ''}">
        </div>
        <div class="form-group">
            <label>SMTP密码</label>
            <input type="password" id="set_smtp_pass" class="form-control" value="${s.smtp_pass || ''}">
        </div>
        <div class="form-group">
            <label>发件人地址</label>
            <input type="email" id="set_smtp_from" class="form-control" value="${s.smtp_from || ''}">
        </div>
        <div class="form-group" style="margin-top:16px;padding-top:16px;border-top:1px solid #eee;">
            <label>测试收件地址</label>
            <input type="email" id="set_test_email_to" class="form-control" placeholder="输入测试收件人邮箱" value="${s.smtp_user || ''}">
        </div>
        <div style="display:flex;gap:10px;margin-top:12px;">
            <button class="btn btn-primary" onclick="saveEmailSettings()">保存</button>
            <button class="btn" onclick="sendTestEmail()" style="background:#52c41a;color:#fff;">发送测试邮件</button>
        </div>
    `;
}

async function sendTestEmail() {
    const to = document.getElementById('set_test_email_to')?.value;
    if (!to) { showToast('请填写测试收件人地址', 'error'); return; }
    const btn = event.target;
    btn.disabled = true; btn.textContent = '发送中...';
    const resp = await api('/api/settings.php', {
        action: 'test_email',
        smtp_host: document.getElementById('set_smtp_host').value,
        smtp_port: document.getElementById('set_smtp_port').value,
        smtp_encryption: document.getElementById('set_smtp_encryption').value,
        smtp_user: document.getElementById('set_smtp_user').value,
        smtp_pass: document.getElementById('set_smtp_pass').value,
        smtp_from: document.getElementById('set_smtp_from').value,
        to: to,
    }, 'POST');
    btn.disabled = false; btn.textContent = '发送测试邮件';
    if (resp && resp.code === 200) showToast('测试邮件发送成功，请检查收件箱');
    else showToast(resp?.msg || '发送失败', 'error');
}

async function saveEmailSettings() {
    const resp = await api('/api/settings.php', {
        action: 'save',
        enable_email_notify: document.getElementById('set_enable_email').value,
        smtp_host: document.getElementById('set_smtp_host').value,
        smtp_port: document.getElementById('set_smtp_port').value,
        smtp_encryption: document.getElementById('set_smtp_encryption').value,
        smtp_user: document.getElementById('set_smtp_user').value,
        smtp_pass: document.getElementById('set_smtp_pass').value,
        smtp_from: document.getElementById('set_smtp_from').value,
    }, 'POST');
    if (resp && resp.code === 200) showToast('保存成功');
}

async function loadUsers() {
    const resp = await api('/api/settings.php', { action: 'users' });
    if (!resp || resp.code !== 200) return;
    
    document.getElementById('usersTable').innerHTML = resp.data.map(u => `
        <tr>
            <td>${u.id}</td>
            <td><strong>${u.username}</strong></td>
            <td>${u.email || '-'}</td>
            <td>${u.role === 'admin' ? '<span class="badge badge-info">管理员</span>' : '<span class="badge badge-default">查看者</span>'}</td>
            <td>${u.last_login || '-'}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editUser(${u.id}, '${u.username}', '${u.email || ''}', '${u.role}')"> 编辑</button>
                ${u.id != <?php echo $_SESSION['user_id']; ?> ? `<button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id}, '${u.username}')"> 删除</button>` : ''}
            </td>
        </tr>
    `).join('');
}

async function addUser() {
    const resp = await api('/api/settings.php', {
        action: 'add_user',
        username: document.getElementById('newUsername').value,
        password: document.getElementById('newUserPassword').value,
        email: document.getElementById('newUserEmail').value,
        role: document.getElementById('newUserRole').value,
    }, 'POST');
    
    if (resp && resp.code === 200) {
        showToast('添加成功');
        hideModal('addUserModal');
        loadUsers();
    } else {
        showToast(resp?.msg || '添加失败', 'error');
    }
}

async function deleteUser(id, name) {
    if (!confirmAction(`确定删除用户 "${name}" 吗？`)) return;
    const resp = await api('/api/settings.php', { action: 'delete_user', id: id }, 'POST');
    if (resp && resp.code === 200) { showToast('已删除'); loadUsers(); }
}

function editUser(id, username, email, role) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editUserEmail').value = email;
    document.getElementById('editUserRole').value = role;
    document.getElementById('editUserPassword').value = '';
    showModal('editUserModal');
}

async function saveEditUser() {
    const id = document.getElementById('editUserId').value;
    const email = document.getElementById('editUserEmail').value;
    const role = document.getElementById('editUserRole').value;
    const password = document.getElementById('editUserPassword').value;
    
    const resp = await api('/api/settings.php', {
        action: 'edit_user',
        id: id,
        email: email,
        role: role,
        password: password,
    }, 'POST');
    
    if (resp && resp.code === 200) {
        showToast('保存成功');
        hideModal('editUserModal');
        loadUsers();
    } else {
        showToast(resp?.msg || '保存失败', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
