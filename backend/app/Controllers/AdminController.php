<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Influencer;
use App\Models\Task;
use App\Models\TaskOrder;
use App\Models\Work;
use App\Models\Withdrawal;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Showcase;
use App\Models\SystemConfig;
use App\Services\TaskService;
use App\Services\WalletService;

class AdminController extends Controller
{
    /**
     * 数据看板
     */
    public function dashboard(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $userModel = new User();
            $taskModel = new Task();
            $orderModel = new TaskOrder();
            $transModel = new Transaction();

            // 用户统计
            $userStats = $userModel->getStatistics();

            // 任务统计
            $taskStats = $taskModel->getStatistics();

            // 订单统计
            $orderStats = $orderModel->getStatistics();

            // 财务统计
            $financeStats = $transModel->getPlatformStatistics();

            return Response::success([
                'users' => $userStats,
                'tasks' => $taskStats,
                'orders' => $orderStats,
                'finance' => $financeStats,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 用户列表
     */
    public function userList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $conditions = [];
            if ($this->request->get('user_type')) {
                $conditions['user_type'] = $this->request->get('user_type');
            }
            if ($this->request->get('status') !== null) {
                $conditions['status'] = $this->request->get('status');
            }

            $userModel = new User();
            $result = $userModel->paginate($page, $perPage, $conditions);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 更新用户状态
     */
    public function updateUserStatus(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $userId = (int) $this->param('id');
            $status = (int) $this->request->input('status');

            $userModel = new User();
            $userModel->update($userId, ['status' => $status]);

            $this->logOperation('admin', 'update_user_status', $userId, 'user');

            return Response::success(null, '更新成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 商家列表
     */
    public function merchantList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $filters = [];
            if ($this->request->get('verify_status') !== null && $this->request->get('verify_status') !== '') {
                $filters['verify_status'] = $this->request->get('verify_status');
            }
            if ($this->request->get('keyword')) {
                $filters['keyword'] = $this->request->get('keyword');
            }
            if ($this->request->get('category_id')) {
                $filters['category_id'] = $this->request->get('category_id');
            }

            $merchantModel = new Merchant();
            $result = $merchantModel->getList($page, $perPage, $filters);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 商家详情
     */
    public function merchantDetail(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $merchantModel = new Merchant();
            $merchant = $merchantModel->getDetail($id);

            if (!$merchant) {
                return Response::error('商家不存在', 404);
            }

            return Response::success($merchant);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 审核商家
     */
    public function verifyMerchant(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $status = (int) $this->request->input('status');
            $note = $this->request->input('note');

            $merchantModel = new Merchant();
            $merchantModel->verify($id, $status, $note);

            $this->logOperation('admin', 'verify_merchant', $id, 'merchant');

            return Response::success(null, '审核完成');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人列表
     */
    public function influencerList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $filters = [];
            if ($this->request->get('verify_status') !== null && $this->request->get('verify_status') !== '') {
                $filters['verify_status'] = $this->request->get('verify_status');
            }
            if ($this->request->get('platform_type')) {
                $filters['platform_type'] = $this->request->get('platform_type');
            }
            if ($this->request->get('keyword')) {
                $filters['keyword'] = $this->request->get('keyword');
            }

            $influencerModel = new Influencer();
            $result = $influencerModel->getList($page, $perPage, $filters);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 达人详情
     */
    public function influencerDetail(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $influencerModel = new Influencer();
            $influencer = $influencerModel->getDetail($id);

            if (!$influencer) {
                return Response::error('达人不存在', 404);
            }

            return Response::success($influencer);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 审核达人
     */
    public function verifyInfluencer(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $status = (int) $this->request->input('status');
            $note = $this->request->input('note');

            $influencerModel = new Influencer();
            $influencerModel->verify($id, $status, $note);

            $this->logOperation('admin', 'verify_influencer', $id, 'influencer');

            return Response::success(null, '审核完成');
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
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $filters = [];
            if ($this->request->get('status') !== null && $this->request->get('status') !== '') {
                $filters['status'] = $this->request->get('status');
            }
            if ($this->request->get('merchant_id')) {
                $filters['merchant_id'] = $this->request->get('merchant_id');
            }
            if ($this->request->get('keyword')) {
                $filters['keyword'] = $this->request->get('keyword');
            }

            $taskModel = new Task();
            $result = $taskModel->getAdminList($page, $perPage, $filters);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
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
            $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $taskModel = new Task();
            $task = $taskModel->getDetail($id);

            if (!$task) {
                return Response::error('任务不存在', 404);
            }

            return Response::success($task);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 审核任务
     */
    public function reviewTask(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $approved = (bool) $this->request->input('approved');
            $note = $this->request->input('note');

            $taskModel = new Task();
            $task = $taskModel->find($id);

            if (!$task || $task['status'] != Task::STATUS_PENDING) {
                return Response::error('任务不存在或状态不正确');
            }

            $status = $approved ? Task::STATUS_ACTIVE : Task::STATUS_REJECTED;
            $taskModel->update($id, [
                'status' => $status,
                'review_note' => $note,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);

            // 如果拒绝，解冻预算
            if (!$approved && $task['frozen_amount'] > 0) {
                $merchantModel = new Merchant();
                $merchant = $merchantModel->find($task['merchant_id']);

                $walletModel = new \App\Models\Wallet();
                $walletModel->unfreeze(
                    $merchant['user_id'],
                    $task['frozen_amount'],
                    $id,
                    'task',
                    "任务审核拒绝，退回预算：{$task['title']}"
                );
            }

            $this->logOperation('admin', 'review_task', $id, 'task');

            return Response::success(null, $approved ? '审核通过' : '审核拒绝');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 作品列表
     */
    public function workList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);
            $status = $this->request->get('status');

            $workModel = new Work();
            $result = $workModel->getAdminWorkList($page, $perPage, $status);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 作品详情
     */
    public function workDetail(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $workModel = new Work();
            $work = $workModel->getWorkDetail($id);

            if (!$work) {
                return Response::error('作品不存在', 404);
            }

            return Response::success($work);
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
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $approved = (bool) $this->request->input('approved');
            $note = $this->request->input('note');

            $taskService = new TaskService();
            $taskService->reviewWork($id, $admin['id'], 2, $approved, $note);

            $this->logOperation('admin', 'review_work', $id, 'work');

            return Response::success(null, $approved ? '审核通过' : '审核拒绝');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 内容广场列表
     */
    public function showcaseList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $showcaseModel = new Showcase();
            $result = $showcaseModel->getList($page, $perPage, []);

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 添加展示作品
     */
    public function addShowcase(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $data = $this->validate([
                'work_id' => 'required|integer',
                'title' => 'required|string',
            ]);

            $workModel = new Work();
            $work = $workModel->find($data['work_id']);

            if (!$work) {
                return Response::error('作品不存在');
            }

            $orderModel = new TaskOrder();
            $order = $orderModel->find($work['order_id']);

            $showcaseModel = new Showcase();
            $id = $showcaseModel->create([
                'work_id' => $data['work_id'],
                'influencer_id' => $work['influencer_id'],
                'merchant_id' => $order['merchant_id'],
                'title' => $data['title'],
                'description' => $this->request->input('description'),
                'cover_image' => $this->request->input('cover_image'),
                'platform' => $this->request->input('platform'),
                'publish_url' => $work['publish_url'],
                'view_count' => $work['view_count'],
                'like_count' => $work['like_count'],
                'comment_count' => $work['comment_count'],
            ]);

            $this->logOperation('admin', 'add_showcase', $id, 'showcase');

            return Response::success(['id' => $id], '添加成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 设为精选
     */
    public function featureShowcase(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $featured = (bool) $this->request->input('featured', true);

            $showcaseModel = new Showcase();
            $showcaseModel->update($id, ['is_featured' => $featured ? 1 : 0]);

            $this->logOperation('admin', 'feature_showcase', $id, 'showcase');

            return Response::success(null, $featured ? '已设为精选' : '已取消精选');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 提现列表
     */
    public function withdrawalList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $filters = [
                'status' => $this->request->get('status'),
            ];

            $withdrawModel = new Withdrawal();
            $result = $withdrawModel->getPendingList($page, $perPage, array_filter($filters));

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 处理提现
     */
    /**
     * 处理提现（旧接口，保留兼容）
     */
    public function processWithdrawal(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $approved = (bool) $this->request->input('approved');
            $note = $this->request->input('note');

            $walletService = new WalletService();
            $walletService->processWithdrawal($id, $admin['id'], $approved, $note);

            $this->logOperation('admin', 'process_withdrawal', $id, 'withdrawal');

            return Response::success(null, $approved ? '已通过并打款' : '已拒绝');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 审核通过提现
     */
    public function approveWithdrawal(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $note = $this->request->input('note');

            $withdrawModel = new Withdrawal();
            $withdrawal = $withdrawModel->find($id);

            if (!$withdrawal) {
                return Response::error('提现申请不存在', 404);
            }

            if ($withdrawal['status'] != Withdrawal::STATUS_PENDING) {
                return Response::error('该申请已处理');
            }

            // 更新状态为已通过
            $withdrawModel->process($id, Withdrawal::STATUS_APPROVED, $admin['id'], $note);

            $this->logOperation('admin', 'approve_withdrawal', $id, 'withdrawal');

            return Response::success(null, '审核通过');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 拒绝提现
     */
    public function rejectWithdrawal(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $note = $this->request->input('note');

            $withdrawModel = new Withdrawal();
            $withdrawal = $withdrawModel->find($id);

            if (!$withdrawal) {
                return Response::error('提现申请不存在', 404);
            }

            if ($withdrawal['status'] != Withdrawal::STATUS_PENDING) {
                return Response::error('该申请已处理');
            }

            // 解冻金额并更新状态
            $walletModel = new \App\Models\Wallet();
            $wallet = $walletModel->findByUserId($withdrawal['user_id']);

            $db = \Core\Database::getInstance();
            $db->transaction(function($db) use ($withdrawal, $wallet, $withdrawModel, $admin, $id, $note) {
                // 解冻金额
                $sql = "UPDATE wallets SET
                        balance = balance + ?,
                        frozen_amount = frozen_amount - ?,
                        version = version + 1
                        WHERE id = ? AND version = ?";
                $db->query($sql, [$withdrawal['amount'], $withdrawal['amount'], $wallet['id'], $wallet['version']]);

                // 记录流水
                $transModel = new Transaction();
                $transModel->record(
                    $withdrawal['user_id'],
                    $wallet['id'],
                    Transaction::TYPE_UNFREEZE,
                    $withdrawal['amount'],
                    $wallet['balance'],
                    $wallet['balance'] + $withdrawal['amount'],
        },

    /**
     * GMV趋势
     */
    public function gmvTrend(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $period = $this->request->get('period', 'day');
            $days = $period === 'day' ? 14 : ($period === 'week' ? 12 : 6);
            $format = $period === 'day' ? 'm-d' : ($period === 'week' ? '\第W周' : 'Y-m');

            $labels = [];
            $gmvData = [];
            $platformIncome = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                if ($period === 'day') {
                    $date = date($format, strtotime("-$i days"));
                } elseif ($period === 'week') {
                    $date = '第' . date('W', strtotime("-$i weeks")) . '周';
                } else {
                    $date = date($format, strtotime("-$i months"));
                }
                $labels[] = $date;
                $gmvData[] = rand(50000, 200000);
                $platformIncome[] = rand(5000, 20000);
            }

            return Response::success([
                'labels' => $labels,
                'gmv' => $gmvData,
                'service_fee' => $platformIncome,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 用户注册趋势
     */
    public function userRegistrationsTrend(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $period = $this->request->get('period', 'day');
            $days = $period === 'day' ? 14 : ($period === 'week' ? 12 : 6);
            $format = $period === 'day' ? 'm-d' : ($period === 'week' ? '\第W周' : 'Y-m');

            $labels = [];
            $merchantData = [];
            $influencerData = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                if ($period === 'day') {
                    $date = date($format, strtotime("-$i days"));
                } elseif ($period === 'week') {
                    $date = '第' . date('W', strtotime("-$i weeks")) . '周';
                } else {
                    $date = date($format, strtotime("-$i months"));
                }
                $labels[] = $date;
                $merchantData[] = rand(5, 30);
                $influencerData[] = rand(20, 100);
            }

            return Response::success([
                'labels' => $labels,
                'merchants' => $merchantData,
                'influencers' => $influencerData,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 任务发布趋势
     */
    public function taskPublicationsTrend(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $period = $this->request->get('period', 'day');
            $days = $period === 'day' ? 14 : ($period === 'week' ? 12 : 6);
            $format = $period === 'day' ? 'm-d' : ($period === 'week' ? '\第W周' : 'Y-m');

            $labels = [];
            $taskData = [];
            $orderData = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                if ($period === 'day') {
                    $date = date($format, strtotime("-$i days"));
                } elseif ($period === 'week') {
                    $date = '第' . date('W', strtotime("-$i weeks")) . '周';
                } else {
                    $date = date($format, strtotime("-$i months"));
                }
                $labels[] = $date;
                $taskData[] = rand(10, 50);
                $orderData[] = rand(30, 150);
            }

            return Response::success([
                'labels' => $labels,
                'tasks' => $taskData,
                'orders' => $orderData,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 服务费收入趋势
     */
    public function serviceFeeTrend(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $period = $this->request->get('period', 'day');
            $days = $period === 'day' ? 14 : ($period === 'week' ? 12 : 6);
            $format = $period === 'day' ? 'm-d' : ($period === 'week' ? '\第W周' : 'Y-m');

            $labels = [];
            $incomeData = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                if ($period === 'day') {
                    $date = date($format, strtotime("-$i days"));
                } elseif ($period === 'week') {
                    $date = '第' . date('W', strtotime("-$i weeks")) . '周';
                } else {
                    $date = date($format, strtotime("-$i months"));
                }
                $labels[] = $date;
                $incomeData[] = rand(5000, 25000);
            }

            return Response::success([
                'labels' => $labels,
                'service_fee' => $incomeData,
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 转化漏斗
     */
    public function conversionFunnel(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            return Response::success([
                'items' => [
                    ['name' => '平台访问', 'value' => 10000],
                    ['name' => '账号注册', 'value' => 6500],
                    ['name' => '完成认证', 'value' => 4200],
                    ['name' => '报名/发布任务', 'value' => 2800],
                    ['name' => '任务成交', 'value' => 1800],
                ],
            ]);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 导出交易流水
     */
    public function exportTransactions(): void
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $transModel = new Transaction();
            $transactions = $transModel->getDb()->fetchAll("SELECT
                t.id, t.user_id, u.nickname, t.type, t.amount, t.balance_before, t.balance_after,
                t.related_type, t.related_id, t.description, t.created_at
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                ORDER BY t.id DESC LIMIT 1000");

            $headers = ['ID', '用户ID', '用户昵称', '交易类型', '金额', '变更前余额', '变更后余额', '关联类型', '关联ID', '描述', '创建时间'];

            $BOM = "\xEF\xBB\xBF";
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment;filename="transactions_' . date('YmdHis') . '.csv"');
            header('Cache-Control: max-age=0');

            fputcsv($output, $headers);

            $typeMap = [1 => '充值', 2 => '提现', 3 => '冻结', 4 => '解冻', 5 => '佣金', 6 => '服务费'];

            foreach ($transactions as &$row) {
                $row['type'] = $typeMap[$row['type']] ?? $row['type'];
            }

            foreach ($transactions as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        } catch (\Exception $e) {
            echo json_encode(['code' => 400, 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * 导出订单列表
     */
    public function exportOrders(): void
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $orderModel = new TaskOrder();
            $orders = $orderModel->getDb()->fetchAll("SELECT
                o.id, o.task_id, t.title task_title,
                m.shop_name merchant_name, i.nickname influencer_name,
                o.commission_type, o.commission_amount, o.cps_rate, o.cps_sales,
                o.status, o.created_at, o.completed_at
                FROM task_orders o
                LEFT JOIN tasks t ON o.task_id = t.id
                LEFT JOIN merchants m ON o.merchant_id = m.id
                LEFT JOIN influencers i ON o.influencer_id = i.id
                ORDER BY o.id DESC LIMIT 1000");

            $headers = ['订单ID', '任务ID', '任务标题', '商家名称', '达人昵称', '佣金类型', '佣金金额', 'CPS比例(%)', 'CPS销售额', '状态', '创建时间', '完成时间'];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment;filename="orders_' . date('YmdHis') . '.csv"');
            header('Cache-Control: max-age=0');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, $headers);

            $statusMap = [1 => '已报名', 2 => '已接单', 3 => '执行中', 4 => '审核中', 5 => '已完成', 6 => '已取消'];
            $typeMap = [1 => '固定佣金', 2 => 'CPS分成'];

            foreach ($orders as $row) {
                $row['status'] = $statusMap[$row['status']] ?? $row['status'];
                $row['commission_type'] = $typeMap[$row['commission_type']] ?? $row['commission_type'];
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        } catch (\Exception $e) {
            echo json_encode(['code' => 400, 'msg' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * 导出现金列表
     */
    public function exportWithdrawals(): void
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $withdrawModel = new Withdrawal();
            $withdrawals = $withdrawModel->getDb()->fetchAll("SELECT
                w.id, w.user_id, u.nickname, w.amount, w.fee, w.real_amount,
                w.account_type, w.account_name, w.account_no, w.status,
                w.created_at, w.processed_at
                FROM withdrawals w
                LEFT JOIN users u ON w.user_id = u.id
                ORDER BY w.id DESC LIMIT 1000");

            $headers = ['ID', '用户ID', '用户昵称', '申请金额', '手续费', '到账金额', '账户类型', '开户名', '账号/支付宝', '状态', '申请时间', '处理时间'];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment;filename="withdrawals_' . date('YmdHis') . '.csv"');
            header('Cache-Control: max-age=0');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, $headers);

            $statusMap = [0 => '待处理', 1 => '处理中', 2 => '已通过', 3 => '已拒绝'];

            foreach ($withdrawals as $row) {
                $row['status'] = $statusMap[$row['status']] ?? $row['status'];
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        } catch (\Exception $e) {
            echo json_encode(['code' => 400, 'msg' => $e->getMessage()]);
            exit;
        }
    }
                    $withdrawal['id'],
                    'withdrawal',
                    '提现申请被拒绝，金额已退回',
                    $wallet['frozen_amount'],
                    $wallet['frozen_amount'] - $withdrawal['amount']
                );

                // 更新状态
                $withdrawModel->process($id, Withdrawal::STATUS_REJECTED, $admin['id'], $note);
            });

            $this->logOperation('admin', 'reject_withdrawal', $id, 'withdrawal');

            return Response::success(null, '已拒绝，金额已退回用户余额');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 确认打款
     */
    public function markWithdrawalPaid(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $note = $this->request->input('note');

            $withdrawModel = new Withdrawal();
            $withdrawal = $withdrawModel->find($id);

            if (!$withdrawal) {
                return Response::error('提现申请不存在', 404);
            }

            if ($withdrawal['status'] != Withdrawal::STATUS_APPROVED) {
                return Response::error('该申请状态不正确，需先审核通过');
            }

            // 从冻结金额扣除并更新状态
            $walletModel = new \App\Models\Wallet();
            $wallet = $walletModel->findByUserId($withdrawal['user_id']);

            $db = \Core\Database::getInstance();
            $db->transaction(function($db) use ($withdrawal, $wallet, $withdrawModel, $admin, $id, $note) {
                // 从冻结金额扣除
                $sql = "UPDATE wallets SET
                        frozen_amount = frozen_amount - ?,
                        total_withdraw = total_withdraw + ?,
                        version = version + 1
                        WHERE id = ? AND version = ?";
                $db->query($sql, [$withdrawal['amount'], $withdrawal['actual_amount'], $wallet['id'], $wallet['version']]);

                // 记录流水
                $transModel = new Transaction();
                $transModel->record(
                    $withdrawal['user_id'],
                    $wallet['id'],
                    Transaction::TYPE_WITHDRAW,
                    $withdrawal['actual_amount'],
                    $wallet['balance'],
                    $wallet['balance'],
                    $withdrawal['id'],
                    'withdrawal',
                    '提现成功',
                    $wallet['frozen_amount'],
                    $wallet['frozen_amount'] - $withdrawal['amount']
                );

                // 记录手续费
                if ($withdrawal['fee'] > 0) {
                    $transModel->record(
                        $withdrawal['user_id'],
                        $wallet['id'],
                        Transaction::TYPE_PLATFORM_FEE,
                        $withdrawal['fee'],
                        $wallet['balance'],
                        $wallet['balance'],
                        $withdrawal['id'],
                        'withdrawal',
                        '提现手续费'
                    );
                }

                // 更新状态
                $withdrawModel->process($id, Withdrawal::STATUS_PAID, $admin['id'], $note);
            });

            $this->logOperation('admin', 'paid_withdrawal', $id, 'withdrawal');

            return Response::success(null, '已确认打款');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 交易流水
     */
    public function transactionList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $transModel = new Transaction();
            $result = $transModel->paginate($page, $perPage, [], 'id DESC');

            return Response::paginate($result['items'], $result['total'], $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 分类列表
     */
    public function categoryList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $categoryModel = new Category();
            $categories = $categoryModel->getTree();

            return Response::success($categories);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 创建分类
     */
    public function createCategory(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $data = $this->validate([
                'name' => 'required|string|max_length:50',
            ]);

            $categoryModel = new Category();
            $id = $categoryModel->create([
                'name' => $data['name'],
                'icon' => $this->request->input('icon'),
                'parent_id' => $this->request->input('parent_id', 0),
                'sort_order' => $this->request->input('sort_order', 0),
            ]);

            $this->logOperation('admin', 'create_category', $id, 'category');

            return Response::success(['id' => $id], '创建成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 更新分类
     */
    public function updateCategory(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');
            $data = $this->request->all();

            $allowedFields = ['name', 'icon', 'parent_id', 'sort_order', 'status'];
            $updateData = array_intersect_key($data, array_flip($allowedFields));

            $categoryModel = new Category();
            $categoryModel->update($id, $updateData);

            $this->logOperation('admin', 'update_category', $id, 'category');

            return Response::success(null, '更新成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 删除分类
     */
    public function deleteCategory(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $id = (int) $this->param('id');

            $categoryModel = new Category();

            if ($categoryModel->hasChildren($id)) {
                return Response::error('请先删除子分类');
            }

            $categoryModel->delete($id);

            $this->logOperation('admin', 'delete_category', $id, 'category');

            return Response::success(null, '删除成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 系统配置列表
     */
    public function configList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $configModel = new SystemConfig();
            $configs = $configModel->getAllConfigs();

            return Response::success($configs);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 更新系统配置
     */
    public function updateConfigs(): Response
    {
        try {
            $admin = $this->requireRole(User::TYPE_ADMIN);

            $configs = $this->request->all();

            $configModel = new SystemConfig();
            $configModel->batchUpdate($configs);

            $this->logOperation('admin', 'update_configs', null, 'config');

            return Response::success(null, '配置已更新');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 操作日志
     */
    public function logList(): Response
    {
        try {
            $this->requireRole(User::TYPE_ADMIN);

            $page = (int) $this->request->get('page', 1);
            $perPage = (int) $this->request->get('per_page', 20);

            $db = \Core\Database::getInstance();

            $total = (int) $db->fetch("SELECT COUNT(*) as total FROM operation_logs")['total'];

            $offset = ($page - 1) * $perPage;
            $items = $db->fetchAll(
                "SELECT * FROM operation_logs ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"
            );

            return Response::paginate($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
