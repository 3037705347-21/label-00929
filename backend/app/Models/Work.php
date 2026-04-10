<?php
namespace App\Models;

use Core\Model;

class Work extends Model
{
    protected string $table = 'works';

    const STAGE_SCRIPT = 1;   // 脚本
    const STAGE_MATERIAL = 2; // 素材
    const STAGE_FINAL = 3;    // 成片

    const REVIEW_PENDING = 0;
    const REVIEW_APPROVED = 1;
    const REVIEW_REJECTED = 2;

    /**
     * 获取订单的作品列表
     */
    public function getOrderWorks(int $orderId): array
    {
        $sql = "SELECT * FROM works WHERE order_id = ? ORDER BY stage ASC, id DESC";
        return $this->db->fetchAll($sql, [$orderId]);
    }

    /**
     * 获取最新阶段作品
     */
    public function getLatestWork(int $orderId, int $stage): ?array
    {
        $sql = "SELECT * FROM works WHERE order_id = ? AND stage = ? ORDER BY id DESC LIMIT 1";
        return $this->db->fetch($sql, [$orderId, $stage]);
    }

    /**
     * 获取待审核作品列表（管理后台）
     */
    public function getPendingWorks(int $page, int $perPage): array
    {
        $where = 'w.review_status = 0';
        $params = [];

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM works w WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT w.*, o.order_no, t.title as task_title,
                       i.nickname as influencer_name, m.shop_name
                FROM works w
                LEFT JOIN task_orders o ON w.order_id = o.id
                LEFT JOIN tasks t ON o.task_id = t.id
                LEFT JOIN influencers i ON w.influencer_id = i.id
                LEFT JOIN merchants m ON o.merchant_id = m.id
                WHERE {$where}
                ORDER BY w.id DESC
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
     * 获取作品列表（管理后台，支持状态筛选）
     */
    public function getAdminWorkList(int $page, int $perPage, $status = null): array
    {
        $where = '1=1';
        $params = [];

        if ($status !== null && $status !== '') {
            $where .= ' AND w.review_status = ?';
            $params[] = $status;
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM works w WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT w.*, o.order_no, t.title as task_title,
                       i.nickname as influencer_name, m.shop_name
                FROM works w
                LEFT JOIN task_orders o ON w.order_id = o.id
                LEFT JOIN tasks t ON o.task_id = t.id
                LEFT JOIN influencers i ON w.influencer_id = i.id
                LEFT JOIN merchants m ON o.merchant_id = m.id
                WHERE {$where}
                ORDER BY w.id DESC
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
     * 获取作品详情
     */
    public function getWorkDetail(int $id): ?array
    {
        $sql = "SELECT w.*, o.order_no, t.title as task_title,
                       i.nickname as influencer_name, m.shop_name
                FROM works w
                LEFT JOIN task_orders o ON w.order_id = o.id
                LEFT JOIN tasks t ON o.task_id = t.id
                LEFT JOIN influencers i ON w.influencer_id = i.id
                LEFT JOIN merchants m ON o.merchant_id = m.id
                WHERE w.id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * 审核作品
     */
    public function review(int $id, int $status, int $reviewerId, int $reviewerType, ?string $note = null): bool
    {
        return $this->update($id, [
            'review_status' => $status,
            'review_note' => $note,
            'reviewer_id' => $reviewerId,
            'reviewer_type' => $reviewerType,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }
}
