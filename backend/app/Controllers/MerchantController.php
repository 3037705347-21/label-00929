<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Task;
use App\Models\TaskOrder;
use App\Models\Work;
use App\Models\Transaction;
use App\Services\TaskService;
use App\Services\WalletService;

class MerchantController extends Controller
{
    private Merchant $merchantModel;
    private TaskService $taskService;
    private WalletService $walletService;

    public function __construct()
    {
        $this->merchantModel = new Merchant();
        $this->taskService = new TaskService();
        $this->walletService = new WalletService();
    }

    /**
     * 获取店铺信息
     */
    public function profile(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);
            return Response::success($merchant);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 更新店铺信息
     */
    public function updateProfile(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $data = $this->request->all();
            $allowedFields = [
                'shop_name', 'shop_logo', 'province', 'city', 'district', 'address',
                'longitude', 'latitude', 'contact_name', 'contact_phone', 'category_id',
                'description', 'images', 'business_license'
            ];

            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (isset($updateData['images']) && is_array($updateData['images'])) {
                $updateData['images'] = json_encode($updateData['images']);
            }

            $this->merchantModel->update($merchant['id'], $updateData);
            $this->logOperation('merchant', 'update_profile', $merchant['id'], 'merchant');

            return Response::success(null, '更新成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 任务列表
     */
    public function taskList(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);
            $filters = ['status' => $this->request->get('status')];

            $taskModel = new Task();
            $result = $taskModel->getMerchantTasks($merchant['id'], $page, $perPage, array_filter($filters));

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 创建任务
     */
    public function createTask(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            if ($merchant['verify_status'] != Merchant::VERIFY_APPROVED) {
                throw new \RuntimeException('请先完成商家认证');
            }

            $data = $this->validate([
                'title' => 'required|string|max_length:200',
                'task_type' => 'required|integer|in:1,2',
                'requirements' => 'required|string',
                'commission_type' => 'required|integer|in:1,2',
                'total_budget' => 'required|numeric|min:1',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
            ]);

            $taskId = $this->taskService->createTask($merchant['id'], $user['id'], array_merge($data, $this->request->all()));
            $this->logOperation('merchant', 'create_task', $taskId, 'task');

            return Response::success(['id' => $taskId], '任务创建成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 任务详情
     */
    public function taskDetail(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $taskId = (int) $this->param('id');
            $taskModel = new Task();
            $task = $taskModel->getDetail($taskId);

            if (!$task || $task['merchant_id'] != $merchant['id']) {
                return Response::error('任务不存在', 404);
            }

            return Response::success($task);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 更新任务
     */
    public function updateTask(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $taskId = (int) $this->param('id');
            $taskModel = new Task();
            $task = $taskModel->find($taskId);

            if (!$task || $task['merchant_id'] != $merchant['id']) {
                return Response::error('任务不存在', 404);
            }

            if ($task['status'] != Task::STATUS_DRAFT) {
                return Response::error('只能编辑草稿状态的任务');
            }

            $data = $this->request->all();
            $allowedFields = [
                'title', 'cover_image', 'task_type', 'requirements', 'content_form',
                'publish_platform', 'publish_deadline', 'target_product', 'product_price',
                'product_images', 'influencer_requirements', 'min_followers',
                'commission_type', 'commission_amount', 'cps_rate', 'total_budget',
                'max_influencers', 'start_date', 'end_date'
            ];

            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (isset($updateData['product_images']) && is_array($updateData['product_images'])) {
                $updateData['product_images'] = json_encode($updateData['product_images']);
            }
            if (isset($updateData['influencer_requirements']) && is_array($updateData['influencer_requirements'])) {
                $updateData['influencer_requirements'] = json_encode($updateData['influencer_requirements']);
            }

            $taskModel->update($taskId, $updateData);
            $this->logOperation('merchant', 'update_task', $taskId, 'task');

            return Response::success(null, '更新成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 发布任务
     */
    public function publishTask(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $taskId = (int) $this->param('id');
            $this->taskService->publishTask($taskId, $merchant['id'], $user['id']);
            $this->logOperation('merchant', 'publish_task', $taskId, 'task');

            return Response::success(null, '任务发布成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 取消任务
     */
    public function cancelTask(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $taskId = (int) $this->param('id');
            $taskModel = new Task();
            $task = $taskModel->find($taskId);

            if (!$task || $task['merchant_id'] != $merchant['id']) {
                return Response::error('任务不存在', 404);
            }

            if (!in_array($task['status'], [Task::STATUS_DRAFT, Task::STATUS_PENDING])) {
                return Response::error('任务状态不允许取消');
            }

            // 如果有冻结金额，需要解冻
            if ($task['frozen_amount'] > 0) {
                $walletModel = new \App\Models\Wallet();
                $walletModel->unfreeze(
                    $user['id'],
                    $task['frozen_amount'],
                    $taskId,
                    'task',
                    "取消任务退回预算：{$task['title']}"
                );
            }

            $taskModel->update($taskId, ['status' => Task::STATUS_CANCELLED]);
            $this->logOperation('merchant', 'cancel_task', $taskId, 'task');

            return Response::success(null, '任务已取消');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 获取任务报名列表
     */
    public function applications(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $taskId = (int) $this->param('id');
            $taskModel = new Task();
            $task = $taskModel->find($taskId);

            if (!$task || $task['merchant_id'] != $merchant['id']) {
                return Response::error('任务不存在', 404);
            }

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $orderModel = new TaskOrder();
            $result = $orderModel->getApplications($taskId, $page, $perPage);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
    /**
     * 订单列表
     */
    public function orderList(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);
            $filters = [];

            $status = $this->request->get('status');
            if ($status !== null && $status !== '') {
                $filters['status'] = $status;
            }

            $taskId = $this->request->get('task_id');
            if ($taskId !== null && $taskId !== '') {
                $filters['task_id'] = $taskId;
            }

            $orderModel = new TaskOrder();
            $result = $orderModel->getMerchantOrders($merchant['id'], $page, $perPage, $filters);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }



    /**
     * 接受报名
     */
    public function acceptApplication(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $this->taskService->acceptApplication($orderId, $merchant['id']);
            $this->logOperation('merchant', 'accept_application', $orderId, 'order');

            return Response::success(null, '已接受报名');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 拒绝报名
     */
    public function rejectApplication(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $reason = $this->request->input('reason');

            $this->taskService->rejectApplication($orderId, $merchant['id'], $reason);
            $this->logOperation('merchant', 'reject_application', $orderId, 'order');

            return Response::success(null, '已拒绝报名');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 获取订单作品
     */
    public function orderWorks(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $orderModel = new TaskOrder();
            $order = $orderModel->find($orderId);

            if (!$order || $order['merchant_id'] != $merchant['id']) {
                return Response::error('订单不存在', 404);
            }

            $workModel = new Work();
            $works = $workModel->getOrderWorks($orderId);

            return Response::success($works);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 审核作品
     */
    public function reviewWork(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $workId = (int) $this->param('id');
            $approved = (bool) $this->request->input('approved');
            $note = $this->request->input('note');

            $workModel = new Work();
            $work = $workModel->find($workId);

            if (!$work) {
                return Response::error('作品不存在', 404);
            }

            // 验证订单归属
            $orderModel = new TaskOrder();
            $order = $orderModel->find($work['order_id']);

            if (!$order || $order['merchant_id'] != $merchant['id']) {
                return Response::error('无权操作', 403);
            }

            $this->taskService->reviewWork($workId, $merchant['id'], 1, $approved, $note);
            $this->logOperation('merchant', 'review_work', $workId, 'work');

            return Response::success(null, $approved ? '审核通过' : '审核拒绝');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 录入CPS销售额
     */
    public function updateCpsSales(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $orderModel = new TaskOrder();
            $order = $orderModel->find($orderId);

            if (!$order || $order['merchant_id'] != $merchant['id']) {
                return Response::error('订单不存在', 404);
            }

            // 只有CPS类型的订单才能录入销售额
            if ($order['commission_type'] != 2) {
                return Response::error('该订单不是CPS分成类型');
            }

            // 已接单、执行中、待审核、已完成状态的订单都可以录入
            if (!in_array($order['status'], [
                TaskOrder::STATUS_ACCEPTED,
                TaskOrder::STATUS_WORKING,
                TaskOrder::STATUS_REVIEWING,
                TaskOrder::STATUS_COMPLETED
            ])) {
                return Response::error('订单状态不允许此操作');
            }

            $data = $this->validate([
                'cps_sales' => 'required|numeric|min:0',
            ]);

            $orderModel->update($orderId, [
                'cps_sales' => $data['cps_sales'],
            ]);

            $this->logOperation('merchant', 'update_cps_sales', $orderId, 'order');

            return Response::success(null, 'CPS销售额已更新');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 通过订单（审核成片通过）
     */
    public function approveOrder(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $orderModel = new TaskOrder();
            $order = $orderModel->find($orderId);

            if (!$order || $order['merchant_id'] != $merchant['id']) {
                return Response::error('订单不存在', 404);
            }

            if ($order['status'] != TaskOrder::STATUS_REVIEWING) {
                return Response::error('订单状态不允许此操作');
            }

            // 获取成片作品
            $workModel = new Work();
            $work = $workModel->getLatestWork($orderId, Work::STAGE_FINAL);

            if (!$work) {
                return Response::error('未找到成片作品');
            }

            $this->taskService->reviewWork($work['id'], $merchant['id'], 1, true, null);
            $this->logOperation('merchant', 'approve_order', $orderId, 'order');

            return Response::success(null, '审核通过，佣金已结算');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 驳回订单作品
     */
    public function rejectOrderWork(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $note = $this->request->input('note');

            $orderModel = new TaskOrder();
            $order = $orderModel->find($orderId);

            if (!$order || $order['merchant_id'] != $merchant['id']) {
                return Response::error('订单不存在', 404);
            }

            if ($order['status'] != TaskOrder::STATUS_REVIEWING) {
                return Response::error('订单状态不允许此操作');
            }

            // 获取成片作品
            $workModel = new Work();
            $work = $workModel->getLatestWork($orderId, Work::STAGE_FINAL);

            if (!$work) {
                return Response::error('未找到成片作品');
            }

            $this->taskService->reviewWork($work['id'], $merchant['id'], 1, false, $note);
            $this->logOperation('merchant', 'reject_order_work', $orderId, 'order');

            return Response::success(null, '已驳回，达人需重新提交');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 完成订单
     */
    public function completeOrder(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $this->taskService->completeOrder($orderId, $merchant['id'], $user['id']);
            $this->logOperation('merchant', 'complete_order', $orderId, 'order');

            return Response::success(null, '订单已完成，佣金已结算');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 数据看板
     */
    public function dashboard(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $taskModel = new Task();
            $orderModel = new TaskOrder();

            // 任务统计
            $taskStats = $taskModel->getDb()->fetch(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as completed,
                    SUM(total_budget) as total_budget,
                    SUM(spent_amount) as total_spent
                FROM tasks WHERE merchant_id = ?",
                [$merchant['id']]
            );

            // 订单统计
            $orderStats = $orderModel->getDb()->fetch(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status IN (2,3,4) THEN 1 ELSE 0 END) as in_progress,
                    SUM(final_commission) as total_paid
                FROM task_orders WHERE merchant_id = ?",
                [$merchant['id']]
            );

            // 钱包信息
            $wallet = $this->walletService->getWallet($user['id']);

            return Response::success([
                'tasks' => $taskStats,
                'orders' => $orderStats,
                'wallet' => $wallet,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 钱包信息
     */
    public function wallet(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $wallet = $this->walletService->getWallet($user['id']);
            return Response::success($wallet);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 充值
     */
    public function recharge(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);

            $data = $this->validate([
                'amount' => 'required|numeric|min:1',
            ]);

            $wallet = $this->walletService->recharge($user['id'], $data['amount']);
            $this->logOperation('merchant', 'recharge', $user['id'], 'wallet');

            return Response::success($wallet, '充值成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 交易记录
     */
    public function transactions(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);
            $filters = [
                'type' => $this->request->get('type'),
                'start_date' => $this->request->get('start_date'),
                'end_date' => $this->request->get('end_date'),
            ];

            $result = $this->walletService->getTransactions($user['id'], $page, $perPage, array_filter($filters));

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 消息列表
     */
    public function messages(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);

            $orderId = (int) $this->request->get('order_id');
            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 50);

            $messageModel = new \App\Models\Message();
            $result = $messageModel->getOrderMessages($orderId, $page, $perPage);

            // 标记已读
            $messageModel->markAsRead($orderId, $user['id']);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 发送消息
     */
    public function sendMessage(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);
            $merchant = $this->merchantModel->findByUserId($user['id']);

            $data = $this->validate([
                'order_id' => 'required|integer',
                'content' => 'required|string',
            ]);

            // 验证订单归属
            $orderModel = new TaskOrder();
            $order = $orderModel->find($data['order_id']);

            if (!$order || $order['merchant_id'] != $merchant['id']) {
                return Response::error('订单不存在', 404);
            }

            // 获取达人用户ID
            $influencerModel = new \App\Models\Influencer();
            $influencer = $influencerModel->find($order['influencer_id']);

            $messageModel = new \App\Models\Message();
            $messageId = $messageModel->create([
                'order_id' => $data['order_id'],
                'sender_id' => $user['id'],
                'receiver_id' => $influencer['user_id'],
                'content' => $data['content'],
                'msg_type' => $this->request->input('msg_type', 1),
                'attachments' => $this->request->input('attachments') ? json_encode($this->request->input('attachments')) : null,
            ]);

            return Response::success(['id' => $messageId], '发送成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
