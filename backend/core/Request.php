<?php
namespace Core;

/**
 * HTTP请求处理类
 */
class Request
{
    private array $get;
    private array $post;
    private array $server;
    private array $headers;
    private ?array $json = null;
    private array $files;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
        $this->headers = $this->parseHeaders();
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }
        return $headers;
    }

    /**
     * 获取请求方法
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * 获取请求URI
     */
    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        return $pos !== false ? substr($uri, 0, $pos) : $uri;
    }

    /**
     * 获取GET参数
     */
    public function get(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    /**
     * 获取POST参数
     */
    public function post(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    /**
     * 获取JSON请求体
     */
    public function json(string $key = null, $default = null)
    {
        if ($this->json === null) {
            $content = file_get_contents('php://input');
            $this->json = json_decode($content, true) ?? [];
        }

        if ($key === null) {
            return $this->json;
        }
        return $this->json[$key] ?? $default;
    }

    /**
     * 获取所有输入（GET + POST + JSON）
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->json() ?? []);
    }

    /**
     * 获取指定输入
     */
    public function input(string $key, $default = null)
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * 获取请求头
     */
    public function header(string $key, $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /**
     * 获取Bearer Token
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * 获取上传文件
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * 获取客户端IP
     */
    public function ip(): string
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($this->server[$key])) {
                $ip = explode(',', $this->server[$key])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    /**
     * 获取User Agent
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * 是否AJAX请求
     */
    public function isAjax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * 是否JSON请求
     */
    public function isJson(): bool
    {
        $contentType = $this->header('content-type', '');
        return strpos($contentType, 'application/json') !== false;
    }
}
