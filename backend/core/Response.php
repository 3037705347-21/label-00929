<?php
namespace Core;

/**
 * HTTP响应处理类
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private $content = '';

    /**
     * 设置状态码
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * 设置响应头
     */
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * JSON响应
     */
    public function json(array $data, int $status = 200): self
    {
        $this->statusCode = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->content = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this;
    }

    /**
     * 成功响应
     */
    public static function success($data = null, string $message = '操作成功'): self
    {
        $response = new self();
        return $response->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * 错误响应
     */
    public static function error(string $message = '操作失败', mixed $code = 400, $data = null): self
    {
        $code = (int) ($code ?: 400);
        $response = new self();
        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code >= 400 && $code < 600 ? $code : 400);
    }

    /**
     * 分页响应
     */
    public static function paginate(array $items, int $total, int $page, int $perPage): self
    {
        $response = new self();
        return $response->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage),
                ],
            ],
        ]);
    }

    /**
     * 发送响应
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        echo $this->content;
    }

    /**
     * 重定向
     */
    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }
}
