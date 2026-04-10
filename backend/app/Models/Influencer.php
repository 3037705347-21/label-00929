<?php
namespace App\Models;

use Core\Model;

class Influencer extends Model
{
    protected string $table = 'influencers';

    const VERIFY_PENDING = 0;
    const VERIFY_APPROVED = 1;
    const VERIFY_REJECTED = 2;

    const PLATFORM_DOUYIN = 'douyin';
    const PLATFORM_XIAOHONGSHU = 'xiaohongshu';
    const PLATFORM_KUAISHOU = 'kuaishou';

    /**
     * 根据用户ID查找
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->findBy(['user_id' => $userId]);
    }

    /**
     * 获取达人详情
     */
    public function getDetail(int $id): ?array
    {
        $sql = "SELECT i.*, u.username, u.phone, u.email, u.avatar, u.status as user_status
                FROM influencers i
                LEFT JOIN users u ON i.user_id = u.id
                WHERE i.id = ?";

        $influencer = $this->db->fetch($sql, [$id]);

        if ($influencer) {
            // 获取标签
            $tags = $this->db->fetchAll(
                "SELECT tag_name FROM influencer_tags WHERE influencer_id = ?",
                [$id]
            );
            $influencer['tags'] = array_column($tags, 'tag_name');
        }

        return $influencer;
    }

    /**
     * 获取达人列表
     */
    public function getList(int $page, int $perPage, array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (isset($filters['verify_status']) && $filters['verify_status'] !== '') {
            $where .= ' AND i.verify_status = ?';
            $params[] = $filters['verify_status'];
        }

        if (!empty($filters['platform_type'])) {
            $where .= ' AND i.platform_type = ?';
            $params[] = $filters['platform_type'];
        }

        if (!empty($filters['min_followers'])) {
            $where .= ' AND i.follower_count >= ?';
            $params[] = $filters['min_followers'];
        }

        if (!empty($filters['city'])) {
            $where .= ' AND i.city = ?';
            $params[] = $filters['city'];
        }

        if (!empty($filters['keyword'])) {
            $where .= ' AND (i.nickname LIKE ? OR i.platform_account LIKE ?)';
            $params[] = "%{$filters['keyword']}%";
            $params[] = "%{$filters['keyword']}%";
        }

        // 总数
        $countSql = "SELECT COUNT(*) as total FROM influencers i WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 数据
        $offset = ($page - 1) * $perPage;
        $orderBy = $filters['order_by'] ?? 'i.id DESC';

        $sql = "SELECT i.*, u.username, u.avatar
                FROM influencers i
                LEFT JOIN users u ON i.user_id = u.id
                WHERE {$where}
                ORDER BY {$orderBy}
                LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        // 获取标签
        foreach ($items as &$item) {
            $tags = $this->db->fetchAll(
                "SELECT tag_name FROM influencer_tags WHERE influencer_id = ?",
                [$item['id']]
            );
            $item['tags'] = array_column($tags, 'tag_name');
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 更新标签
     */
    public function updateTags(int $influencerId, array $tags): void
    {
        // 删除旧标签
        $this->db->delete('influencer_tags', 'influencer_id = ?', [$influencerId]);

        // 添加新标签
        foreach ($tags as $tag) {
            $this->db->insert('influencer_tags', [
                'influencer_id' => $influencerId,
                'tag_name' => trim($tag),
            ]);
        }
    }

    /**
     * 审核达人
     */
    public function verify(int $id, int $status, ?string $note = null): bool
    {
        return $this->update($id, [
            'verify_status' => $status,
            'verify_note' => $note,
            'verified_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    /**
     * 增加完成任务数
     */
    public function incrementCompletedTasks(int $id, float $earnings): void
    {
        $sql = "UPDATE influencers SET
                completed_tasks = completed_tasks + 1,
                total_earnings = total_earnings + ?
                WHERE id = ?";
        $this->db->query($sql, [$earnings, $id]);
    }
}
