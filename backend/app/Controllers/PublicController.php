<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use App\Models\Category;
use App\Models\Showcase;
use App\Models\Influencer;

class PublicController extends Controller
{
    /**
     * 获取分类列表
     */
    public function categories(): Response
    {
        $categoryModel = new Category();
        $categories = $categoryModel->getTree();
        return Response::success($categories);
    }

    /**
     * 获取内容广场
     */
    public function showcase(): Response
    {
        $page = (int) $this->request->get('page', 1);
        $perPage = (int) $this->request->get('per_page', 20);

        $filters = [
            'platform' => $this->request->get('platform'),
            'influencer_id' => $this->request->get('influencer_id'),
        ];

        $showcaseModel = new Showcase();
        $result = $showcaseModel->getList($page, $perPage, array_filter($filters));

        return Response::paginate($result['items'], $result['total'], $page, $perPage);
    }

    /**
     * 获取精选案例
     */
    public function featured(): Response
    {
        $limit = (int) $this->request->get('limit', 10);

        $showcaseModel = new Showcase();
        $items = $showcaseModel->getFeatured($limit);

        return Response::success($items);
    }

    /**
     * 获取达人详情（公开主页）
     */
    public function influencerDetail(): Response
    {
        $id = (int) $this->param('id');

        $influencerModel = new Influencer();
        $influencer = $influencerModel->getDetail($id);

        if (!$influencer || $influencer['verify_status'] != Influencer::VERIFY_APPROVED) {
            return Response::error('达人不存在', 404);
        }

        // 获取作品集
        $showcaseModel = new Showcase();
        $portfolio = $showcaseModel->getInfluencerPortfolio($id);

        // 隐藏敏感信息
        unset($influencer['real_name'], $influencer['id_card'], $influencer['user_id']);

        return Response::success([
            'influencer' => $influencer,
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * 获取公共系统配置（提现限额等）
     */
    public function settings(): Response
    {
        $configModel = new \App\Models\SystemConfig();

        // 只返回公开的配置项
        $settings = [
            'min_withdraw_amount' => (float) $configModel->getValue('min_withdraw_amount', 100),
            'max_withdraw_amount' => (float) $configModel->getValue('max_withdraw_amount', 50000),
            'withdraw_fee_rate' => (float) $configModel->getValue('withdraw_fee_rate', 0.5),
            'platform_fee_rate' => (float) $configModel->getValue('platform_fee_rate', 10),
        ];

        return Response::success($settings);
    }
}
