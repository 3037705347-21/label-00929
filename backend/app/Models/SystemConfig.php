<?php
namespace App\Models;

use Core\Model;

class SystemConfig extends Model
{
    protected string $table = 'system_configs';

    private static array $cache = [];

    /**
     * 获取配置值
     */
    public function getValue(string $key, $default = null)
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $config = $this->findBy(['config_key' => $key]);

        if (!$config) {
            return $default;
        }

        $value = $this->castValue($config['config_value'], $config['config_type']);
        self::$cache[$key] = $value;

        return $value;
    }

    /**
     * 设置配置值
     */
    public function setValue(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        $config = $this->findBy(['config_key' => $key]);

        $data = [
            'config_value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
            'config_type' => $type,
        ];

        if ($description !== null) {
            $data['description'] = $description;
        }

        if ($config) {
            $this->update($config['id'], $data);
        } else {
            $data['config_key'] = $key;
            $this->create($data);
        }

        // 清除缓存
        unset(self::$cache[$key]);
    }

    /**
     * 获取所有配置
     */
    public function getAllConfigs(): array
    {
        $configs = $this->all();
        $result = [];

        foreach ($configs as $config) {
            $result[$config['config_key']] = [
                'value' => $this->castValue($config['config_value'], $config['config_type']),
                'type' => $config['config_type'],
                'description' => $config['description'],
            ];
        }

        return $result;
    }

    /**
     * 批量更新配置
     */
    public function batchUpdate(array $configs): void
    {
        foreach ($configs as $key => $value) {
            $this->setValue($key, $value);
        }
    }

    /**
     * 类型转换
     */
    private function castValue($value, string $type)
    {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'boolean':
                return in_array(strtolower($value), ['true', '1', 'yes']);
            case 'json':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }
}
