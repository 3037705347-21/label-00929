<?php
/**
 * 应用配置文件
 */

return [
    // 应用名称
    'name' => '探店达人营销平台',

    // 调试模式
    'debug' => getenv('APP_DEBUG') ?: false,

    // 应用URL
    'url' => getenv('APP_URL') ?: 'http://localhost',

    // 时区
    'timezone' => 'Asia/Shanghai',

    // 字符编码
    'charset' => 'UTF-8',

    // Session配置
    'session' => [
        'name'     => 'TANDIANDA_SESSION',
        'lifetime' => 7200, // 2小时
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
    ],

    // 上传配置
    'upload' => [
        'path'       => __DIR__ . '/../storage/uploads',
        'url'        => '/uploads',
        'max_size'   => 10 * 1024 * 1024, // 10MB
        'allowed_types' => [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'video' => ['mp4', 'mov', 'avi'],
            'file'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
        ],
    ],

    // 分页配置
    'pagination' => [
        'per_page' => 20,
        'max_per_page' => 100,
    ],

    // JWT配置
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production',
        'expire' => 86400 * 7, // 7天
        'algorithm' => 'HS256',
    ],

    // 日志配置
    'log' => [
        'path'  => __DIR__ . '/../storage/logs',
        'level' => 'debug', // debug, info, warning, error
    ],

    // 支付配置
    'payment' => [
        // 微信支付配置
        'wechat' => [
            'app_id' => getenv('WECHAT_PAY_APP_ID') ?: '',
            'mch_id' => getenv('WECHAT_PAY_MCH_ID') ?: '',
            'api_key' => getenv('WECHAT_PAY_API_KEY') ?: '',
            'api_v3_key' => getenv('WECHAT_PAY_API_V3_KEY') ?: '',
            'cert_path' => getenv('WECHAT_PAY_CERT_PATH') ?: '',
            'key_path' => getenv('WECHAT_PAY_KEY_PATH') ?: '',
        ],
        // 支付宝配置
        'alipay' => [
            'app_id' => getenv('ALIPAY_APP_ID') ?: '',
            'private_key' => getenv('ALIPAY_PRIVATE_KEY') ?: '',
            'public_key' => getenv('ALIPAY_PUBLIC_KEY') ?: '',
        ],
    ],
];
