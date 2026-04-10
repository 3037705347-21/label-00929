<?php
namespace App\Services;

use App\Models\User;
use App\Models\Merchant;
use App\Models\Influencer;
use App\Models\Wallet;
use Core\Session;
use Core\Logger;
use Core\Database;

class AuthService
{
    private User $userModel;
    private Merchant $merchantModel;
    private Influencer $influencerModel;
    private Wallet $walletModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->merchantModel = new Merchant();
        $this->influencerModel = new Influencer();
        $this->walletModel = new Wallet();
    }

    /**
     * 用户注册
     */
    public function register(array $data): array
    {
        // 检查用户名是否存在
        if ($this->userModel->findByUsername($data['username'])) {
            throw new \RuntimeException('用户名已存在');
        }

        // 检查手机号是否存在
        if (!empty($data['phone']) && $this->userModel->findByPhone($data['phone'])) {
            throw new \RuntimeException('手机号已被注册');
        }

        $db = Database::getInstance();

        return $db->transaction(function($db) use ($data) {
            // 创建用户
            $userId = $this->userModel->createUser([
                'username' => $data['username'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'user_type' => $data['user_type'],
            ]);

            // 根据用户类型创建对应资料
            if ($data['user_type'] == User::TYPE_MERCHANT) {
                $this->merchantModel->create([
                    'user_id' => $userId,
                    'shop_name' => $data['shop_name'] ?? $data['username'] . '的店铺',
                ]);
            } elseif ($data['user_type'] == User::TYPE_INFLUENCER) {
                $this->influencerModel->create([
                    'user_id' => $userId,
                    'nickname' => $data['nickname'] ?? $data['username'],
                    'platform_type' => $data['platform_type'] ?? 'douyin',
                    'platform_account' => $data['platform_account'] ?? '',
                ]);
            }

            // 创建钱包
            $this->walletModel->create(['user_id' => $userId]);

            Logger::info('User registered', ['user_id' => $userId, 'type' => $data['user_type']]);

            $user = $this->userModel->find($userId);

            // 注册后不返回token，需要管理员审核通过后才能登录
            return [
                'user' => $this->formatUser($user),
                'message' => '注册成功，请等待管理员审核',
            ];
        });
    }

    /**
     * 用户登录
     */
    public function login(string $username, string $password, string $ip): array
    {
        // 支持用户名或手机号登录
        $user = $this->userModel->findByUsername($username);
        if (!$user) {
            $user = $this->userModel->findByPhone($username);
        }

        if (!$user) {
            throw new \RuntimeException('用户不存在');
        }

        if ($user['status'] != User::STATUS_ACTIVE) {
            throw new \RuntimeException('账号已被禁用');
        }

        if (!$this->userModel->verifyPassword($user, $password)) {
            throw new \RuntimeException('密码错误');
        }

        // 检查商家/达人审核状态（管理员不需要审核）
        if ($user['user_type'] == User::TYPE_MERCHANT) {
            $merchant = $this->merchantModel->findByUserId($user['id']);
            if ($merchant) {
                if ($merchant['verify_status'] != Merchant::VERIFY_APPROVED) {
                    if ($merchant['verify_status'] == Merchant::VERIFY_PENDING) {
                        throw new \RuntimeException('您的商家账号正在审核中，请耐心等待');
                    } else {
                        throw new \RuntimeException('您的商家账号审核未通过，请联系管理员');
                    }
                }
            }
        } elseif ($user['user_type'] == User::TYPE_INFLUENCER) {
            $influencer = $this->influencerModel->findByUserId($user['id']);
            if ($influencer) {
                if ($influencer['verify_status'] != Influencer::VERIFY_APPROVED) {
                    if ($influencer['verify_status'] == Influencer::VERIFY_PENDING) {
                        throw new \RuntimeException('您的达人账号正在审核中，请耐心等待');
                    } else {
                        throw new \RuntimeException('您的达人账号审核未通过，请联系管理员');
                    }
                }
            }
        }

        // 更新最后登录
        $this->userModel->updateLastLogin($user['id'], $ip);

        // 生成Token
        $token = Session::createToken($user);

        Logger::info('User logged in', ['user_id' => $user['id'], 'ip' => $ip]);

        return [
            'user' => $this->formatUser($user),
            'token' => $token,
        ];
    }

    /**
     * 获取用户信息
     */
    public function getProfile(int $userId): array
    {
        $user = $this->userModel->find($userId);

        if (!$user) {
            throw new \RuntimeException('用户不存在');
        }

        $profile = $this->formatUser($user);

        // 获取扩展信息
        if ($user['user_type'] == User::TYPE_MERCHANT) {
            $merchant = $this->merchantModel->findByUserId($userId);
            $profile['merchant'] = $merchant;
        } elseif ($user['user_type'] == User::TYPE_INFLUENCER) {
            $influencer = $this->influencerModel->findByUserId($userId);
            if ($influencer) {
                // 获取标签
                $db = Database::getInstance();
                $tags = $db->fetchAll(
                    "SELECT tag_name FROM influencer_tags WHERE influencer_id = ?",
                    [$influencer['id']]
                );
                $influencer['tags'] = array_column($tags, 'tag_name');
            }
            $profile['influencer'] = $influencer;
        }

        // 获取钱包信息
        $wallet = $this->walletModel->findByUserId($userId);
        $profile['wallet'] = $wallet;

        return $profile;
    }

    /**
     * 格式化用户信息
     */
    private function formatUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'phone' => $user['phone'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
            'avatar' => $user['avatar'],
            'status' => $user['status'],
            'created_at' => $user['created_at'],
        ];
    }
}
