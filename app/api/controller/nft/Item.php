<?php

namespace app\api\controller\nft;

use app\common\service\nft\CollectionService;
use app\common\service\nft\MergeService;
use app\common\service\nft\MarketService;

/**
 * 藏品目录（对齐源站 item 分包）+ 合成活动浏览
 */
class Item extends Cbase
{
    /** 目录浏览公开；关注/取消关注需登录（不列入豁免） */
    protected array $noNeedLogin = ['index', 'category', 'detail', 'mergeindex', 'mergedetail'];

    /**
     * GET item —— 藏品列表（开售即显示：排期/发售中/已售罄均可见）
     */
    public function index(): void
    {
        $svc = new CollectionService();
        $this->success('', $svc->list(
            $this->pageNo(),
            $this->pageSize(),
            (int) $this->request->param('cid/d', 0),
            $this->optionalUid()
        ));
    }

    /**
     * GET item/category —— 分类列表
     */
    public function category(): void
    {
        $this->success('', (new CollectionService())->categories());
    }

    /**
     * GET item/:iid —— 藏品详情（含嵌套 category）
     */
    public function detail(string $iid = ''): void
    {
        $id = (int) $iid;
        if ($id <= 0) {
            $id = (int) $this->request->param('iid/d', 0);
        }
        $this->success('', (new CollectionService())->detail($id, $this->optionalUid()));
    }

    /**
     * POST item/follow/:iid —— 关注藏品
     */
    public function follow(int $iid = 0): void
    {
        if ($iid <= 0) {
            $iid = (int) $this->request->param('iid/d', 0);
        }
        if ($iid <= 0) {
            throw \app\common\service\nft\CException::missing('iid');
        }
        (new MarketService())->follow((int) $this->auth->id, $iid);
        $this->success('', true);
    }

    /**
     * DELETE item/unfollow/:iid —— 取消关注
     */
    public function unfollow(int $iid = 0): void
    {
        if ($iid <= 0) {
            $iid = (int) $this->request->param('iid/d', 0);
        }
        if ($iid <= 0) {
            throw \app\common\service\nft\CException::missing('iid');
        }
        (new MarketService())->unfollow((int) $this->auth->id, $iid);
        $this->success('', true);
    }

    /**
     * GET item/merge —— 合成活动列表（Compose.vue 卡片字段）
     */
    public function mergeIndex(): void
    {
        $this->success('', (new MergeService())->list());
    }

    /**
     * GET item/merge/:id —— 合成详情（needs[].ownQuantity 登录态计算，未登录=0）
     */
    public function mergeDetail(int $id = 0): void
    {
        if ($id <= 0) {
            $id = (int) $this->request->param('id/d', 0);
        }
        if ($id <= 0) {
            throw \app\common\service\nft\CException::missing('id');
        }
        $this->success('', (new MergeService())->detail($id, $this->optionalUid()));
    }
}
