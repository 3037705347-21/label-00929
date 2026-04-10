<?php
namespace App\Models;

use Core\Model;

class Category extends Model
{
    protected string $table = 'categories';

    /**
     * 获取分类树
     */
    public function getTree(int $parentId = 0): array
    {
        $sql = "SELECT * FROM categories WHERE parent_id = ? AND status = 1 ORDER BY sort_order ASC";
        $items = $this->db->fetchAll($sql, [$parentId]);

        foreach ($items as &$item) {
            $item['children'] = $this->getTree($item['id']);
        }

        return $items;
    }

    /**
     * 获取所有分类（扁平）
     */
    public function getAllFlat(): array
    {
        $sql = "SELECT * FROM categories WHERE status = 1 ORDER BY parent_id ASC, sort_order ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * 检查是否有子分类
     */
    public function hasChildren(int $id): bool
    {
        $sql = "SELECT COUNT(*) as count FROM categories WHERE parent_id = ?";
        $result = $this->db->fetch($sql, [$id]);
        return $result['count'] > 0;
    }
}
