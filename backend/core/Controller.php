<?php
namespace Core;

/**
 * 基础控制器类
 */
abstract class Controller
{
    protected ?Request $request = null;
    protected array $params = [];
    protected ?array $user = null;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * 获取路由参数
     */
    protected function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * 获取当前登录用户
     */
    protected function getUser(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();
        if (!$token) {
            return null;
        }

        $this->user = Session::getUserByToken($token);
        return $this->user;
    }

    /**
     * 获取当前用户ID
     */
    protected function getUserId(): ?int
    {
        $user = $this->getUser();
        return $user ? (int) $user['id'] : null;
    }

    /**
     * 要求登录
     */
    protected function requireAuth(): array
    {
        $user = $this->getUser();
        if (!$user) {
            throw new \RuntimeException('请先登录', 401);
        }
        return $user;
    }

    /**
     * 要求指定角色
     */
    protected function requireRole(int $role): array
    {
        $user = $this->requireAuth();
        if ((int) $user['user_type'] != $role) {
            $roleNames = [
                1 => '商家',
                2 => '达人',
                3 => '管理员',
            ];
            $currentRole = $roleNames[$user['user_type']] ?? '未知';
            $requiredRole = $roleNames[$role] ?? '未知';
            throw new \RuntimeException("当前账号为{$currentRole}，请使用{$requiredRole}账号登录", 403);
        }
        return $user;
    }

    /**
     * 验证输入
     */
    protected function validate(array $rules): array
    {
        $data = $this->request->all();
        $validator = new Validator($data, $rules);

        if (!$validator->validate()) {
            throw new \RuntimeException($validator->firstError(), 422);
        }

        return $validator->validated();
    }

    /**
     * 记录操作日志
     */
    protected function logOperation(string $module, string $action, ?int $targetId = null, ?string $targetType = null): void
    {
        $user = $this->getUser();

        $db = Database::getInstance();
        $db->insert('operation_logs', [
            'user_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'module' => $module,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'params' => json_encode($this->request->all(), JSON_UNESCAPED_UNICODE),
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
