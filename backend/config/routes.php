<?php
/**
 * 路由配置文件
 */

use Core\Router;

$router = new Router();

// ==================== 公共接口 ====================
$router->group('/api', function($router) {

    // 认证相关
    $router->post('/auth/register', 'AuthController@register');
    $router->post('/auth/login', 'AuthController@login');
    $router->post('/auth/logout', 'AuthController@logout');
    $router->get('/auth/profile', 'AuthController@profile');

    // 公共数据
    $router->get('/categories', 'PublicController@categories');
    $router->get('/settings', 'PublicController@settings');
    $router->get('/showcase', 'PublicController@showcase');
    $router->get('/showcase/featured', 'PublicController@featured');
    $router->get('/influencers/{id}', 'PublicController@influencerDetail');

    // 文件上传
    $router->post('/upload', 'UploadController@upload');
});

// ==================== 支付接口 ====================
$router->group('/api/payment', function($router) {
    // 创建充值订单
    $router->post('/recharge', 'PaymentController@createRecharge');

    // 查询订单状态
    $router->get('/query', 'PaymentController@queryOrder');

    // 微信支付回调
    $router->post('/wechat/notify', 'PaymentController@wechatNotify');

    // 支付宝回调
    $router->post('/alipay/notify', 'PaymentController@alipayNotify');
    $router->get('/alipay/return', 'PaymentController@alipayReturn');

    // 模拟支付（仅开发环境）
    $router->post('/simulate', 'PaymentController@simulatePay');
});

// ==================== 商家端接口 ====================
$router->group('/api/merchant', function($router) {

    // 店铺管理
    $router->get('/profile', 'MerchantController@profile');
    $router->put('/profile', 'MerchantController@updateProfile');

    // 任务管理
    $router->get('/tasks', 'MerchantController@taskList');
    $router->post('/tasks', 'MerchantController@createTask');
    $router->get('/tasks/{id}', 'MerchantController@taskDetail');
    $router->put('/tasks/{id}', 'MerchantController@updateTask');
    $router->delete('/tasks/{id}', 'MerchantController@cancelTask');
    $router->post('/tasks/{id}/publish', 'MerchantController@publishTask');

    // 订单管理
    $router->get('/orders', 'MerchantController@orderList');
    $router->put('/orders/{id}/cps-sales', 'MerchantController@updateCpsSales');

    // 报名管理
    $router->get('/tasks/{id}/applications', 'MerchantController@applications');
    $router->post('/applications/{id}/accept', 'MerchantController@acceptApplication');
    $router->post('/applications/{id}/reject', 'MerchantController@rejectApplication');

    // 作品审核
    $router->get('/orders/{id}/works', 'MerchantController@orderWorks');
    $router->post('/works/{id}/review', 'MerchantController@reviewWork');
    $router->post('/orders/{id}/approve', 'MerchantController@approveOrder');
    $router->post('/orders/{id}/reject-work', 'MerchantController@rejectOrderWork');
    $router->post('/orders/{id}/complete', 'MerchantController@completeOrder');

    // 数据看板
    $router->get('/dashboard', 'MerchantController@dashboard');
    $router->get('/statistics', 'MerchantController@statistics');

    // 报表分析
    $router->get('/reports/trend', 'ReportController@merchantTrendData');
    $router->get('/reports/roi', 'ReportController@merchantROI');
    $router->get('/reports/influencers', 'ReportController@influencerComparison');
    $router->get('/reports/commission', 'ReportController@commissionDetail');

    // 财务管理
    $router->get('/wallet', 'MerchantController@wallet');
    $router->post('/wallet/recharge', 'MerchantController@recharge');
    $router->get('/transactions', 'MerchantController@transactions');

    // 消息
    $router->get('/messages', 'MerchantController@messages');
    $router->post('/messages', 'MerchantController@sendMessage');
});

// ==================== 达人端接口 ====================
$router->group('/api/influencer', function($router) {

    // 个人信息
    $router->get('/profile', 'InfluencerController@profile');
    $router->put('/profile', 'InfluencerController@updateProfile');
    $router->post('/verify', 'InfluencerController@submitVerify');

    // 任务大厅
    $router->get('/tasks', 'InfluencerController@taskList');
    $router->get('/tasks/recommended', 'InfluencerController@recommendedTasks');
    $router->get('/tasks/{id}', 'InfluencerController@taskDetail');
    $router->post('/tasks/{id}/apply', 'InfluencerController@applyTask');

    // 我的订单
    $router->get('/orders', 'InfluencerController@orderList');
    $router->get('/orders/{id}', 'InfluencerController@orderDetail');
    $router->post('/orders/{id}/start', 'InfluencerController@startOrder');
    $router->post('/orders/{id}/cancel', 'InfluencerController@cancelOrder');

    // 作品提交
    $router->get('/orders/{id}/works', 'InfluencerController@orderWorks');
    $router->post('/orders/{id}/works', 'InfluencerController@submitWork');

    // 收益中心
    $router->get('/wallet', 'InfluencerController@wallet');
    $router->get('/earnings', 'InfluencerController@earnings');
    $router->post('/wallet/withdraw', 'InfluencerController@withdraw');
    $router->get('/withdrawals', 'InfluencerController@withdrawals');

    // 报表分析
    $router->get('/reports/trend', 'ReportController@influencerTrendData');
    $router->get('/reports/earnings', 'ReportController@influencerEarnings');
    $router->get('/reports/works', 'ReportController@influencerWorkStats');

    // 消息
    $router->get('/messages', 'InfluencerController@messages');
    $router->post('/messages', 'InfluencerController@sendMessage');

    // 我的主页/作品集
    $router->get('/portfolio', 'InfluencerController@portfolio');
});

// ==================== 管理后台接口 ====================
$router->group('/api/admin', function($router) {

    // 数据看板
    $router->get('/dashboard', 'AdminController@dashboard');
    $router->get('/statistics', 'AdminController@statistics');

    // 用户管理
    $router->get('/users', 'AdminController@userList');
    $router->get('/users/{id}', 'AdminController@userDetail');
    $router->put('/users/{id}/status', 'AdminController@updateUserStatus');

    // 商家管理
    $router->get('/merchants', 'AdminController@merchantList');
    $router->get('/merchants/{id}', 'AdminController@merchantDetail');
    $router->post('/merchants/{id}/verify', 'AdminController@verifyMerchant');

    // 达人管理
    $router->get('/influencers', 'AdminController@influencerList');
    $router->get('/influencers/{id}', 'AdminController@influencerDetail');
    $router->post('/influencers/{id}/verify', 'AdminController@verifyInfluencer');

    // 任务管理
    $router->get('/tasks', 'AdminController@taskList');
    $router->get('/tasks/{id}', 'AdminController@taskDetail');
    $router->post('/tasks/{id}/review', 'AdminController@reviewTask');

    // 订单管理
    $router->get('/orders', 'AdminController@orderList');
    $router->get('/orders/{id}', 'AdminController@orderDetail');

    // 作品审核
    $router->get('/works', 'AdminController@workList');
    $router->get('/works/{id}', 'AdminController@workDetail');
    $router->post('/works/{id}/review', 'AdminController@reviewWork');

    // 内容广场管理
    $router->get('/showcase', 'AdminController@showcaseList');
    $router->post('/showcase', 'AdminController@addShowcase');
    $router->put('/showcase/{id}', 'AdminController@updateShowcase');
    $router->delete('/showcase/{id}', 'AdminController@deleteShowcase');
    $router->post('/showcase/{id}/feature', 'AdminController@featureShowcase');

    // 财务管理
    $router->get('/transactions', 'AdminController@transactionList');
    $router->get('/withdrawals', 'AdminController@withdrawalList');
    $router->post('/withdrawals/{id}/process', 'AdminController@processWithdrawal');
    $router->post('/withdrawals/{id}/approve', 'AdminController@approveWithdrawal');
    $router->post('/withdrawals/{id}/reject', 'AdminController@rejectWithdrawal');
    $router->post('/withdrawals/{id}/paid', 'AdminController@markWithdrawalPaid');
    $router->get('/finance/report', 'AdminController@financeReport');

    // 分类管理
    $router->get('/categories', 'AdminController@categoryList');
    $router->post('/categories', 'AdminController@createCategory');
    $router->put('/categories/{id}', 'AdminController@updateCategory');
    $router->delete('/categories/{id}', 'AdminController@deleteCategory');

    // 系统配置
    $router->get('/configs', 'AdminController@configList');
    $router->put('/configs', 'AdminController@updateConfigs');

    // 操作日志
    $router->get('/logs', 'AdminController@logList');

    // 报表分析
    $router->get('/reports/trend', 'ReportController@trendData');
    $router->get('/reports/gmv', 'ReportController@platformGMV');
    $router->get('/reports/funnel', 'ReportController@userGrowthFunnel');

    // 数据导出
    $router->get('/export/transactions', 'ReportController@exportTransactions');
    $router->get('/export/orders', 'ReportController@exportOrders');
    $router->get('/export/withdrawals', 'ReportController@exportWithdrawals');
});

return $router;
