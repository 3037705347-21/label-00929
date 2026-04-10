<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use Core\Logger;

class UploadController extends Controller
{
    private array $config;

    public function __construct()
    {
        $this->config = (require ROOT_PATH . '/config/app.php')['upload'];
    }

    /**
     * 文件上传
     */
    public function upload(): Response
    {
        try {
            $this->requireAuth();
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 401);
        }

        $file = $this->request->file('file');

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return Response::error('请选择要上传的文件');
        }

        // 检查文件大小
        if ($file['size'] > $this->config['max_size']) {
            $maxMb = $this->config['max_size'] / 1024 / 1024;
            return Response::error("文件大小不能超过{$maxMb}MB");
        }

        // 获取文件扩展名
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // 检查文件类型
        $type = $this->getFileType($ext);
        if (!$type) {
            return Response::error('不支持的文件类型');
        }

        // 生成文件名
        $filename = date('Ymd') . '/' . uniqid() . '.' . $ext;
        $filepath = $this->config['path'] . '/' . $filename;

        // 确保目录存在
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Logger::error('File upload failed', ['file' => $file['name']]);
            return Response::error('文件上传失败');
        }

        $url = $this->config['url'] . '/' . $filename;

        Logger::info('File uploaded', ['url' => $url, 'type' => $type]);

        return Response::success([
            'url' => $url,
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $type,
        ]);
    }

    /**
     * 获取文件类型
     */
    private function getFileType(string $ext): ?string
    {
        foreach ($this->config['allowed_types'] as $type => $extensions) {
            if (in_array($ext, $extensions)) {
                return $type;
            }
        }
        return null;
    }
}
