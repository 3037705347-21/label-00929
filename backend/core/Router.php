<?php
namespace Core;

/**
 * 路由器类
 */
class Router
{
    private array $routes = [];
    private array $groupStack = [];

    /**
     * 添加路由
     */
    public function addRoute(string $method, string $path, string $handler): void
    {
        $prefix = implode('', $this->groupStack);
        $fullPath = $prefix . $path;

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'handler' => $handler,
            'pattern' => $this->pathToPattern($fullPath),
        ];
    }

    /**
     * 路由分组
     */
    public function group(string $prefix, callable $callback): void
    {
        $this->groupStack[] = $prefix;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * GET路由
     */
    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * POST路由
     */
    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * PUT路由
     */
    public function put(string $path, string $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * DELETE路由
     */
    public function delete(string $path, string $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * 路径转正则
     */
    private function pathToPattern(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * 匹配路由
     */
    public function match(string $method, string $uri): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                ];
            }
        }
        return null;
    }

    /**
     * 分发请求
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        // 处理OPTIONS预检请求
        if ($method === 'OPTIONS') {
            return Response::success();
        }

        $route = $this->match($method, $uri);

        if ($route === null) {
            Logger::warning('Route not found', ['method' => $method, 'uri' => $uri]);
            return Response::error('接口不存在', 404);
        }

        return $this->callHandler($route['handler'], $request, $route['params']);
    }

    /**
     * 调用处理器
     */
    private function callHandler(string $handler, Request $request, array $params): Response
    {
        [$controllerName, $method] = explode('@', $handler);
        $controllerClass = "App\\Controllers\\{$controllerName}";

        if (!class_exists($controllerClass)) {
            Logger::error('Controller not found', ['controller' => $controllerClass]);
            return Response::error('控制器不存在', 500);
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            Logger::error('Method not found', ['controller' => $controllerClass, 'method' => $method]);
            return Response::error('方法不存在', 500);
        }

        try {
            // 注入Request和路由参数
            $controller->setRequest($request);
            $controller->setParams($params);

            return $controller->$method();
        } catch (\Exception $e) {
            Logger::error('Controller error', [
                'controller' => $controllerClass,
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $debug = (require __DIR__ . '/../config/app.php')['debug'];
            $message = $debug ? $e->getMessage() : '服务器内部错误';

            return Response::error($message, 500);
        }
    }
}
