<?php
namespace App\Services;

use App\Models\Task;
use App\Models\TaskOrder;
use App\Models\Work;
use App\Models\Wallet;
use App\Models\Influencer;
use App\Models\Merchant;
use App\Models\SystemConfig;
use Core\Database;
use Core\Logger;

class TaskService
{
    private Task $taskModel;
    private TaskOrder $orderModel;
    private Work $workModel;
    private Wallet $walletModel;
    private Influencer $influencerModel;
    private SystemConfig $configModel;

    public function __construct()
    {
        $this->taskModel = new Task();
        $this->orderModel = new TaskOrder();
        $this->workModel = new Work();
        $this->walletModel = new Wallet();
        $this->influencerModel = new Influencer();
        $this->configModel = new SystemConfig();
    }

    /**
     * 创建任务
     */
    public function createTask(int $merchantId, int $userId, array $data): int
    {
        // 检查余额
        $wallet = $this->walletModel->findByUserId($userId);
        if (!$wallet || $wallet['balance'] < $data['total_budget']) {
            throw new \RuntimeException('账户余额不足，请先充值');
        }

        $taskData = [
            'merchant_id' => $merchantId,
            'title' => $data['title'],
            'cover_image' => $data['cover_image'] ?? null,
            'task_type' => $data['task_type'],
            'requirements' => $data['requirements'],
            'content_form' => !empty($data['content_form']) ? $data['content_form'] : null,
            'publish_platform' => !empty($data['publish_platform']) ? $data['publish_platform'] : null,
            'publish_deadline' => !empty($data['publish_deadline']) ? $data['publish_deadline'] : null,
            'target_product' => !empty($data['target_product']) ? $data['target_product'] : null,
            'product_price' => !empty($data['product_price']) ? $data['product_price'] : null,
            'product_images' => !empty($data['product_images']) ? json_encode($data['product_images']) : null,
            'influencer_requirements' => !empty($data['influencer_requirements']) ? json_encode($data['influencer_requirements']) : null,
            'min_followers' => !empty($data['min_followers']) ? (int)$data['min_followers'] : 0,
            'commission_type' => $data['commission_type'],
            'commission_amount' => !empty($data['commission_amount']) ? $data['commission_amount'] : 0,
            'cps_rate' => !empty($data['cps_rate']) ? $data['cps_rate'] : 0,
            'total_budget' => $data['total_budget'],
            'max_influencers' => !empty($data['max_influencers']) ? (int)$data['max_influencers'] : 1,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => Task::STATUS_DRAFT,
        ];

        $taskId = $this->taskModel->create($taskData);

        Logger::info('Task created', ['task_id' => $taskId, 'merchant_id' => $merchantId]);

        return $taskId;
    }

    /**
     * 发布任务
     */
    public function publishTask(int $taskId, int $merchantId, int $userId): void
    {
        $task = $this->taskModel->find($taskId);

        if (!$task || $task['merchant_id'] != $merchantId) {
            throw new \RuntimeException('任务不存在');
        }

        if ($task['status'] != Task::STATUS_DRAFT && $task['status'] != Task::STATUS_REJECTED) {
            throw new \RuntimeException('任务状态不允许发布');
        }

        // 检查余额是否足够
        $wallet = $this->walletModel->findByUserId($userId);
        if (!$wallet || $wallet['balance'] < $task['total_budget']) {
            throw new \RuntimeException('账户余额不足，请先充值（需要 ¥' . $task['total_budget'] . '，当前余额 ¥' . ($wallet['balance'] ?? 0) . '）');
        }

        $db = Database::getInstance();

        $db->transaction(function($db) use ($task, $userId) {
            // 冻结预算
            $this->walletModel->freeze(
                $userId,
                $task['total_budget'],
                $task['id'],
                'task',
                "发布任务冻结预算：{$task['title']}"
            );

            // 更新任务状态
            $needReview = $this->configModel->getValue('task_review_required', true);
            $status = $needReview ? Task::STATUS_PENDING : Task::STATUS_ACTIVE;

            $this->taskModel->update($task['id'], [
                'status' => $status,
                'frozen_amount' => $task['total_budget'],
            ]);
        });

        Logger::info('Task published', ['task_id' => $taskId]);
    }

    /**
     * 达人报名任务
     */
    public function applyTask(int $taskId, int $influencerId, int $userId, ?string $note = null): int
    {
        $task = $this->taskModel->find($taskId);

        if (!$task || $task['status'] != Task::STATUS_ACTIVE) {
            throw new \RuntimeException('任务不存在或已结束');
        }

        if ($task['accepted_count'] >= $task['max_influencers']) {
            throw new \RuntimeException('任务名额已满');
        }

        // 检查是否已报名
        if ($this->taskModel->hasApplied($taskId, $influencerId)) {
            throw new \RuntimeException('您已报名过此任务');
        }

        // 检查达人资质
        $influencer = $this->influencerModel->find($influencerId);
        if ($influencer['verify_status'] != Influencer::VERIFY_APPROVED) {
            throw new \RuntimeException('请先完成达人认证');
        }

        if ($task['min_followers'] > 0 && $influencer['follower_count'] < $task['min_followers']) {
            throw new \RuntimeException("此任务要求粉丝数不低于{$task['min_followers']}");
        }

        $merchantModel = new Merchant();
        $merchant = $merchantModel->find($task['merchant_id']);

        $orderId = $this->orderModel->create([
            'order_no' => $this->orderModel->generateOrderNo(),
            'task_id' => $taskId,
            'influencer_id' => $influencerId,
            'merchant_id' => $task['merchant_id'],
            'status' => TaskOrder::STATUS_APPLIED,
            'apply_note' => $note,
            'commission_type' => $task['commission_type'],
            'commission_amount' => $task['commission_amount'],
            'cps_rate' => $task['cps_rate'],
        ]);

        // 更新任务报名数
        $this->taskModel->increment($taskId, 'applied_count');

        Logger::info('Task applied', ['order_id' => $orderId, 'task_id' => $taskId, 'influencer_id' => $influencerId]);

        return $orderId;
    }

    /**
     * 商家接受报名
     */
    public function acceptApplication(int $orderId, int $merchantId): void
    {
        $order = $this->orderModel->find($orderId);

        if (!$order || $order['merchant_id'] != $merchantId) {
            throw new \RuntimeException('订单不存在');
        }

        if ($order['status'] != TaskOrder::STATUS_APPLIED) {
            throw new \RuntimeException('订单状态不允许此操作');
        }

        $task = $this->taskModel->find($order['task_id']);
        if ($task['accepted_count'] >= $task['max_influencers']) {
            throw new \RuntimeException('任务名额已满');
        }

        $this->orderModel->update($orderId, [
            'status' => TaskOrder::STATUS_ACCEPTED,
            'accepted_at' => date('Y-m-d H:i:s'),
        ]);

        $this->taskModel->increment($order['task_id'], 'accepted_count');

        Logger::info('Application accepted', ['order_id' => $orderId]);
    }

    /**
     * 商家拒绝报名
     */
    public function rejectApplication(int $orderId, int $merchantId, ?string $reason = null): void
    {
        $order = $this->orderModel->find($orderId);

        if (!$order || $order['merchant_id'] != $merchantId) {
            throw new \RuntimeException('订单不存在');
        }

        if ($order['status'] != TaskOrder::STATUS_APPLIED) {
            throw new \RuntimeException('订单状态不允许此操作');
        }

        $this->orderModel->update($orderId, [
            'status' => TaskOrder::STATUS_REJECTED,
            'reject_reason' => $reason,
        ]);

        Logger::info('Application rejected', ['order_id' => $orderId]);
    }

    /**
     * 达人提交作品
     */
    public function submitWork(int $orderId, int $influencerId, array $data): int
    {
        $order = $this->orderModel->find($orderId);

        if (!$order || $order['influencer_id'] != $influencerId) {
            throw new \RuntimeException('订单不存在');
        }

        if (!in_array($order['status'], [TaskOrder::STATUS_ACCEPTED, TaskOrder::STATUS_WORKING])) {
            throw new \RuntimeException('订单状态不允许提交作品');
        }

        $workId = $this->workModel->create([
            'order_id' => $orderId,
            'influencer_id' => $influencerId,
            'stage' => $data['stage'],
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'file_urls' => isset($data['file_urls']) ? json_encode($data['file_urls']) : null,
            'publish_url' => $data['publish_url'] ?? null,
            'publish_time' => $data['publish_time'] ?? null,
        ]);

        // 更新订单状态
        if ($order['status'] == TaskOrder::STATUS_ACCEPTED) {
            $this->orderModel->update($orderId, [
                'status' => TaskOrder::STATUS_WORKING,
                'started_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 如果是成片，更新为待审核
        if ($data['stage'] == Work::STAGE_FINAL) {
            $this->orderModel->update($orderId, [
                'status' => TaskOrder::STATUS_REVIEWING,
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);
        }

        Logger::info('Work submitted', ['work_id' => $workId, 'order_id' => $orderId]);

        return $workId;
    }

    /**
     * 审核作品
     */
    public function reviewWork(int $workId, int $reviewerId, int $reviewerType, bool $approved, ?string $note = null): void
    {
        $work = $this->workModel->find($workId);

        if (!$work) {
            throw new \RuntimeException('作品不存在');
        }

        $status = $approved ? Work::REVIEW_APPROVED : Work::REVIEW_REJECTED;
        $this->workModel->review($workId, $status, $reviewerId, $reviewerType, $note);

        // 如果是成片
        if ($work['stage'] == Work::STAGE_FINAL) {
            if ($approved) {
                // 成片审核通过，更新订单状态为已完成
                $order = $this->orderModel->find($work['order_id']);
                if ($order && $order['status'] == TaskOrder::STATUS_REVIEWING) {
                    // 获取商家信息进行结算
                    $merchantModel = new Merchant();
                    $merchant = $merchantModel->find($order['merchant_id']);

                    if ($merchant) {
                        $this->completeOrderInternal($order, $merchant['user_id']);
                    }
                }
            } else {
                // 成片被拒绝，订单回到执行中
                $this->orderModel->update($work['order_id'], [
                    'status' => TaskOrder::STATUS_WORKING,
                ]);
            }
        }

        Logger::info('Work reviewed', ['work_id' => $workId, 'approved' => $approved]);
    }

    /**
     * 内部完成订单方法（审核通过后自动调用）
     */
    private function completeOrderInternal(array $order, int $merchantUserId): void
    {
        $db = Database::getInstance();

        $db->transaction(function($db) use ($order, $merchantUserId) {
            // 计算佣金
            $commission = $order['commission_amount'];
            if ($order['commission_type'] == Task::COMMISSION_CPS && isset($order['cps_sales']) && $order['cps_sales'] > 0) {
                $commission = $order['cps_sales'] * $order['cps_rate'] / 100;
            }

            // 计算平台服务费
            $feeRate = $this->configModel->getValue('platform_fee_rate', 10);
            $platformFee = $commission * $feeRate / 100;
            $influencerAmount = $commission - $platformFee;

            // 从商家冻结金额扣除
            $task = $this->taskModel->find($order['task_id']);
            $this->walletModel->consume(
                $merchantUserId,
                $commission,
                $order['id'],
                'order',
                "任务结算：" . ($task['title'] ?? '任务')
            );

            // 给达人结算
            $influencer = $this->influencerModel->find($order['influencer_id']);
            $this->walletModel->settle(
                $influencer['user_id'],
                $influencerAmount,
                $order['id'],
                'order',
                "任务收入：" . ($task['title'] ?? '任务')
            );

            // 更新订单
            $this->orderModel->update($order['id'], [
                'status' => TaskOrder::STATUS_COMPLETED,
                'final_commission' => $influencerAmount,
                'platform_fee' => $platformFee,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            // 更新任务完成数
            $this->taskModel->increment($order['task_id'], 'completed_count');
            $this->taskModel->increment($order['task_id'], 'spent_amount', $commission);

            // 更新达人统计
            $this->influencerModel->incrementCompletedTasks($order['influencer_id'], $influencerAmount);
        });

        Logger::info('Order auto completed after work approved', ['order_id' => $order['id']]);
    }

    /**
     * 完成订单并结算
     */
    public function completeOrder(int $orderId, int $merchantId, int $merchantUserId): void
    {
        $order = $this->orderModel->getDetail($orderId);

        if (!$order || $order['merchant_id'] != $merchantId) {
            throw new \RuntimeException('订单不存在');
        }

        if ($order['status'] != TaskOrder::STATUS_REVIEWING) {
            throw new \RuntimeException('订单状态不允许此操作');
        }

        // 检查成片是否已审核通过
        $finalWork = $this->workModel->getLatestWork($orderId, Work::STAGE_FINAL);
        if (!$finalWork || $finalWork['review_status'] != Work::REVIEW_APPROVED) {
            throw new \RuntimeException('请先审核通过成片');
        }

        $db = Database::getInstance();

        $db->transaction(function($db) use ($order, $merchantUserId) {
            // 计算佣金
            $commission = $order['commission_amount'];
            if ($order['commission_type'] == Task::COMMISSION_CPS && $order['cps_sales'] > 0) {
                $commission = $order['cps_sales'] * $order['cps_rate'] / 100;
            }

            // 计算平台服务费
            $feeRate = $this->configModel->getValue('platform_fee_rate', 10);
            $platformFee = $commission * $feeRate / 100;
            $influencerAmount = $commission - $platformFee;

            // 从商家冻结金额扣除
            $this->walletModel->consume(
                $merchantUserId,
                $commission,
                $order['id'],
                'order',
                "任务结算：{$order['task_title']}"
            );

            // 给达人结算
            $influencer = $this->influencerModel->find($order['influencer_id']);
            $this->walletModel->settle(
                $influencer['user_id'],
                $influencerAmount,
                $order['id'],
                'order',
                "任务收入：{$order['task_title']}"
            );

            // 更新订单
            $this->orderModel->update($order['id'], [
                'status' => TaskOrder::STATUS_COMPLETED,
                'final_commission' => $influencerAmount,
                'platform_fee' => $platformFee,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            // 更新任务完成数
            $this->taskModel->increment($order['task_id'], 'completed_count');
            $this->taskModel->increment($order['task_id'], 'spent_amount', $commission);

            // 更新达人统计
            $this->influencerModel->incrementCompletedTasks($order['influencer_id'], $influencerAmount);
        });

        Logger::info('Order completed', ['order_id' => $orderId]);
    }
}
