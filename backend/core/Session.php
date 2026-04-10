<?php
namespace Core;

/**
 * 会话管理类 - 基于JWT Token
 */
class Session
{
    private static array $config;

    public static function init(): void
    {
        self::$config = (require __DIR__ . '/../config/app.php')['jwt'];
    }

    /**
     * 生成Token
     */
    public static function createToken(array $user): string
    {
        if (empty(self::$config)) {
            self::init();
        }

        $header = [
            'typ' => 'JWT',
            'alg' => self::$config['algorithm'],
        ];

        $payload = [
            'iss' => 'tandianda',
            'iat' => time(),
            'exp' => time() + self::$config['expire'],
            'uid' => $user['id'],
            'type' => $user['user_type'],
        ];

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", self::$config['secret'], true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * 验证Token
     */
    public static function verifyToken(string $token): ?array
    {
        if (empty(self::$config)) {
            self::init();
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // 验证签名
        $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", self::$config['secret'], true);
        $expectedSignature = self::base64UrlEncode($signature);

        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            return null;
        }

        // 解析payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // 检查过期
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * 根据Token获取用户
     */
    public static function getUserByToken(string $token): ?array
    {
        $payload = self::verifyToken($token);

        if (!$payload || !isset($payload['uid'])) {
            return null;
        }

        $db = Database::getInstance();
        $user = $db->fetch(
            "SELECT id, username, phone, email, user_type, avatar, status FROM users WHERE id = ? AND status = 1",
            [$payload['uid']]
        );

        return $user;
    }

    /**
     * Base64 URL安全编码
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL安全解码
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
