<?php
namespace App\Models;

use Core\Model;

class Merchant extends Model
{
    protected string $table = 'merchants';

    const VERIFY_PENDING = 0;
    const VERIFY_APPROVED = 1;
    const VERIFY_REJECTED = 2;

    /**
     * 根据用户ID查找
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->findBy(['user_id' => $userId]);
    }

    /**
     * 获取商家详情（含用户信息）
     */
    public function getDetail(int $id): ?array
    {
        $sql = "SELECT m.*, u.username, u.phone, u.email, u.avatar, u.status as user_status,
                       c.name as category_name
                FROM merchants m
                LEFT JOIN users u ON m.user_id = u.id
                LEFT JOIN categories c ON m.category_id = c.id
                WHERE m.id = ?";

        return $this->db->fetch($sql, [$id]);
    }

    /**
     * 获取商家列表（管理后台）
     */
    public function getList(int $page, int $perPage, array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (isset($filters['verify_status']) && $filters['verify_status'] !== '') {
            $where .= ' AND m.verify_status = ?';
            $params[] = $filters['verify_status'];
        }

        if (!empty($filters['keyword'])) {
            $where .= ' AND (m.shop_name LIKE ? OR u.phone LIKE ?)';
            $params[] = "%{$filters['keyword']}%";
            $params[] = "%{$filters['keyword']}%";
        }

        if (!empty($filters['category_id'])) {
            $where .= ' AND m.category_id = ?';
            $params[] = $filters['category_id'];
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM merchants m
                     LEFT JOIN users u ON m.user_id = u.id WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT m.*, u.username, u.phone, u.status as user_status, c.name as category_name
                FROM merchants m
                LEFT JOIN users u ON m.user_id = u.id
                LEFT JOIN categories c ON m.category_id = c.id
                WHERE {$where}
                ORDER BY m.id DESC
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
     * 审核商家
     */
    public function verify(int $id, int $status, ?string $note = null): bool
    {
        return $this->update($id, [
            'verify_status' => $status,
            'verify_note' => $note,
            'verified_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }
}
