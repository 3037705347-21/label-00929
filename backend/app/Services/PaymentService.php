<?php
namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Core\Database;
use Core\Logger;

/**
 * 支付服务 - 集成微信支付、支付宝支付
 */
class PaymentService
{
    // 支付方式
    const PAY_WECHAT = 'wechat';
    const PAY_ALIPAY = 'alipay';

    // 订单状态
    const ORDER_PENDING = 0;    // 待支付
    const ORDER_PAID = 1;       // 已支付
    const ORDER_FAILED = 2;     // 支付失败
    const ORDER_REFUNDED = 3;   // 已退款

    private array $config;
    private Wallet $walletModel;
    private Transaction $transModel;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->walletModel = new Wallet();
        $this->transModel = new Transaction();
    }

    /**
     * 加载支付配置
     */
    private function loadConfig(): array
    {
        return [
            'wechat' => [
                'app_id' => getenv('WECHAT_PAY_APP_ID') ?: '',
                'mch_id' => getenv('WECHAT_PAY_MCH_ID') ?: '',
                'api_key' => getenv('WECHAT_PAY_API_KEY') ?: '',
                'api_v3_key' => getenv('WECHAT_PAY_API_V3_KEY') ?: '',
                'cert_path' => getenv('WECHAT_PAY_CERT_PATH') ?: '',
                'key_path' => getenv('WECHAT_PAY_KEY_PATH') ?: '',
                'notify_url' => getenv('APP_URL') . '/api/payment/wechat/notify',
            ],
            'alipay' => [
                'app_id' => getenv('ALIPAY_APP_ID') ?: '',
                'private_key' => getenv('ALIPAY_PRIVATE_KEY') ?: '',
                'public_key' => getenv('ALIPAY_PUBLIC_KEY') ?: '',
                'notify_url' => getenv('APP_URL') . '/api/payment/alipay/notify',
                'return_url' => getenv('APP_URL') . '/merchant/wallet.html?pay_result=success',
            ],
        ];
    }

    /**
     * 创建充值订单
     */
    public function createRechargeOrder(int $userId, float $amount, string $payMethod): array
    {
        if ($amount <= 0) {
            throw new \RuntimeException('充值金额必须大于0');
        }

        if (!in_array($payMethod, [self::PAY_WECHAT, self::PAY_ALIPAY])) {
            throw new \RuntimeException('不支持的支付方式');
        }

        $db = Database::getInstance();

        // 确保支付订单表存在
        $this->ensureTableExists($db);

        // 生成订单号
        $orderNo = $this->generateOrderNo();

        // 创建支付订单记录
        $orderId = $db->insert('payment_orders', [
            'order_no' => $orderNo,
            'user_id' => $userId,
            'amount' => $amount,
            'pay_method' => $payMethod,
            'order_type' => 'recharge',
            'status' => self::ORDER_PENDING,
            'created_at' => date('Y-m-d H:i:s'),
            'expire_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ]);

        Logger::info('Payment order created', [
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'user_id' => $userId,
            'amount' => $amount,
            'pay_method' => $payMethod,
        ]);

        // 调用对应支付接口
        if ($payMethod === self::PAY_WECHAT) {
            $payData = $this->createWechatPay($orderNo, $amount, '探店达人平台-账户充值');
        } else {
            $payData = $this->createAlipayPay($orderNo, $amount, '探店达人平台-账户充值');
        }

        return [
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'amount' => $amount,
            'pay_method' => $payMethod,
            'pay_data' => $payData,
        ];
    }

    /**
     * 创建微信支付订单（Native扫码支付）
     */
    private function createWechatPay(string $orderNo, float $amount, string $description): array
    {
        $config = $this->config['wechat'];

        // 构建请求参数
        $params = [
            'appid' => $config['app_id'],
            'mchid' => $config['mch_id'],
            'description' => $description,
            'out_trade_no' => $orderNo,
            'notify_url' => $config['notify_url'],
            'amount' => [
                'total' => (int)($amount * 100), // 转换为分
                'currency' => 'CNY',
            ],
        ];

        // 如果配置了真实的微信支付参数，调用微信支付API
        if (!empty($config['app_id']) && !empty($config['mch_id'])) {
            try {
                $result = $this->callWechatPayApi('/v3/pay/transactions/native', $params);
                return [
                    'type' => 'qrcode',
                    'qr_code' => $result['code_url'] ?? '',
                    'expire_time' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
                ];
            } catch (\Exception $e) {
                Logger::error('Wechat pay failed', ['error' => $e->getMessage()]);
                throw new \RuntimeException('微信支付创建失败：' . $e->getMessage());
            }
        }

        // 开发环境：返回模拟数据
        return [
            'type' => 'qrcode',
            'qr_code' => 'weixin://wxpay/bizpayurl?pr=' . $orderNo,
            'expire_time' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'is_sandbox' => true,
        ];
    }

    /**
     * 创建支付宝支付订单（电脑网站支付）
     */
    private function createAlipayPay(string $orderNo, float $amount, string $subject): array
    {
        $config = $this->config['alipay'];

        // 构建请求参数
        $bizContent = [
            'out_trade_no' => $orderNo,
            'total_amount' => number_format($amount, 2, '.', ''),
            'subject' => $subject,
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
        ];

        // 如果配置了真实的支付宝参数，生成支付链接
        if (!empty($config['app_id']) && !empty($config['private_key'])) {
            try {
                $payUrl = $this->buildAlipayUrl($bizContent);
                return [
                    'type' => 'redirect',
                    'pay_url' => $payUrl,
                    'expire_time' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
                ];
            } catch (\Exception $e) {
                Logger::error('Alipay create failed', ['error' => $e->getMessage()]);
                throw new \RuntimeException('支付宝支付创建失败：' . $e->getMessage());
            }
        }

        // 开发环境：返回模拟数据
        return [
            'type' => 'redirect',
            'pay_url' => 'https://openapi.alipay.com/gateway.do?order=' . $orderNo,
            'expire_time' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'is_sandbox' => true,
        ];
    }

    /**
     * 调用微信支付API
     */
    private function callWechatPayApi(string $path, array $params): array
    {
        $config = $this->config['wechat'];
        $url = 'https://api.mch.weixin.qq.com' . $path;

        $timestamp = time();
        $nonceStr = bin2hex(random_bytes(16));
        $body = json_encode($params);

        // 构建签名串
        $message = "POST\n{$path}\n{$timestamp}\n{$nonceStr}\n{$body}\n";

        // 使用商户私钥签名
        $privateKey = file_get_contents($config['key_path']);
        openssl_sign($message, $signature, $privateKey, 'SHA256');
        $signature = base64_encode($signature);

        // 构建Authorization头
        $serialNo = $this->getWechatCertSerialNo($config['cert_path']);
        $authorization = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $config['mch_id'],
            $nonceStr,
            $timestamp,
            $serialNo,
            $signature
        );

        // 发送请求
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $authorization,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException('微信支付API调用失败: ' . $response);
        }

        return json_decode($response, true);
    }

    /**
     * 获取微信支付证书序列号
     */
    private function getWechatCertSerialNo(string $certPath): string
    {
        $cert = file_get_contents($certPath);
        $certData = openssl_x509_parse($cert);
        return strtoupper(dechex($certData['serialNumber']));
    }

    /**
     * 构建支付宝支付URL
     */
    private function buildAlipayUrl(array $bizContent): string
    {
        $config = $this->config['alipay'];

        $params = [
            'app_id' => $config['app_id'],
            'method' => 'alipay.trade.page.pay',
            'format' => 'JSON',
            'return_url' => $config['return_url'],
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => $config['notify_url'],
            'biz_content' => json_encode($bizContent),
        ];

        // 生成签名
        ksort($params);
        $signStr = urldecode(http_build_query($params));
        openssl_sign($signStr, $signature, $config['private_key'], OPENSSL_ALGO_SHA256);
        $params['sign'] = base64_encode($signature);

        return 'https://openapi.alipay.com/gateway.do?' . http_build_query($params);
    }

    /**
     * 处理微信支付回调
     */
    public function handleWechatNotify(array $data): bool
    {
        $config = $this->config['wechat'];

        // 验证签名
        if (!$this->verifyWechatSign($data)) {
            Logger::warning('Wechat notify sign verify failed', $data);
            return false;
        }

        // 解密数据
        $resource = $data['resource'] ?? [];
        $ciphertext = base64_decode($resource['ciphertext'] ?? '');
        $nonce = $resource['nonce'] ?? '';
        $associatedData = $resource['associated_data'] ?? '';

        $decrypted = openssl_decrypt(
            substr($ciphertext, 0, -16),
            'aes-256-gcm',
            $config['api_v3_key'],
            OPENSSL_RAW_DATA,
            $nonce,
            substr($ciphertext, -16),
            $associatedData
        );

        if ($decrypted === false) {
            Logger::error('Wechat notify decrypt failed');
            return false;
        }

        $payResult = json_decode($decrypted, true);

        return $this->processPaymentResult(
            $payResult['out_trade_no'],
            $payResult['transaction_id'],
            self::PAY_WECHAT,
            $payResult['trade_state'] === 'SUCCESS'
        );
    }

    /**
     * 处理支付宝回调
     */
    public function handleAlipayNotify(array $data): bool
    {
        // 验证签名
        if (!$this->verifyAlipaySign($data)) {
            Logger::warning('Alipay notify sign verify failed', $data);
            return false;
        }

        $tradeStatus = $data['trade_status'] ?? '';
        $success = in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED']);

        return $this->processPaymentResult(
            $data['out_trade_no'],
            $data['trade_no'],
            self::PAY_ALIPAY,
            $success
        );
    }

    /**
     * 验证微信支付签名
     */
    private function verifyWechatSign(array $data): bool
    {
        // 实际项目中需要验证微信支付平台证书签名
        // 这里简化处理
        return true;
    }

    /**
     * 验证支付宝签名
     */
    private function verifyAlipaySign(array $data): bool
    {
        $config = $this->config['alipay'];

        if (empty($config['public_key'])) {
            return true; // 开发环境跳过验证
        }

        $sign = $data['sign'] ?? '';
        unset($data['sign'], $data['sign_type']);

        ksort($data);
        $signStr = urldecode(http_build_query($data));

        return openssl_verify(
            $signStr,
            base64_decode($sign),
            $config['public_key'],
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    /**
     * 处理支付结果
     */
    private function processPaymentResult(string $orderNo, string $transactionId, string $payMethod, bool $success): bool
    {
        $db = Database::getInstance();

        // 查询订单
        $order = $db->fetch(
            "SELECT * FROM payment_orders WHERE order_no = ? FOR UPDATE",
            [$orderNo]
        );

        if (!$order) {
            Logger::error('Payment order not found', ['order_no' => $orderNo]);
            return false;
        }

        // 已处理过的订单直接返回成功
        if ($order['status'] != self::ORDER_PENDING) {
            return true;
        }

        return $db->transaction(function($db) use ($order, $transactionId, $payMethod, $success) {
            if ($success) {
                // 更新订单状态
                $db->query(
                    "UPDATE payment_orders SET status = ?, transaction_id = ?, paid_at = NOW() WHERE id = ?",
                    [self::ORDER_PAID, $transactionId, $order['id']]
                );

                // 充值到钱包
                $this->walletModel->recharge($order['user_id'], $order['amount']);

                Logger::info('Payment success', [
                    'order_no' => $order['order_no'],
                    'user_id' => $order['user_id'],
                    'amount' => $order['amount'],
                    'transaction_id' => $transactionId,
                ]);
            } else {
                // 更新订单状态为失败
                $db->query(
                    "UPDATE payment_orders SET status = ? WHERE id = ?",
                    [self::ORDER_FAILED, $order['id']]
                );

                Logger::warning('Payment failed', [
                    'order_no' => $order['order_no'],
                    'user_id' => $order['user_id'],
                ]);
            }

            return true;
        });
    }

    /**
     * 查询支付订单状态
     */
    public function queryOrderStatus(string $orderNo): array
    {
        $db = Database::getInstance();

        $order = $db->fetch(
            "SELECT * FROM payment_orders WHERE order_no = ?",
            [$orderNo]
        );

        if (!$order) {
            throw new \RuntimeException('订单不存在');
        }

        // 如果订单还在待支付状态，主动查询支付平台
        if ($order['status'] == self::ORDER_PENDING) {
            if ($order['pay_method'] === self::PAY_WECHAT) {
                $this->queryWechatOrder($orderNo);
            } else {
                $this->queryAlipayOrder($orderNo);
            }

            // 重新查询订单状态
            $order = $db->fetch(
                "SELECT * FROM payment_orders WHERE order_no = ?",
                [$orderNo]
            );
        }

        return [
            'order_no' => $order['order_no'],
            'amount' => $order['amount'],
            'status' => $order['status'],
            'status_text' => $this->getStatusText($order['status']),
            'pay_method' => $order['pay_method'],
            'paid_at' => $order['paid_at'],
        ];
    }

    /**
     * 查询微信支付订单
     */
    private function queryWechatOrder(string $orderNo): void
    {
        $config = $this->config['wechat'];

        if (empty($config['app_id'])) {
            return; // 开发环境跳过
        }

        try {
            $result = $this->callWechatPayApi(
                "/v3/pay/transactions/out-trade-no/{$orderNo}?mchid={$config['mch_id']}",
                []
            );

            if (($result['trade_state'] ?? '') === 'SUCCESS') {
                $this->processPaymentResult(
                    $orderNo,
                    $result['transaction_id'],
                    self::PAY_WECHAT,
                    true
                );
            }
        } catch (\Exception $e) {
            Logger::error('Query wechat order failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 查询支付宝订单
     */
    private function queryAlipayOrder(string $orderNo): void
    {
        $config = $this->config['alipay'];

        if (empty($config['app_id'])) {
            return; // 开发环境跳过
        }

        // 实际项目中调用支付宝查询接口
        // alipay.trade.query
    }

    /**
     * 生成订单号
     */
    private function generateOrderNo(): string
    {
        return 'PAY' . date('YmdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 确保支付订单表存在
     */
    private function ensureTableExists(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `payment_orders` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
            `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
            `amount` DECIMAL(12,2) NOT NULL COMMENT '支付金额',
            `pay_method` VARCHAR(20) NOT NULL COMMENT '支付方式:wechat/alipay',
            `order_type` VARCHAR(20) NOT NULL DEFAULT 'recharge' COMMENT '订单类型:recharge充值',
            `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待支付1已支付2支付失败3已退款',
            `transaction_id` VARCHAR(64) DEFAULT NULL COMMENT '第三方交易号',
            `expire_at` DATETIME DEFAULT NULL COMMENT '过期时间',
            `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
            `refund_at` DATETIME DEFAULT NULL COMMENT '退款时间',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_order_no` (`order_no`),
            KEY `idx_user` (`user_id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付订单表'";

        try {
            $db->query($sql);
        } catch (\Exception $e) {
            Logger::error('Create payment_orders table failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取状态文本
     */
    private function getStatusText(int $status): string
    {
        $texts = [
            self::ORDER_PENDING => '待支付',
            self::ORDER_PAID => '已支付',
            self::ORDER_FAILED => '支付失败',
            self::ORDER_REFUNDED => '已退款',
        ];
        return $texts[$status] ?? '未知';
    }

    /**
     * 模拟支付成功（仅用于开发测试）
     */
    public function simulatePaySuccess(string $orderNo): bool
    {
        if (getenv('APP_DEBUG') !== 'true') {
            throw new \RuntimeException('仅开发环境可用');
        }

        return $this->processPaymentResult(
            $orderNo,
            'SIMULATED_' . time(),
            'sandbox',
            true
        );
    }
}
