<?php
/**
 * 探店达人营销平台 - 入口文件
 */

// 定义根目录
define('ROOT_PATH', dirname(__DIR__));

// 自动加载
spl_autoload_register(function ($class) {
    $prefixes = [
        'Core\\' => ROOT_PATH . '/core/',
        'App\\Controllers\\' => ROOT_PATH . '/app/Controllers/',
        'App\\Models\\' => ROOT_PATH . '/app/Models/',
        'App\\Services\\' => ROOT_PATH . '/app/Services/',
        'App\\Middleware\\' => ROOT_PATH . '/app/Middleware/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// 启动应用
\Core\App::getInstance()->run();
