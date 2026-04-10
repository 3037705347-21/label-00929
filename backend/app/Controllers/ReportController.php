<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Influencer;
use App\Models\Task;
use App\Models\TaskOrder;
use App\Models\Transaction;
use App\Models\Work;
use App\Models\Withdrawal;

class ReportController extends Controller
{
    /**
     * 获取趋势数据（管理端）
     */
    public function trendData(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $type = $this->request->get('type', 'day');
            $startDate = $this->request->get('start_date');
            $endDate = $this->request->get('end_date');

            if (!$startDate) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$endDate) {
                $endDate = date('Y-m-d');
            }

            $data = $this->getTrendDataByType($type, $startDate, $endDate);

            return Response::success($data);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 获取商家趋势数据
     */
    public function merchantTrendData(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = (new Merchant())->findByUserId($user['id']);

            $type = $this->request->get('type', 'day');
            $startDate = $this->request->get('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->get('end_date', date('Y-m-d'));

            $data = $this->getMerchantTrendData($merchant['id'], $type, $startDate, $endDate);

            return Response::success($data);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 获取达人趋势数据
     */
    public function influencerTrendData(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = (new Influencer())->findByUserId($user['id']);

            $type = $this->request->get('type', 'day');
            $startDate = $this->request->get('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->get('end_date', date('Y-m-d'));

            $data = $this->getInfluencerTrendData($influencer['id'], $type, $startDate, $endDate);

            return Response::success($data);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 平台GMV报表
     */
    public function platformGMV(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $startDate = $this->request->get('start_date', date('Y-m-01'));
            $endDate = $this->request->get('end_date', date('Y-m-d'));

            $db = \Core\Database::getInstance();

            // 按日统计GMV
            $sql = "SELECT
                    DATE(created_at) as date,
                    SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) as gmv,
                    SUM(CASE WHEN type = 8 THEN amount ELSE 0 END) as platform_fee,
                    SUM(CASE WHEN type = 5 THEN amount ELSE 0 END) as influencer_income
                FROM transactions
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date";

            $dailyData = $db->fetchAll($sql, [$startDate, $endDate]);

            // 汇总数据
            $sql = "SELECT
                    SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) as total_gmv,
                    SUM(CASE WHEN type = 8 THEN amount ELSE 0 END) as total_platform_fee,
                    SUM(CASE WHEN type = 5 THEN amount ELSE 0 END) as total_influencer_income
                FROM transactions
                WHERE DATE(created_at) BETWEEN ? AND ?";

            $summary = $db->fetch($sql, [$startDate, $endDate]);

            return Response::success([
                'daily' => $dailyData,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 用户增长漏斗
     */
    public function userGrowthFunnel(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $db = \Core\Database::getInstance();

            // 用户增长数据
            $sql = "SELECT
                    DATE(created_at) as date,
                    SUM(CASE WHEN user_type = 2 THEN 1 ELSE 0 END) as merchant_count,
                    SUM(CASE WHEN user_type = 1 THEN 1 ELSE 0 END) as influencer_count
                FROM users
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date";

            $growthData = $db->fetchAll($sql);

            // 漏斗数据
            $sql = "SELECT
                    COUNT(*) as total_users,
                    SUM(CASE WHEN user_type = 2 THEN 1 ELSE 0 END) as total_merchants,
                    SUM(CASE WHEN user_type = 1 THEN 1 ELSE 0 END) as total_influencers,
                    (SELECT COUNT(*) FROM merchants WHERE verify_status = 1) as verified_merchants,
                    (SELECT COUNT(*) FROM influencers WHERE verify_status = 1) as verified_influencers,
                    (SELECT COUNT(DISTINCT merchant_id) FROM tasks) as active_merchants,
                    (SELECT COUNT(DISTINCT influencer_id) FROM task_orders WHERE status >= 2) as active_influencers
                FROM users";

            $funnel = $db->fetch($sql);

            return Response::success([
                'growth' => $growthData,
                'funnel' => $funnel,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 商家ROI分析
     */
    public function merchantROI(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = (new Merchant())->findByUserId($user['id']);

            $db = \Core\Database::getInstance();

            // 任务ROI分析
            $sql = "SELECT
                    t.id,
                    t.title,
                    t.total_budget,
                    t.spent_amount,
                    COUNT(o.id) as total_orders,
                    SUM(CASE WHEN o.status = 5 THEN 1 ELSE 0 END) as completed_orders,
                    SUM(o.final_commission) as total_commission,
                    SUM(o.cps_sales) as total_sales,
                    CASE
                        WHEN t.commission_type = 2 AND SUM(o.cps_sales) > 0
                        THEN SUM(o.final_commission) / SUM(o.cps_sales) * 100
                        ELSE 0
                    END as roi_rate
                FROM tasks t
                LEFT JOIN task_orders o ON t.id = o.task_id
                WHERE t.merchant_id = ? AND t.status IN (2, 3)
                GROUP BY t.id
                ORDER BY t.created_at DESC
                LIMIT 20";

            $tasks = $db->fetchAll($sql, [$merchant['id']]);

            return Response::success([
                'tasks' => $tasks,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人效果对比
     */
    public function influencerComparison(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = (new Merchant())->findByUserId($user['id']);

            $db = \Core\Database::getInstance();

            // 达人效果对比
            $sql = "SELECT
                    i.id,
                    i.nickname,
                    i.platform_type,
                    i.follower_count,
                    COUNT(o.id) as total_orders,
                    SUM(CASE WHEN o.status = 5 THEN 1 ELSE 0 END) as completed_orders,
                    SUM(o.final_commission) as total_cost,
                    AVG(w.view_count) as avg_views,
                    AVG(w.like_count) as avg_likes,
                    AVG(w.comment_count) as avg_comments,
                    CASE
                        WHEN SUM(o.final_commission) > 0
                        THEN SUM(w.view_count) / SUM(o.final_commission)
                        ELSE 0
                    END as cpm
                FROM influencers i
                JOIN task_orders o ON i.id = o.influencer_id
                LEFT JOIN works w ON o.id = w.order_id AND w.status = 1
                WHERE o.merchant_id = ?
                GROUP BY i.id
                ORDER BY completed_orders DESC
                LIMIT 20";

            $influencers = $db->fetchAll($sql, [$merchant['id']]);

            return Response::success([
                'influencers' => $influencers,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 佣金支出明细
     */
    public function commissionDetail(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = (new Merchant())->findByUserId($user['id']);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);
            $startDate = $this->request->get('start_date');
            $endDate = $this->request->get('end_date');

            $db = \Core\Database::getInstance();

            $where = 'o.merchant_id = ? AND o.status = 5';
            $params = [$merchant['id']];

            if ($startDate) {
                $where .= ' AND DATE(o.completed_at) >= ?';
                $params[] = $startDate;
            }
            if ($endDate) {
                $where .= ' AND DATE(o.completed_at) <= ?';
                $params[] = $endDate;
            }

            // 总数
            $countSql = "SELECT COUNT(*) as total FROM task_orders o WHERE {$where}";
            $total = (int) $db->fetch($countSql, $params)['total'];

            // 数据
            $offset = ($page - 1) * $perPage;
            $sql = "SELECT
                    o.id,
                    o.order_no,
                    o.final_commission,
                    o.commission_type,
                    o.cps_sales,
                    o.completed_at,
                    t.title as task_title,
                    i.nickname as influencer_name,
                    i.platform_type
                FROM task_orders o
                JOIN tasks t ON o.task_id = t.id
                JOIN influencers i ON o.influencer_id = i.id
                WHERE {$where}
                ORDER BY o.completed_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

            $items = $db->fetchAll($sql, $params);

            // 汇总
            $sql = "SELECT
                    SUM(o.final_commission) as total_commission,
                    SUM(o.cps_sales) as total_sales
                FROM task_orders o
                WHERE {$where}";
            $summary = $db->fetch($sql, $params);

            return Response::success([
                'items' => $items,
                'summary' => $summary,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人收益趋势
     */
    public function influencerEarnings(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = (new Influencer())->findByUserId($user['id']);

            $type = $this->request->get('type', 'day');
            $startDate = $this->request->get('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->get('end_date', date('Y-m-d'));

            $db = \Core\Database::getInstance();

            // 按时间统计收益
            $groupBy = $type === 'month' ? 'DATE_FORMAT(o.completed_at, "%Y-%m")' : 'DATE(o.completed_at)';

            $sql = "SELECT
                    {$groupBy} as date,
                    COUNT(*) as order_count,
                    SUM(o.final_commission) as earnings
                FROM task_orders o
                WHERE o.influencer_id = ?
                    AND o.status = 5
                    AND DATE(o.completed_at) BETWEEN ? AND ?
                GROUP BY {$groupBy}
                ORDER BY date";

            $trend = $db->fetchAll($sql, [$influencer['id'], $startDate, $endDate]);

            // 汇总
            $sql = "SELECT
                    COUNT(*) as total_orders,
                    SUM(o.final_commission) as total_earnings,
                    AVG(o.final_commission) as avg_earnings
                FROM task_orders o
                WHERE o.influencer_id = ? AND o.status = 5";
            $summary = $db->fetch($sql, [$influencer['id']]);

            return Response::success([
                'trend' => $trend,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人作品数据分析
     */
    public function influencerWorkStats(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = (new Influencer())->findByUserId($user['id']);

            $db = \Core\Database::getInstance();

            // 作品数据
            $sql = "SELECT
                    w.id,
                    w.title,
                    w.view_count,
                    w.like_count,
                    w.comment_count,
                    w.share_count,
                    w.platform,
                    w.publish_url,
                    w.created_at,
                    t.title as task_title,
                    o.final_commission as earnings,
                    CASE
                        WHEN w.view_count > 0
                        THEN w.like_count / w.view_count * 100
                        ELSE 0
                    END as like_rate,
                    CASE
                        WHEN w.view_count > 0 AND o.final_commission > 0
                        THEN w.view_count / o.final_commission
                        ELSE 0
                    END as cpm
                FROM works w
                JOIN task_orders o ON w.order_id = o.id
                JOIN tasks t ON o.task_id = t.id
                WHERE w.influencer_id = ? AND w.status = 1
                ORDER BY w.created_at DESC
                LIMIT 50";

            $works = $db->fetchAll($sql, [$influencer['id']]);

            // 汇总统计
            $sql = "SELECT
                    COUNT(*) as total_works,
                    SUM(w.view_count) as total_views,
                    SUM(w.like_count) as total_likes,
                    SUM(w.comment_count) as total_comments,
                    AVG(w.view_count) as avg_views,
                    AVG(w.like_count) as avg_likes
                FROM works w
                WHERE w.influencer_id = ? AND w.status = 1";
            $summary = $db->fetch($sql, [$influencer['id']]);

            return Response::success([
                'works' => $works,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 交易流水导出
     */
    public function exportTransactions(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $startDate = $this->request->get('start_date');
            $endDate = $this->request->get('end_date');
            $type = $this->request->get('type');

            $db = \Core\Database::getInstance();

            $where = '1=1';
            $params = [];

            if ($startDate) {
                $where .= ' AND DATE(t.created_at) >= ?';
                $params[] = $startDate;
            }
            if ($endDate) {
                $where .= ' AND DATE(t.created_at) <= ?';
                $params[] = $endDate;
            }
            if ($type) {
                $where .= ' AND t.type = ?';
                $params[] = $type;
            }

            $sql = "SELECT
                    t.trans_no,
                    u.username,
                    t.type,
                    t.amount,
                    t.balance_before,
                    t.balance_after,
                    t.description,
                    t.created_at
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE {$where}
                ORDER BY t.created_at DESC
                LIMIT 10000";

            $data = $db->fetchAll($sql, $params);

            return Response::success([
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 订单导出
     */
    public function exportOrders(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $startDate = $this->request->get('start_date');
            $endDate = $this->request->get('end_date');
            $status = $this->request->get('status');

            $db = \Core\Database::getInstance();

            $where = '1=1';
            $params = [];

            if ($startDate) {
                $where .= ' AND DATE(o.created_at) >= ?';
                $params[] = $startDate;
            }
            if ($endDate) {
                $where .= ' AND DATE(o.created_at) <= ?';
                $params[] = $endDate;
            }
            if ($status !== null && $status !== '') {
                $where .= ' AND o.status = ?';
                $params[] = $status;
            }

            $sql = "SELECT
                    o.order_no,
                    t.title as task_title,
                    m.shop_name,
                    i.nickname as influencer_name,
                    o.commission_type,
                    o.final_commission,
                    o.cps_sales,
                    o.status,
                    o.created_at,
                    o.completed_at
                FROM task_orders o
                JOIN tasks t ON o.task_id = t.id
                JOIN merchants m ON o.merchant_id = m.id
                JOIN influencers i ON o.influencer_id = i.id
                WHERE {$where}
                ORDER BY o.created_at DESC
                LIMIT 10000";

            $data = $db->fetchAll($sql, $params);

            return Response::success([
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 提现记录导出
     */
    public function exportWithdrawals(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $startDate = $this->request->get('start_date');
            $endDate = $this->request->get('end_date');
            $status = $this->request->get('status');

            $db = \Core\Database::getInstance();

            $where = '1=1';
            $params = [];

            if ($startDate) {
                $where .= ' AND DATE(w.created_at) >= ?';
                $params[] = $startDate;
            }
            if ($endDate) {
                $where .= ' AND DATE(w.created_at) <= ?';
                $params[] = $endDate;
            }
            if ($status !== null && $status !== '') {
                $where .= ' AND w.status = ?';
                $params[] = $status;
            }

            $sql = "SELECT
                    w.withdraw_no,
                    u.username,
                    w.amount,
                    w.fee,
                    w.actual_amount,
                    w.account_name,
                    w.account_no,
                    w.status,
                    w.created_at,
                    w.processed_at
                FROM withdrawals w
                JOIN users u ON w.user_id = u.id
                WHERE {$where}
                ORDER BY w.created_at DESC
                LIMIT 10000";

            $data = $db->fetchAll($sql, $params);

            return Response::success([
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // ========== 私有方法 ==========

    private function getTrendDataByType(string $type, string $startDate, string $endDate): array
    {
        $db = \Core\Database::getInstance();

        if ($type === 'month') {
            $dateFormat = '%Y-%m';
            $dateSelect = "DATE_FORMAT(created_at, '{$dateFormat}')";
        } else if ($type === 'week') {
            $dateSelect = "CONCAT(YEAR(created_at), '-W', WEEK(created_at))";
        } else {
            $dateSelect = 'DATE(created_at)';
        }

        // 用户注册趋势
        $sql = "SELECT
                {$dateSelect} as date,
                COUNT(*) as count
            FROM users
            WHERE created_at BETWEEN ? AND ?
            GROUP BY {$dateSelect}
            ORDER BY date";
        $userTrend = $db->fetchAll($sql, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // 任务发布趋势
        $sql = "SELECT
                {$dateSelect} as date,
                COUNT(*) as count
            FROM tasks
            WHERE created_at BETWEEN ? AND ?
            GROUP BY {$dateSelect}
            ORDER BY date";
        $taskTrend = $db->fetchAll($sql, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // 交易额趋势
        $sql = "SELECT
                {$dateSelect} as date,
                SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) as gmv,
                SUM(CASE WHEN type = 5 THEN amount ELSE 0 END) as commission
            FROM transactions
            WHERE created_at BETWEEN ? AND ?
            GROUP BY {$dateSelect}
            ORDER BY date";
        $transactionTrend = $db->fetchAll($sql, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        return [
            'user_trend' => $userTrend,
            'task_trend' => $taskTrend,
            'transaction_trend' => $transactionTrend,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
                'type' => $type,
            ],
        ];
    }

    private function getMerchantTrendData(int $merchantId, string $type, string $startDate, string $endDate): array
    {
        $db = \Core\Database::getInstance();

        if ($type === 'month') {
            $dateFormat = '%Y-%m';
            $dateSelect = "DATE_FORMAT(created_at, '{$dateFormat}')";
        } else if ($type === 'week') {
            $dateSelect = "CONCAT(YEAR(created_at), '-W', WEEK(created_at))";
        } else {
            $dateSelect = 'DATE(created_at)';
        }

        // 任务发布趋势
        $sql = "SELECT
                {$dateSelect} as date,
                COUNT(*) as count,
                SUM(total_budget) as budget
            FROM tasks
            WHERE merchant_id = ? AND created_at BETWEEN ? AND ?
            GROUP BY {$dateSelect}
            ORDER BY date";
        $taskTrend = $db->fetchAll($sql, [$merchantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // 订单趋势
        $sql = "SELECT
                {$dateSelect} as date,
                COUNT(*) as count,
                SUM(final_commission) as commission,
                SUM(cps_sales) as sales
            FROM task_orders
            WHERE merchant_id = ? AND created_at BETWEEN ? AND ?
            GROUP BY {$dateSelect}
            ORDER BY date";
        $orderTrend = $db->fetchAll($sql, [$merchantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        return [
            'task_trend' => $taskTrend,
            'order_trend' => $orderTrend,
        ];
    }

    private function getInfluencerTrendData(int $influencerId, string $type, string $startDate, string $endDate): array
    {
        $db = \Core\Database::getInstance();

        if ($type === 'month') {
            $dateFormat = '%Y-%m';
            $dateSelect = "DATE_FORMAT(created_at, '{$dateFormat}')";
        } else if ($type === 'week') {
            $dateSelect = "CONCAT(YEAR(created_at), '-W', WEEK(created_at))";
        } else {
            $dateSelect = 'DATE(created_at)';
        }

        // 订单趋势
        $sql = "SELECT
                {$dateSelect} as date,
                COUNT(*) as order_count,
                SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 5 THEN final_commission ELSE 0 END) as earnings
            FROM task_orders
            WHERE influencer_id = ? AND created_at BETWEEN ? AND ?
            GROUP BY {$dateSelect}
            ORDER BY date";
        $orderTrend = $db->fetchAll($sql, [$influencerId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // 作品数据趋势
        $sql = "SELECT
                {$dateSelect} as date,
                COUNT(*) as work_count,
                SUM(view_count) as total_views,
                SUM(like_count) as total_likes
            FROM works
            WHERE influencer_id = ? AND created_at BETWEEN ? AND ? AND status = 1
            GROUP BY {$dateSelect}
            ORDER BY date";
        $workTrend = $db->fetchAll($sql, [$influencerId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        return [
            'order_trend' => $orderTrend,
            'work_trend' => $workTrend,
        ];
    }
}
