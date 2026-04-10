<?php
namespace App\Models;

use Core\Model;

class Withdrawal extends Model
{
    protected string $table = 'withdrawals';

    const STATUS_PENDING = 0;   // 待审核
    const STATUS_APPROVED = 1;  // 已通过
    const STATUS_REJECTED = 2;  // 已拒绝
    const STATUS_PAID = 3;      // 已打款
    const STATUS_FAILED = 4;    // 打款失败

    const TYPE_BANK = 1;
    const TYPE_ALIPAY = 2;
    const TYPE_WECHAT = 3;

    /**
     * 生成提现单号
     */
    public function generateWithdrawNo(): string
    {
        return 'WD' . date('YmdHis') . mt_rand(1000, 9999);
    }

    /**
     * 获取用户提现记录
     */
    public function getUserWithdrawals(int $userId, int $page, int $perPage): array
    {
        $where = 'user_id = ?';
        $params = [$userId];

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM withdrawals WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM withdrawals WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取待处理提现列表（管理后台）
     */
    public function getPendingList(int $page, int $perPage, array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (isset($filters['status'])) {
            $where .= ' AND w.status = ?';
            $params[] = $filters['status'];
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM withdrawals w WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT w.*, u.username, u.phone, u.user_type
                FROM withdrawals w
                LEFT JOIN users u ON w.user_id = u.id
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
     * 处理提现
     */
    public function process(int $id, int $status, int $reviewerId, ?string $note = null): bool
    {
        $data = [
            'status' => $status,
            'review_note' => $note,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === self::STATUS_PAID) {
            $data['paid_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($id, $data) > 0;
    }
}
