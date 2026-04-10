<?php
namespace App\Models;

use Core\Model;

class Task extends Model
{
    protected string $table = 'tasks';

    const TYPE_CLOUD = 1;      // 云探店
    const TYPE_VISIT = 2;      // 到店实探

    const COMMISSION_FIXED = 1; // 一口价
    const COMMISSION_CPS = 2;   // CPS分成

    const STATUS_DRAFT = 0;
    const STATUS_PENDING = 1;   // 审核中
    const STATUS_ACTIVE = 2;    // 进行中
    const STATUS_ENDED = 3;     // 已结束
    const STATUS_CANCELLED = 4; // 已取消
    const STATUS_REJECTED = 5;  // 审核拒绝

    /**
     * 获取任务详情
     */
    public function getDetail(int $id): ?array
    {
        $sql = "SELECT t.*, m.shop_name, m.shop_logo, m.address, m.city,
                       c.name as category_name
                FROM tasks t
                LEFT JOIN merchants m ON t.merchant_id = m.id
                LEFT JOIN categories c ON m.category_id = c.id
                WHERE t.id = ?";

        return $this->db->fetch($sql, [$id]);
    }

    /**
     * 商家任务列表
     */
    public function getMerchantTasks(int $merchantId, int $page, int $perPage, array $filters = []): array
    {
        $where = 't.merchant_id = ?';
        $params = [$merchantId];

        if (isset($filters['status'])) {
            $where .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM tasks t WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT t.* FROM tasks t WHERE {$where} ORDER BY t.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 任务大厅列表（达人端）
     */
    public function getTaskHall(int $page, int $perPage, array $filters = []): array
    {
        $where = 't.status = 2 AND t.end_date >= CURDATE() AND t.accepted_count < t.max_influencers';
        $params = [];

        if (!empty($filters['task_type'])) {
            $where .= ' AND t.task_type = ?';
            $params[] = $filters['task_type'];
        }

        if (!empty($filters['commission_type'])) {
            $where .= ' AND t.commission_type = ?';
            $params[] = $filters['commission_type'];
        }

        if (!empty($filters['category_id'])) {
            $where .= ' AND m.category_id = ?';
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['city'])) {
            $where .= ' AND (m.city = ? OR m.city LIKE ?)';
            $params[] = $filters['city'];
            $params[] = '%' . $filters['city'] . '%';
        }

        if (!empty($filters['min_commission'])) {
            $where .= ' AND t.commission_amount >= ?';
            $params[] = $filters['min_commission'];
        }

        if (!empty($filters['max_commission'])) {
            $where .= ' AND t.commission_amount <= ?';
            $params[] = $filters['max_commission'];
        }

        if (!empty($filters['keyword'])) {
            $where .= ' AND (t.title LIKE ? OR m.shop_name LIKE ?)';
            $params[] = "%{$filters['keyword']}%";
            $params[] = "%{$filters['keyword']}%";
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM tasks t
                     LEFT JOIN merchants m ON t.merchant_id = m.id WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $orderBy = $filters['order_by'] ?? 't.id DESC';

        $sql = "SELECT t.*, m.shop_name, m.shop_logo, m.city, c.name as category_name
                FROM tasks t
                LEFT JOIN merchants m ON t.merchant_id = m.id
                LEFT JOIN categories c ON m.category_id = c.id
                WHERE {$where}
                ORDER BY {$orderBy}
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
     * 管理后台任务列表
     */
    public function getAdminList(int $page, int $perPage, array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['merchant_id'])) {
            $where .= ' AND t.merchant_id = ?';
            $params[] = $filters['merchant_id'];
        }

        if (!empty($filters['keyword'])) {
            $where .= ' AND (t.title LIKE ? OR m.shop_name LIKE ?)';
            $params[] = "%{$filters['keyword']}%";
            $params[] = "%{$filters['keyword']}%";
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM tasks t
                     LEFT JOIN merchants m ON t.merchant_id = m.id WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT t.*, m.shop_name, m.shop_logo
                FROM tasks t
                LEFT JOIN merchants m ON t.merchant_id = m.id
                WHERE {$where}
                ORDER BY t.id DESC
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
     * 获取任务统计
     */
    public function getStatistics(): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as completed,
                    SUM(total_budget) as total_budget,
                    SUM(spent_amount) as total_spent
                FROM tasks";

        return $this->db->fetch($sql);
    }

    /**
     * 检查达人是否已报名
     */
    public function hasApplied(int $taskId, int $influencerId): bool
    {
        $sql = "SELECT id FROM task_orders WHERE task_id = ? AND influencer_id = ?";
        return $this->db->fetch($sql, [$taskId, $influencerId]) !== null;
    }

    /**
     * 获取推荐任务（定向派单）
     * 基于达人的粉丝量、内容风格、历史数据等多维度匹配
     */
    public function getRecommendedTasks(array $influencer, int $limit = 10): array
    {
        // 基础条件：进行中、未过期、有名额
        $baseSql = "SELECT t.*, m.shop_name, m.shop_logo, m.city, m.category_id,
                           c.name as category_name,
                           0 as match_score
                    FROM tasks t
                    LEFT JOIN merchants m ON t.merchant_id = m.id
                    LEFT JOIN categories c ON m.category_id = c.id
                    WHERE t.status = 2
                      AND t.end_date >= CURDATE()
                      AND t.accepted_count < t.max_influencers
                      AND t.id NOT IN (
                          SELECT task_id FROM task_orders WHERE influencer_id = ?
                      )";

        $params = [$influencer['id']];
        $tasks = $this->db->fetchAll($baseSql, $params);

        // 计算匹配分数
        foreach ($tasks as &$task) {
            $score = 0;

            // 1. 粉丝量匹配 (30分)
            // 达人粉丝量满足任务要求得满分，超出越多分数越高
            $followerCount = (int)($influencer['follower_count'] ?? 0);
            $minFollowers = (int)($task['min_followers'] ?? 0);
            if ($minFollowers == 0 || $followerCount >= $minFollowers) {
                $score += 30;
                // 粉丝量超出要求的，额外加分（最多10分）
                if ($minFollowers > 0 && $followerCount > $minFollowers) {
                    $ratio = min($followerCount / $minFollowers, 3);
                    $score += min(($ratio - 1) * 5, 10);
                }
            }

            // 2. 城市匹配 (20分)
            // 到店实探任务，同城优先
            if ($task['task_type'] == self::TYPE_VISIT) {
                $influencerCity = $influencer['city'] ?? '';
                $taskCity = $task['city'] ?? '';
                if (!empty($influencerCity) && !empty($taskCity) && $influencerCity == $taskCity) {
                    $score += 20;
                }
            } else {
                // 云探店不限城市，直接得分
                $score += 15;
            }

            // 3. 平台匹配 (15分)
            // 根据任务发布平台和达人擅长平台匹配
            $publishPlatform = strtolower($task['publish_platform'] ?? '');
            $influencerPlatform = strtolower($influencer['platform_type'] ?? '');
            if (!empty($publishPlatform) && !empty($influencerPlatform)) {
                if (strpos($publishPlatform, $influencerPlatform) !== false ||
                    strpos($influencerPlatform, $publishPlatform) !== false) {
                    $score += 15;
                }
            } else {
                $score += 10; // 未指定平台，给基础分
            }

            // 4. 内容风格匹配 (15分)
            $contentStyle = $influencer['content_style'] ?? '';
            $contentForm = $task['content_form'] ?? '';
            if (!empty($contentStyle) && !empty($contentForm)) {
                // 简单匹配：如果内容风格包含任务要求的形式
                if (stripos($contentStyle, $contentForm) !== false ||
                    stripos($contentForm, $contentStyle) !== false) {
                    $score += 15;
                } else {
                    $score += 5; // 基础分
                }
            } else {
                $score += 8;
            }

            // 5. 历史表现 (20分)
            // 根据达人的平均播放量、点赞量、评分等
            $avgViews = (int)($influencer['avg_views'] ?? 0);
            $avgLikes = (int)($influencer['avg_likes'] ?? 0);
            $rating = (float)($influencer['rating'] ?? 0);
            $completedTasks = (int)($influencer['completed_tasks'] ?? 0);

            // 有完成任务经验加分
            if ($completedTasks > 0) {
                $score += min($completedTasks * 2, 8);
            }

            // 评分加分
            if ($rating > 0) {
                $score += min($rating * 2, 10);
            }

            // 数据表现加分
            if ($avgViews > 10000) {
                $score += 2;
            }
            if ($avgLikes > 500) {
                $score += 2;
            }

            $task['match_score'] = $score;
        }

        // 按匹配分数排序
        usort($tasks, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });

        // 返回前N个
        return array_slice($tasks, 0, $limit);
    }
}
