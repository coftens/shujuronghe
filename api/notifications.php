<?php
/**
 * 通知API
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$action = input('action', 'list');
$userId = $_SESSION['user_id'];

switch ($action) {
    
    case 'list':
        $page = max(1, intval(input('page', 1)));
        $perPage = 20;
        
        $total = db()->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id = ?", [$userId]);
        $pagination = paginate($total, $page, $perPage);
        
        $notifications = db()->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?, ?",
            [$userId, $pagination['offset'], $perPage]
        );
        
        jsonResponse(200, 'success', [
            'list' => $notifications,
            'pagination' => $pagination,
            'unread' => getUnreadCount($userId),
        ]);
        break;
    
    case 'unread_count':
        jsonResponse(200, 'success', ['count' => getUnreadCount($userId)]);
        break;
    
    case 'read':
        $id = intval(input('id'));
        if ($id) {
            db()->execute("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [$id, $userId]);
        }
        jsonResponse(200, '已读');
        break;
    
    case 'read_all':
        db()->execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$userId]);
        jsonResponse(200, '全部已读');
        break;
    
    case 'delete':
        $id = intval(input('id'));
        db()->execute("DELETE FROM notifications WHERE id = ? AND user_id = ?", [$id, $userId]);
        jsonResponse(200, '已删除');
        break;
    
    default:
        jsonResponse(400, '未知操作');
}
