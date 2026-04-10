<?php
namespace App\Models;

use Core\Model;

class Showcase extends Model
{
    protected string $table = 'showcase_works';

    /**
     * 获取内容广场列表
     * 同时从showcase_works表和已完成订单的works表获取数据
     */
    public function getList(int $page, int $perPage, array $filters = []): array
    {
        $params = [];

        // 构建筛选条件
        $showcaseWhere = 's.status = 1';
        $worksWhere = 'w.review_status = 1 AND w.stage = 3 AND o.status = 5';

        if (!empty($filters['is_featured'])) {
            $showcaseWhere .= ' AND s.is_featured = 1';
            $worksWhere .= ' AND 0'; // works表没有精选字段，精选筛选时不显示works数据
        }

        if (!empty($filters['platform'])) {
            $showcaseWhere .= ' AND s.platform = ?';
            $worksWhere .= ' AND i.platform_type = ?';
            $params[] = $filters['platform'];
            $params[] = $filters['platform'];
        }

        if (!empty($filters['influencer_id'])) {
            $showcaseWhere .= ' AND s.influencer_id = ?';
            $worksWhere .= ' AND w.influencer_id = ?';
            $params[] = $filters['influencer_id'];
            $params[] = $filters['influencer_id'];
        }

        // 使用UNION合并两个数据源
        $unionSql = "
            SELECT
                s.id, s.title, s.cover_image, s.platform, s.publish_url,
                s.view_count, s.like_count, s.comment_count,
                s.influencer_id, s.is_featured, s.created_at,
                i.nickname as influencer_name, i.platform_type, i.follower_count,
                u.avatar as influencer_avatar,
                'showcase' as source
            FROM showcase_works s
            LEFT JOIN influencers i ON s.influencer_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE {$showcaseWhere}

            UNION ALL

            SELECT
                w.id, COALESCE(w.title, t.title) as title,
                COALESCE((SELECT JSON_UNQUOTE(JSON_EXTRACT(w.file_urls, '$[0]'))), '') as cover_image,
                i.platform_type as platform, w.publish_url,
                COALESCE(w.view_count, 0) as view_count,
                COALESCE(w.like_count, 0) as like_count,
                COALESCE(w.comment_count, 0) as comment_count,
                w.influencer_id, 0 as is_featured, w.created_at,
                i.nickname as influencer_name, i.platform_type, i.follower_count,
                u.avatar as influencer_avatar,
                'work' as source
            FROM works w
            INNER JOIN task_orders o ON w.order_id = o.id
            INNER JOIN tasks t ON o.task_id = t.id
            INNER JOIN influencers i ON w.influencer_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE {$worksWhere}
        ";

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM ({$unionSql}) as combined";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM ({$unionSql}) as combined ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取精选案例
     */
    public function getFeatured(int $limit = 10): array
    {
        // 精选案例优先从showcase_works获取，不足时从works补充
        $sql = "SELECT s.*, i.nickname as influencer_name, i.platform_type, i.follower_count,
                       u.avatar as influencer_avatar
                FROM showcase_works s
                LEFT JOIN influencers i ON s.influencer_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                WHERE s.status = 1 AND s.is_featured = 1
                ORDER BY s.sort_order DESC, s.view_count DESC
                LIMIT ?";

        $showcaseItems = $this->db->fetchAll($sql, [$limit]);

        // 如果精选不足，从已完成的works补充
        $remaining = $limit - count($showcaseItems);
        if ($remaining > 0) {
            $worksSql = "SELECT
                    w.id, COALESCE(w.title, t.title) as title,
                    '' as cover_image,
                    i.platform_type as platform, w.publish_url,
                    COALESCE(w.view_count, 0) as view_count,
                    COALESCE(w.like_count, 0) as like_count,
                    COALESCE(w.comment_count, 0) as comment_count,
                    w.influencer_id, 0 as is_featured,
                    i.nickname as influencer_name, i.platform_type, i.follower_count,
                    u.avatar as influencer_avatar
                FROM works w
                INNER JOIN task_orders o ON w.order_id = o.id
                INNER JOIN tasks t ON o.task_id = t.id
                INNER JOIN influencers i ON w.influencer_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                WHERE w.review_status = 1 AND w.stage = 3 AND o.status = 5
                ORDER BY w.view_count DESC, w.id DESC
                LIMIT ?";

            $worksItems = $this->db->fetchAll($worksSql, [$remaining]);
            $showcaseItems = array_merge($showcaseItems, $worksItems);
        }

        return $showcaseItems;
    }

    /**
     * 获取达人作品集
     */
    public function getInfluencerPortfolio(int $influencerId, int $limit = 20): array
    {
        // 合并showcase_works和works表的数据
        $sql = "SELECT id, title, cover_image, publish_url, view_count, like_count, comment_count, created_at
                FROM showcase_works
                WHERE influencer_id = ? AND status = 1

                UNION ALL

                SELECT w.id, COALESCE(w.title, t.title) as title, '' as cover_image,
                       w.publish_url, COALESCE(w.view_count, 0), COALESCE(w.like_count, 0),
                       COALESCE(w.comment_count, 0), w.created_at
                FROM works w
                INNER JOIN task_orders o ON w.order_id = o.id
                INNER JOIN tasks t ON o.task_id = t.id
                WHERE w.influencer_id = ? AND w.review_status = 1 AND w.stage = 3 AND o.status = 5

                ORDER BY created_at DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$influencerId, $influencerId, $limit]);
    }

    /**
     * 增加浏览量
     */
    public function incrementView(int $id): void
    {
        $sql = "UPDATE showcase_works SET view_count = view_count + 1 WHERE id = ?";
        $this->db->query($sql, [$id]);
    }
}
