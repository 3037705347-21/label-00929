<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use App\Models\User;
use App\Services\PaymentService;

/**
 * 支付控制器
 */
class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    /**
     * 创建充值订单
     */
    public function createRecharge(): Response
    {
        try {
            $user = $this->requireRole(User::TYPE_MERCHANT);

            $data = $this->validate([
                'amount' => 'required|numeric|min:1',
                'pay_method' => 'required|string|in:wechat,alipay',
            ]);

            $result = $this->paymentService->createRechargeOrder(
                $user['id'],
                (float) $data['amount'],
                $data['pay_method']
            );

            $this->logOperation('merchant', 'create_recharge', $result['order_id'], 'payment');

            return Response::success($result, '订单创建成功');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 查询订单状态
     */
    public function queryOrder(): Response
    {
        try {
            $user = $this->requireAuth();
            $orderNo = $this->request->get('order_no');

            if (empty($orderNo)) {
                return Response::error('订单号不能为空');
            }

            $result = $this->paymentService->queryOrderStatus($orderNo);

            return Response::success($result);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * 微信支付回调
     */
    public function wechatNotify(): Response
    {
        try {
            $data = $this->request->all();

            $success = $this->paymentService->handleWechatNotify($data);

            if ($success) {
                // 微信支付要求返回特定格式
                return Response::json(['code' => 'SUCCESS', 'message' => '成功']);
            } else {
                return Response::json(['code' => 'FAIL', 'message' => '处理失败'], 500);
            }
        } catch (\Exception $e) {
            return Response::json(['code' => 'FAIL', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 支付宝回调
     */
    public function alipayNotify(): Response
    {
        try {
            $data = $this->request->all();

            $success = $this->paymentService->handleAlipayNotify($data);

            // 支付宝要求返回 "success" 字符串
            if ($success) {
                return Response::raw('success');
            } else {
                return Response::raw('fail');
            }
        } catch (\Exception $e) {
            return Response::raw('fail');
        }
    }

    /**
     * 支付宝同步返回
     */
    public function alipayReturn(): Response
    {
        // 支付宝同步返回，重定向到前端页面
        $orderNo = $this->request->get('out_trade_no');
        $redirectUrl = '/merchant/wallet.html?order_no=' . $orderNo;

        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * 模拟支付成功（仅开发环境）
     */
    public function simulatePay(): Response
    {
        try {
            $user = $this->requireAuth();
            $orderNo = $this->request->input('order_no');

            if (empty($orderNo)) {
                return Response::error('订单号不能为空');
            }

            $success = $this->paymentService->simulatePaySuccess($orderNo);

            if ($success) {
                return Response::success(null, '模拟支付成功');
            } else {
                return Response::error('模拟支付失败');
            }
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
