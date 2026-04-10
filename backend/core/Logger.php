<?php
namespace Core;

/**
 * 日志记录类
 */
class Logger
{
    private static array $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    private static ?string $logPath = null;
    private static string $minLevel = 'debug';

    public static function init(): void
    {
        $config = require __DIR__ . '/../config/app.php';
        self::$logPath = $config['log']['path'];
        self::$minLevel = $config['log']['level'];

        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        if (self::$logPath === null) {
            self::init();
        }

        if (self::$levels[$level] < self::$levels[self::$minLevel]) {
            return;
        }

        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');
        $file = self::$logPath . "/{$date}.log";

        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[{$time}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        file_put_contents($file, $logLine, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }
}
