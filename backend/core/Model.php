<?php
namespace Core;

/**
 * 基础模型类
 */
abstract class Model
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 根据ID查找
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * 根据条件查找单条
     */
    public function findBy(array $conditions): ?array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $where[] = "{$key} = ?";
            $params[] = $value;
        }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereStr} LIMIT 1";

        return $this->db->fetch($sql, $params);
    }

    /**
     * 获取所有记录
     */
    public function all(array $conditions = [], string $orderBy = 'id DESC'): array
    {
        $where = '1=1';
        $params = [];

        foreach ($conditions as $key => $value) {
            $where .= " AND {$key} = ?";
            $params[] = $value;
        }

        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy}";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 分页查询
     */
    public function paginate(int $page = 1, int $perPage = 20, array $conditions = [], string $orderBy = 'id DESC'): array
    {
        $where = '1=1';
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                // 支持 ['>=', 100] 这种格式
                $where .= " AND {$key} {$value[0]} ?";
                $params[] = $value[1];
            } else {
                $where .= " AND {$key} = ?";
                $params[] = $value;
            }
        }

        // 获取总数
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$where}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // 获取数据
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 创建记录
     */
    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * 更新记录
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update($this->table, $data, "{$this->primaryKey} = ?", [$id]);
    }

    /**
     * 删除记录
     */
    public function delete(int $id): int
    {
        return $this->db->delete($this->table, "{$this->primaryKey} = ?", [$id]);
    }

    /**
     * 统计数量
     */
    public function count(array $conditions = []): int
    {
        $where = '1=1';
        $params = [];

        foreach ($conditions as $key => $value) {
            $where .= " AND {$key} = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$where}";
        return (int) $this->db->fetch($sql, $params)['total'];
    }

    /**
     * 自增字段
     */
    public function increment(int $id, string $field, int $amount = 1): int
    {
        $sql = "UPDATE {$this->table} SET {$field} = {$field} + ? WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->query($sql, [$amount, $id]);
        return $stmt->rowCount();
    }

    /**
     * 自减字段
     */
    public function decrement(int $id, string $field, int $amount = 1): int
    {
        $sql = "UPDATE {$this->table} SET {$field} = {$field} - ? WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->query($sql, [$amount, $id]);
        return $stmt->rowCount();
    }

    /**
     * 执行原生SQL
     */
    public function raw(string $sql, array $params = []): array
    {
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 获取数据库实例
     */
    public function getDb(): Database
    {
        return $this->db;
    }
}
