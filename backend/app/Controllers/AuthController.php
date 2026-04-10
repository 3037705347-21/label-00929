<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use App\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * 用户注册
     */
    public function register(): Response
    {
        $data = $this->validate([
            'username' => 'required|string|min_length:3|max_length:50',
            'password' => 'required|string|min_length:6',
            'user_type' => 'required|integer|in:1,2',
            'phone' => 'phone',
        ]);

        try {
            $result = $this->authService->register($data);
            $this->logOperation('auth', 'register', $result['user']['id'], 'user');
            return Response::success($result, '注册成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }
    }

    /**
     * 用户登录
     */
    public function login(): Response
    {
        $data = $this->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->authService->login(
                $data['username'],
                $data['password'],
                $this->request->ip()
            );
            return Response::success($result, '登录成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 401);
        }
    }

    /**
     * 退出登录
     */
    public function logout(): Response
    {
        // JWT无状态，客户端删除token即可
        return Response::success(null, '退出成功');
    }

    /**
     * 获取当前用户信息
     */
    public function profile(): Response
    {
        try {
            $user = $this->requireAuth();
            $profile = $this->authService->getProfile($user['id']);
            return Response::success($profile);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 401);
        }
    }
}
