<?php
namespace App\Models;

use Core\Model;

class TaskOrder extends Model
{
    protected string $table = 'task_orders';

    const STATUS_APPLIED = 1;   // 已报名
    const STATUS_ACCEPTED = 2;  // 已接单
    const STATUS_WORKING = 3;   // 执行中
    const STATUS_REVIEWING = 4; // 待审核
    const STATUS_COMPLETED = 5; // 已完成
    const STATUS_REJECTED = 6;  // 已拒绝
    const STATUS_CANCELLED = 7; // 已取消

    /**
     * 生成订单号
     */
    public function generateOrderNo(): string
    {
        return 'TO' . date('YmdHis') . mt_rand(1000, 9999);
    }

    /**
     * 获取订单详情
     */
    public function getDetail(int $id): ?array
    {
        $sql = "SELECT o.*,
                       t.title as task_title, t.task_type, t.requirements, t.target_product,
                       t.commission_type as task_commission_type, t.commission_amount as task_commission,
                       t.cps_rate as task_cps_rate, t.end_date,
                       m.shop_name, m.shop_logo, m.address, m.contact_phone,
                       i.nickname as influencer_name, i.platform_type, i.platform_account, i.follower_count,
                       u.avatar as influencer_avatar
                FROM task_orders o
                LEFT JOIN tasks t ON o.task_id = t.id
                LEFT JOIN merchants m ON o.merchant_id = m.id
                LEFT JOIN influencers i ON o.influencer_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                WHERE o.id = ?";

        return $this->db->fetch($sql, [$id]);
    }

    /**
     * 获取任务的报名列表
     */
    public function getApplications(int $taskId, int $page, int $perPage): array
    {
        $where = 'o.task_id = ?';
        $params = [$taskId];

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM task_orders o WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT o.*, i.nickname, i.platform_type, i.platform_account,
                       i.follower_count, i.avg_views, i.avg_likes, i.rating,
                       u.avatar
                FROM task_orders o
                LEFT JOIN influencers i ON o.influencer_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                WHERE {$where}
                ORDER BY o.id DESC
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
     * 达人订单列表
     */
    public function getInfluencerOrders(int $influencerId, int $page, int $perPage, array $filters = []): array
    {
        $where = 'o.influencer_id = ?';
        $params = [$influencerId];

        if (isset($filters['status'])) {
            $where .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM task_orders o WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT o.*, t.title as task_title, t.task_type, t.cover_image,
                       t.end_date, m.shop_name, m.shop_logo
                FROM task_orders o
                LEFT JOIN tasks t ON o.task_id = t.id
                LEFT JOIN merchants m ON o.merchant_id = m.id
                WHERE {$where}
                ORDER BY o.id DESC
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
     * 商家订单列表
     */
    public function getMerchantOrders(int $merchantId, int $page, int $perPage, array $filters = []): array
    {
        $where = 'o.merchant_id = ?';
        $params = [$merchantId];

        if (isset($filters['status'])) {
            $where .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['task_id'])) {
            $where .= ' AND o.task_id = ?';
            $params[] = $filters['task_id'];
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM task_orders o WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT o.*, t.title as task_title,
                       i.nickname as influencer_name, i.platform_type, i.follower_count,
                       u.avatar as influencer_avatar
                FROM task_orders o
                LEFT JOIN tasks t ON o.task_id = t.id
                LEFT JOIN influencers i ON o.influencer_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                WHERE {$where}
                ORDER BY o.id DESC
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
     * 获取订单统计
     */
    public function getStatistics(): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status IN (2,3,4) THEN 1 ELSE 0 END) as in_progress,
                    SUM(final_commission) as total_commission
                FROM task_orders";

        return $this->db->fetch($sql);
    }
}
