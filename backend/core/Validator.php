<?php
namespace Core;

/**
 * 数据验证器
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $validated = [];

    private array $messages = [
        'required' => ':field不能为空',
        'string' => ':field必须是字符串',
        'integer' => ':field必须是整数',
        'numeric' => ':field必须是数字',
        'email' => ':field格式不正确',
        'phone' => ':field格式不正确',
        'min' => ':field最小值为:param',
        'max' => ':field最大值为:param',
        'min_length' => ':field最少:param个字符',
        'max_length' => ':field最多:param个字符',
        'in' => ':field值不在允许范围内',
        'date' => ':field日期格式不正确',
        'url' => ':fieldURL格式不正确',
        'array' => ':field必须是数组',
        'confirmed' => ':field两次输入不一致',
    ];

    // 字段名中文映射
    private array $fieldNames = [
        'username' => '用户名',
        'password' => '密码',
        'phone' => '手机号',
        'email' => '邮箱',
        'user_type' => '用户类型',
        'title' => '标题',
        'content' => '内容',
        'amount' => '金额',
        'status' => '状态',
        'name' => '名称',
        'shop_name' => '店铺名称',
        'nickname' => '昵称',
        'real_name' => '真实姓名',
        'id_card' => '身份证号',
        'address' => '地址',
        'city' => '城市',
        'province' => '省份',
        'category_id' => '分类',
        'task_type' => '任务类型',
        'commission_type' => '佣金类型',
        'commission_amount' => '佣金金额',
        'cps_rate' => 'CPS比例',
        'total_budget' => '总预算',
        'max_influencers' => '最大达人数',
        'start_date' => '开始日期',
        'end_date' => '结束日期',
        'requirements' => '任务要求',
        'platform_type' => '平台类型',
        'platform_account' => '平台账号',
        'follower_count' => '粉丝数',
        'follower_screenshot' => '粉丝截图',
        'account_name' => '账户名',
        'account_no' => '账号',
        'bank_name' => '银行名称',
        'order_id' => '订单ID',
        'task_id' => '任务ID',
        'stage' => '阶段',
        'note' => '备注',
        'reason' => '原因',
        'icon' => '图标',
        'sort_order' => '排序',
        'parent_id' => '父级ID',
        'verify_status' => '审核状态',
        'pay_method' => '支付方式',
    ];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * 执行验证
     */
    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $param = null;
                if (strpos($rule, ':') !== false) {
                    [$rule, $param] = explode(':', $rule, 2);
                }

                $method = 'validate' . ucfirst($rule);
                if (method_exists($this, $method)) {
                    if (!$this->$method($field, $value, $param)) {
                        break; // 一个字段只报第一个错误
                    }
                }
            }

            // 收集验证通过的数据
            if (!isset($this->errors[$field]) && isset($this->data[$field])) {
                $this->validated[$field] = $this->data[$field];
            }
        }

        return empty($this->errors);
    }

    /**
     * 获取所有错误
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 获取第一个错误
     */
    public function firstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * 获取验证通过的数据
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * 添加错误
     */
    private function addError(string $field, string $rule, ?string $param = null): void
    {
        $message = $this->messages[$rule] ?? ':field验证失败';
        // 使用中文字段名
        $fieldName = $this->fieldNames[$field] ?? $field;
        $message = str_replace(':field', $fieldName, $message);
        $message = str_replace(':param', $param ?? '', $message);
        $this->errors[$field] = $message;
    }

    // ==================== 验证规则 ====================

    private function validateRequired(string $field, $value, ?string $param): bool
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, 'required');
            return false;
        }
        return true;
    }

    private function validateString(string $field, $value, ?string $param): bool
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, 'string');
            return false;
        }
        return true;
    }

    private function validateInteger(string $field, $value, ?string $param): bool
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer');
            return false;
        }
        return true;
    }

    private function validateNumeric(string $field, $value, ?string $param): bool
    {
        if ($value !== null && !is_numeric($value)) {
            $this->addError($field, 'numeric');
            return false;
        }
        return true;
    }

    private function validateEmail(string $field, $value, ?string $param): bool
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email');
            return false;
        }
        return true;
    }

    private function validatePhone(string $field, $value, ?string $param): bool
    {
        if ($value !== null && !preg_match('/^1[3-9]\d{9}$/', $value)) {
            $this->addError($field, 'phone');
            return false;
        }
        return true;
    }

    private function validateMin(string $field, $value, ?string $param): bool
    {
        if ($value !== null && is_numeric($value) && $value < $param) {
            $this->addError($field, 'min', $param);
            return false;
        }
        return true;
    }

    private function validateMax(string $field, $value, ?string $param): bool
    {
        if ($value !== null && is_numeric($value) && $value > $param) {
            $this->addError($field, 'max', $param);
            return false;
        }
        return true;
    }

    private function validateMin_length(string $field, $value, ?string $param): bool
    {
        if ($value !== null && is_string($value) && mb_strlen($value) < $param) {
            $this->addError($field, 'min_length', $param);
            return false;
        }
        return true;
    }

    private function validateMax_length(string $field, $value, ?string $param): bool
    {
        if ($value !== null && is_string($value) && mb_strlen($value) > $param) {
            $this->addError($field, 'max_length', $param);
            return false;
        }
        return true;
    }

    private function validateIn(string $field, $value, ?string $param): bool
    {
        $allowed = explode(',', $param);
        if ($value !== null && !in_array($value, $allowed)) {
            $this->addError($field, 'in');
            return false;
        }
        return true;
    }

    private function validateDate(string $field, $value, ?string $param): bool
    {
        if ($value !== null) {
            $format = $param ?: 'Y-m-d';
            $d = \DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                $this->addError($field, 'date');
                return false;
            }
        }
        return true;
    }

    private function validateUrl(string $field, $value, ?string $param): bool
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url');
            return false;
        }
        return true;
    }

    private function validateArray(string $field, $value, ?string $param): bool
    {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, 'array');
            return false;
        }
        return true;
    }

    private function validateConfirmed(string $field, $value, ?string $param): bool
    {
        $confirmField = $field . '_confirmation';
        if ($value !== ($this->data[$confirmField] ?? null)) {
            $this->addError($field, 'confirmed');
            return false;
        }
        return true;
    }
}
