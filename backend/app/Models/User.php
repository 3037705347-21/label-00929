<?php
namespace App\Models;

use Core\Model;

class User extends Model
{
    protected string $table = 'users';

    const TYPE_MERCHANT = 1;
    const TYPE_INFLUENCER = 2;
    const TYPE_ADMIN = 3;

    const STATUS_DISABLED = 0;
    const STATUS_ACTIVE = 1;

    /**
     * 根据用户名查找
     */
    public function findByUsername(string $username): ?array
    {
        return $this->findBy(['username' => $username]);
    }

    /**
     * 根据手机号查找
     */
    public function findByPhone(string $phone): ?array
    {
        return $this->findBy(['phone' => $phone]);
    }

    /**
     * 创建用户
     */
    public function createUser(array $data): int
    {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);

        return $this->create($data);
    }

    /**
     * 验证密码
     */
    public function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    /**
     * 更新最后登录
     */
    public function updateLastLogin(int $id, string $ip): void
    {
        $this->update($id, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * 获取用户统计
     */
    public function getStatistics(): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN user_type = 1 THEN 1 ELSE 0 END) as merchants,
                    SUM(CASE WHEN user_type = 2 THEN 1 ELSE 0 END) as influencers,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_new
                FROM users WHERE status = 1";

        return $this->db->fetch($sql);
    }
}
