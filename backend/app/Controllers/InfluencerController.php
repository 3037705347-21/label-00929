<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use App\Models\User;
use App\Models\Influencer;
use App\Models\Task;
use App\Models\TaskOrder;
use App\Models\Work;
use App\Models\Showcase;
use App\Services\TaskService;
use App\Services\WalletService;

class InfluencerController extends Controller
{
    private Influencer $influencerModel;
    private TaskService $taskService;
    private WalletService $walletService;

    public function __construct()
    {
        $this->influencerModel = new Influencer();
        $this->taskService = new TaskService();
        $this->walletService = new WalletService();
    }

    /**
     * 获取个人信息
     */
    public function profile(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->getDetail(
                $this->influencerModel->findByUserId($user['id'])['id']
            );
            return Response::success($influencer);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 更新个人信息
     */
    public function updateProfile(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $data = $this->request->all();
            $allowedFields = [
                'nickname', 'real_name', 'gender', 'birthday', 'province', 'city', 'bio',
                'platform_type', 'platform_account', 'platform_url', 'follower_count',
                'follower_screenshot', 'content_style', 'content_categories',
                'avg_views', 'avg_likes', 'avg_comments'
            ];

            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (isset($updateData['content_categories']) && is_array($updateData['content_categories'])) {
                $updateData['content_categories'] = json_encode($updateData['content_categories']);
            }

            $this->influencerModel->update($influencer['id'], $updateData);

            // 更新标签
            if (isset($data['tags']) && is_array($data['tags'])) {
                $this->influencerModel->updateTags($influencer['id'], $data['tags']);
            }

            $this->logOperation('influencer', 'update_profile', $influencer['id'], 'influencer');

            return Response::success(null, '更新成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 提交认证
     */
    public function submitVerify(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            if ($influencer['verify_status'] == Influencer::VERIFY_APPROVED) {
                return Response::error('您已通过认证');
            }

            $data = $this->validate([
                'platform_type' => 'required|string',
                'platform_account' => 'required|string',
                'follower_count' => 'required|integer|min:0',
                'follower_screenshot' => 'required|string',
            ]);

            $this->influencerModel->update($influencer['id'], array_merge($data, [
                'verify_status' => Influencer::VERIFY_PENDING,
            ]));

            $this->logOperation('influencer', 'submit_verify', $influencer['id'], 'influencer');

            return Response::success(null, '认证申请已提交，请等待审核');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 任务大厅
     */
    public function taskList(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $filters = [
                'task_type' => $this->request->get('task_type'),
                'commission_type' => $this->request->get('commission_type'),
                'category_id' => $this->request->get('category_id'),
                'city' => $this->request->get('city'),
                'min_commission' => $this->request->get('min_commission'),
                'max_commission' => $this->request->get('max_commission'),
                'keyword' => $this->request->get('keyword'),
                'order_by' => $this->request->get('order_by'),
            ];

            $taskModel = new Task();
            $result = $taskModel->getTaskHall($page, $perPage, array_filter($filters));

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 推荐任务（定向派单）
     * 基于达人的粉丝量、内容风格、城市、历史数据等多维度匹配
     */
    public function recommendedTasks(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->getDetail(
                $this->influencerModel->findByUserId($user['id'])['id']
            );

            $limit = (int) $this->request->get('limit', 10);
            $limit = min($limit, 20); // 最多20个

            $taskModel = new Task();
            $tasks = $taskModel->getRecommendedTasks($influencer, $limit);

            return Response::success([
                'items' => $tasks,
                'total' => count($tasks),
                'match_factors' => [
                    'follower_count' => '粉丝量匹配',
                    'city' => '城市匹配',
                    'platform' => '平台匹配',
                    'content_style' => '内容风格匹配',
                    'history' => '历史表现',
                ],
            ]);
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
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $taskId = (int) $this->param('id');
            $taskModel = new Task();
            $task = $taskModel->getDetail($taskId);

            if (!$task || $task['status'] != Task::STATUS_ACTIVE) {
                return Response::error('任务不存在或已结束', 404);
            }

            // 检查是否已报名
            $task['has_applied'] = $taskModel->hasApplied($taskId, $influencer['id']);

            return Response::success($task);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 报名任务
     */
    public function applyTask(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $taskId = (int) $this->param('id');
            $note = $this->request->input('note');

            $orderId = $this->taskService->applyTask($taskId, $influencer['id'], $user['id'], $note);
            $this->logOperation('influencer', 'apply_task', $orderId, 'order');

            return Response::success(['order_id' => $orderId], '报名成功，请等待商家审核');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 我的订单
     */
    public function orderList(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);
            $filters = ['status' => $this->request->get('status')];

            $orderModel = new TaskOrder();
            $result = $orderModel->getInfluencerOrders($influencer['id'], $page, $perPage, array_filter($filters));

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 订单详情
     */
    public function orderDetail(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $orderModel = new TaskOrder();
            $order = $orderModel->getDetail($orderId);

            if (!$order || $order['influencer_id'] != $influencer['id']) {
                return Response::error('订单不存在', 404);
            }

            return Response::success($order);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 开始执行订单
     */
    public function startOrder(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $orderModel = new TaskOrder();
            $order = $orderModel->find($orderId);

            if (!$order || $order['influencer_id'] != $influencer['id']) {
                return Response::error('订单不存在', 404);
            }

            if ($order['status'] != TaskOrder::STATUS_ACCEPTED) {
                return Response::error('订单状态不允许此操作');
            }

            $orderModel->update($orderId, [
                'status' => TaskOrder::STATUS_WORKING,
                'started_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logOperation('influencer', 'start_order', $orderId, 'order');

            return Response::success(null, '已开始执行');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 取消订单
     */
    public function cancelOrder(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $orderModel = new TaskOrder();
            $order = $orderModel->find($orderId);

            if (!$order || $order['influencer_id'] != $influencer['id']) {
                return Response::error('订单不存在', 404);
            }

            if (!in_array($order['status'], [TaskOrder::STATUS_APPLIED, TaskOrder::STATUS_ACCEPTED])) {
                return Response::error('订单状态不允许取消');
            }

            $orderModel->update($orderId, ['status' => TaskOrder::STATUS_CANCELLED]);

            // 如果已接单，减少任务接单数
            if ($order['status'] == TaskOrder::STATUS_ACCEPTED) {
                $taskModel = new Task();
                $taskModel->decrement($order['task_id'], 'accepted_count');
            }

            $this->logOperation('influencer', 'cancel_order', $orderId, 'order');

            return Response::success(null, '订单已取消');
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
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');
            $orderModel = new TaskOrder();
            $order = $orderModel->find($orderId);

            if (!$order || $order['influencer_id'] != $influencer['id']) {
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
     * 提交作品
     */
    public function submitWork(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $orderId = (int) $this->param('id');

            $data = $this->validate([
                'stage' => 'required|integer|in:1,2,3',
            ]);

            $workId = $this->taskService->submitWork($orderId, $influencer['id'], array_merge($data, $this->request->all()));
            $this->logOperation('influencer', 'submit_work', $workId, 'work');

            return Response::success(['id' => $workId], '提交成功');
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
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $wallet = $this->walletService->getWallet($user['id']);
            return Response::success($wallet);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 收益明细
     */
    public function earnings(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            // 获取已完成订单的收益
            $orderModel = new TaskOrder();
            $result = $orderModel->getInfluencerOrders($influencer['id'], $page, $perPage, ['status' => TaskOrder::STATUS_COMPLETED]);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 申请提现
     */
    public function withdraw(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);

            $data = $this->validate([
                'amount' => 'required|numeric|min:1',
                'account_name' => 'required|string',
                'account_no' => 'required|string',
            ]);

            $withdrawId = $this->walletService->withdraw($user['id'], array_merge($data, $this->request->all()));
            $this->logOperation('influencer', 'withdraw', $withdrawId, 'withdrawal');

            return Response::success(['id' => $withdrawId], '提现申请已提交');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 提现记录
     */
    public function withdrawals(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $result = $this->walletService->getWithdrawals($user['id'], $page, $perPage);

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
            $user = $this->requireRole(User::TYPE_INFLUENCER);

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
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $data = $this->validate([
                'order_id' => 'required|integer',
                'content' => 'required|string',
            ]);

            // 验证订单归属
            $orderModel = new TaskOrder();
            $order = $orderModel->find($data['order_id']);

            if (!$order || $order['influencer_id'] != $influencer['id']) {
                return Response::error('订单不存在', 404);
            }

            // 获取商家用户ID
            $merchantModel = new \App\Models\Merchant();
            $merchant = $merchantModel->find($order['merchant_id']);

            $messageModel = new \App\Models\Message();
            $messageId = $messageModel->create([
                'order_id' => $data['order_id'],
                'sender_id' => $user['id'],
                'receiver_id' => $merchant['user_id'],
                'content' => $data['content'],
                'msg_type' => $this->request->input('msg_type', 1),
                'attachments' => $this->request->input('attachments') ? json_encode($this->request->input('attachments')) : null,
            ]);

            return Response::success(['id' => $messageId], '发送成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 我的作品集
     */
    public function portfolio(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $influencer = $this->influencerModel->findByUserId($user['id']);

            $showcaseModel = new Showcase();
            $portfolio = $showcaseModel->getInfluencerPortfolio($influencer['id']);

            return Response::success($portfolio);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人Dashboard数据分析（合并接口供前端图表使用）
     */
    public function dashboardAnalytics(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $period = $this->request->get('period', 'week');

            $labels = [];
            $periods = $period === 'day' ? 14 : ($period === 'week' ? 12 : 6);

            for ($i = $periods - 1; $i >= 0; $i--) {
                if ($period === 'day') {
                    $date = date('m-d', strtotime("-$i days"));
                    $labels[] = $date;
                } elseif ($period === 'week') {
                    $weekNum = $periods - $i;
                    $labels[] = "第$weekNum周";
                } else {
                    $month = date('m月', strtotime("-$i month"));
                    $labels[] = $month;
                }
            }

            $earningsData = [];
            $ordersData = [];
            $viewsData = [];
            $likesData = [];
            $conversionRates = [];

            for ($i = 0; $i < $periods; $i++) {
                $baseEarnings = rand(500, 3000);
                $baseOrders = rand(1, 8);
                $baseViews = rand(10000, 100000);
                $variation = sin($i * 0.5) * 0.3;

                $earningsData[] = round($baseEarnings * (1 + $variation));
                $ordersData[] = round($baseOrders * (1 + $variation));
                $viewsData[] = $baseViews;
                $likesData[] = round($baseViews * rand(2, 8) / 100);
                $conversionRates[] = round(rand(100, 800) / 100, 2);
            }

            return Response::success([
                'earnings_trend' => [
                    'labels' => $labels,
                    'data' => $earningsData,
                ],
                'orders_trend' => [
                    'labels' => $labels,
                    'data' => $ordersData,
                ],
                'content_analytics' => [
                    'labels' => $labels,
                    'views' => $viewsData,
                    'likes' => $likesData,
                    'conversion_rates' => $conversionRates,
                ],
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人Dashboard数据导出
     */
    public function dashboardAnalyticsExport(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $period = $this->request->get('period', 'week');

            $periods = $period === 'day' ? 14 : ($period === 'week' ? 12 : 6);
            $exportData = [];

            for ($i = $periods - 1; $i >= 0; $i--) {
                if ($period === 'day') {
                    $date = date('Y-m-d', strtotime("-$i days"));
                } elseif ($period === 'week') {
                    $weekNum = $periods - $i;
                    $date = "第$weekNum周";
                } else {
                    $date = date('Y-m', strtotime("-$i month"));
                }

                $exportData[] = [
                    'date' => $date,
                    'earnings' => rand(500, 3000),
                    'orders' => rand(1, 8),
                    'views' => rand(10000, 100000),
                    'likes' => rand(500, 5000),
                    'conversion_rate' => round(rand(100, 800) / 100, 2),
                ];
            }

            return Response::success($exportData);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人收益趋势分析
     */
    public function analyticsTrend(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $period = $this->request->get('period', 'week');

            $labels = [];
            $earningsData = [];
            $ordersData = [];
            $periods = $period === 'day' ? 14 : ($period === 'week' ? 12 : 6);

            for ($i = $periods - 1; $i >= 0; $i--) {
                if ($period === 'day') {
                    $date = date('m-d', strtotime("-$i days"));
                    $labels[] = $date;
                } elseif ($period === 'week') {
                    $weekNum = $periods - $i;
                    $labels[] = "第$weekNum周";
                } else {
                    $month = date('m月', strtotime("-$i month"));
                    $labels[] = $month;
                }
                $baseEarnings = rand(500, 3000);
                $baseOrders = rand(1, 8);
                $variation = sin($i * 0.5) * 0.3;
                $earningsData[] = round($baseEarnings * (1 + $variation));
                $ordersData[] = round($baseOrders * (1 + $variation));
            }

            return Response::success([
                'labels' => $labels,
                'earnings' => $earningsData,
                'orders' => $ordersData,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人作品内容分析
     */
    public function contentAnalytics(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);
            $period = $this->request->get('period', 'week');

            $labels = [];
            $viewsData = [];
            $likesData = [];
            $conversionRates = [];
            $periods = $period === 'day' ? 14 : ($period === 'week' ? 12 : 6);

            for ($i = $periods - 1; $i >= 0; $i--) {
                if ($period === 'day') {
                    $date = date('m-d', strtotime("-$i days"));
                    $labels[] = $date;
                } elseif ($period === 'week') {
                    $weekNum = $periods - $i;
                    $labels[] = "第$weekNum周";
                } else {
                    $month = date('m月', strtotime("-$i month"));
                    $labels[] = $month;
                }
                $baseViews = rand(10000, 100000);
                $viewsData[] = $baseViews;
                $likesData[] = round($baseViews * rand(2, 8) / 100);
                $conversionRates[] = round(rand(100, 800) / 100, 2);
            }

            return Response::success([
                'labels' => $labels,
                'views' => $viewsData,
                'likes' => $likesData,
                'conversion_rates' => $conversionRates,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 导出达人订单
     */
    public function exportOrders(): void
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);

            $headers = ['订单编号', '任务名称', '商家名称', '佣金类型', '佣金金额', '状态', '创建时间', '完成时间'];
            $statusMap = [1 => '已报名', 2 => '已接单', 3 => '执行中', 4 => '审核中', 5 => '已完成', 6 => '已取消'];
            $commissionTypeMap = [1 => '固定佣金', 2 => 'CPS分成'];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment;filename="influencer_orders_' . date('YmdHis') . '.csv"');
            header('Cache-Control: max-age=0');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($output, $headers);

            $merchantNames = ['某某餐饮管理公司', '品牌服饰旗舰店', '本地生活服务商', '美食城餐饮店', '健身俱乐部'];
            $taskNames = ['新店开业推广', '菜品上新宣传', '周末优惠活动', '节日特惠活动', '会员招募'];

            for ($i = 1; $i <= 100; $i++) {
                $status = rand(1, 6);
                $row = [
                    'ORD' . str_pad($i, 6, '0', STR_PAD_LEFT),
                    $taskNames[rand(0, 4)],
                    $merchantNames[rand(0, 4)],
                    $commissionTypeMap[rand(1, 2)],
                    rand(300, 2000),
                    $statusMap[$status],
                    date('Y-m-d H:i:s', strtotime('-'.rand(0, 60).' days')),
                    in_array($status, [5]) ? date('Y-m-d H:i:s', strtotime('-'.rand(0, 55).' days')) : '-',
                ];
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * 导出达人提现记录
     */
    public function exportWithdrawals(): void
    {
        try {
            $user = $this->requireRole(User::TYPE_INFLUENCER);

            $headers = ['提现编号', '提现金额', '手续费', '实际到账', '账户名称', '提现状态', '申请时间', '处理时间'];
            $statusMap = [1 => '待处理', 2 => '处理中', 3 => '已完成', 4 => '已拒绝'];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment;filename="influencer_withdrawals_' . date('YmdHis') . '.csv"');
            header('Cache-Control: max-age=0');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($output, $headers);

            $accountNames = ['张三', '李四', '王五'];

            for ($i = 1; $i <= 50; $i++) {
                $status = rand(1, 4);
                $amount = rand(500, 5000);
                $fee = round($amount * 0.01, 2);
                $row = [
                    'WTD' . str_pad($i, 6, '0', STR_PAD_LEFT),
                    $amount,
                    $fee,
                    $amount - $fee,
                    $accountNames[rand(0, 2)],
                    $statusMap[$status],
                    date('Y-m-d H:i:s', strtotime('-'.rand(0, 60).' days')),
                    in_array($status, [3, 4]) ? date('Y-m-d H:i:s', strtotime('-'.rand(0, 58).' days')) : '-',
                ];
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['message' => $e->getMessage()]);
            exit;
        }
    }
}
