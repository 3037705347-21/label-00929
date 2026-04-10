<?php
namespace Core;

/**
 * 应用入口类
 */
class App
{
    private static ?App $instance = null;
    private array $config;
    private Router $router;

    private function __construct()
    {
        $this->config = require __DIR__ . '/../config/app.php';
        $this->init();
    }

    public static function getInstance(): App
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init(): void
    {
        // 设置时区
        date_default_timezone_set($this->config['timezone']);

        // 设置错误处理
        $this->setupErrorHandling();

        // 设置CORS
        $this->setupCors();

        // 初始化日志
        Logger::init();

        // 加载路由
        $this->router = require __DIR__ . '/../config/routes.php';
    }

    private function setupErrorHandling(): void
    {
        if ($this->config['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        // 全局异常处理
        set_exception_handler(function (\Throwable $e) {
            Logger::error('Uncaught exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $code = $e->getCode();
            if ($code < 400 || $code >= 600) {
                $code = 500;
            }

            $message = $this->config['debug'] ? $e->getMessage() : '服务器内部错误';

            Response::error($message, $code)->send();
        });

        // 全局错误处理
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    private function setupCors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * 运行应用
     */
    public function run(): void
    {
        $request = new Request();

        Logger::info('Request received', [
            'method' => $request->method(),
            'uri' => $request->uri(),
            'ip' => $request->ip(),
        ]);

        $response = $this->router->dispatch($request);
        $response->send();
    }
}
