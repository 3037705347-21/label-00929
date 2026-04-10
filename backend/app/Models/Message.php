<?php
namespace App\Models;

use Core\Model;

class Message extends Model
{
    protected string $table = 'messages';

    const TYPE_TEXT = 1;
    const TYPE_IMAGE = 2;
    const TYPE_FILE = 3;

    /**
     * 获取订单消息列表
     */
    public function getOrderMessages(int $orderId, int $page, int $perPage): array
    {
        $where = 'order_id = ?';
        $params = [$orderId];

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM messages WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT m.*, u.username, u.avatar
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE {$where}
                ORDER BY m.id ASC
                LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取用户未读消息数
     */
    public function getUnreadCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
        $result = $this->db->fetch($sql, [$userId]);
        return (int) $result['count'];
    }

    /**
     * 标记已读
     */
    public function markAsRead(int $orderId, int $userId): void
    {
        $sql = "UPDATE messages SET is_read = 1, read_at = NOW() WHERE order_id = ? AND receiver_id = ? AND is_read = 0";
        $this->db->query($sql, [$orderId, $userId]);
    }
}
