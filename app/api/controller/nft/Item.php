<?php

namespace app\api\controller\nft;

use app\common\service\nft\CollectionService;
use app\common\service\nft\MarketService;

/**
 * 藏品目录（对齐源站 item 分包）
 */
class Item extends Cbase
{
    /** 目录浏览公开；关注/取消关注需登录（不列入豁免） */
    protected array $noNeedLogin = ['index', 'category', 'detail'];

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
}
