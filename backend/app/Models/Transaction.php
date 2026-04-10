<?php
namespace App\Models;

use Core\Model;

class Transaction extends Model
{
    protected string $table = 'transactions';

    const TYPE_RECHARGE = 1;    // 充值
    const TYPE_CONSUME = 2;     // 消费
    const TYPE_FREEZE = 3;      // 冻结
    const TYPE_UNFREEZE = 4;    // 解冻
    const TYPE_SETTLE = 5;      // 结算收入
    const TYPE_WITHDRAW = 6;    // 提现
    const TYPE_REFUND = 7;      // 退款
    const TYPE_PLATFORM_FEE = 8; // 平台服务费

    const STATUS_FAILED = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_PENDING = 2;

    /**
     * 生成流水号
     */
    public function generateTransNo(): string
    {
        return 'TR' . date('YmdHis') . mt_rand(100000, 999999);
    }

    /**
     * 记录流水
     */
    public function record(
        int $userId,
        int $walletId,
        int $type,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        ?int $relatedId = null,
        ?string $relatedType = null,
        ?string $description = null,
        float $frozenBefore = 0,
        float $frozenAfter = 0
    ): int {
        return $this->create([
            'trans_no' => $this->generateTransNo(),
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'frozen_before' => $frozenBefore,
            'frozen_after' => $frozenAfter,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
            'description' => $description,
            'status' => self::STATUS_SUCCESS,
        ]);
    }

    /**
     * 获取用户交易记录
     */
    public function getUserTransactions(int $userId, int $page, int $perPage, array $filters = []): array
    {
        $where = 'user_id = ?';
        $params = [$userId];

        if (!empty($filters['type'])) {
            $where .= ' AND type = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['start_date'])) {
            $where .= ' AND DATE(created_at) >= ?';
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where .= ' AND DATE(created_at) <= ?';
            $params[] = $filters['end_date'];
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM transactions WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM transactions WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取平台交易统计
     */
    public function getPlatformStatistics(string $startDate = null, string $endDate = null): array
    {
        $where = '1=1';
        $params = [];

        if ($startDate) {
            $where .= ' AND DATE(created_at) >= ?';
            $params[] = $startDate;
        }

        if ($endDate) {
            $where .= ' AND DATE(created_at) <= ?';
            $params[] = $endDate;
        }

        $sql = "SELECT
                    SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) as total_recharge,
                    SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) as total_consume,
                    SUM(CASE WHEN type = 5 THEN amount ELSE 0 END) as total_settle,
                    SUM(CASE WHEN type = 6 THEN amount ELSE 0 END) as total_withdraw,
                    SUM(CASE WHEN type = 8 THEN amount ELSE 0 END) as total_platform_fee
                FROM transactions WHERE {$where}";

        return $this->db->fetch($sql, $params);
    }

    /**
     * 获取类型名称
     */
    public static function getTypeName(int $type): string
    {
        $names = [
            self::TYPE_RECHARGE => '充值',
            self::TYPE_CONSUME => '消费',
            self::TYPE_FREEZE => '冻结',
            self::TYPE_UNFREEZE => '解冻',
            self::TYPE_SETTLE => '结算收入',
            self::TYPE_WITHDRAW => '提现',
            self::TYPE_REFUND => '退款',
            self::TYPE_PLATFORM_FEE => '平台服务费',
        ];

        return $names[$type] ?? '未知';
    }
}
