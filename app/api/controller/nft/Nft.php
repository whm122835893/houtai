<?php

namespace app\api\controller\nft;

use app\common\service\nft\HoldService;
use app\common\service\nft\MarketService;

/**
 * 我的持仓 + 二级市场（对齐源站 nft 分包契约）
 *
 * 持仓：GET /nft  GET /nft/{nftId}
 * 寄售：POST /nft/sell/up  POST /nft/sell/up/batch  PUT /nft/sell/down
 *       GET /nft/sell/sold  POST /nft/sell/apply
 * 市场：GET /nft/secondary  GET /nft/secondary/{iid}
 *       GET /nft/secondary/sell|sold|apply|trust|follow
 */
class Nft extends Cbase
{
    /** 市场浏览公开（未登录可看挂单/成交/求购/委托），关注列表与持仓寄售需登录 */
    protected array $noNeedLogin = ['secondary', 'secondarydetail', 'secondarysell', 'secondarysold', 'secondaryapply', 'secondarytrust'];
    /**
     * GET nft —— 我的持仓列表（裸数组）
     */
    public function index(): void
    {
        $this->success('', (new HoldService())->myList(
            (int) $this->auth->id,
            (string) $this->request->param('iid/s', ''),
            (string) $this->request->param('status/s', '')
        ));
    }

    /**
     * GET nft/{nftId} —— 持仓详情（NftInfo / NftSale 页面消费）
     */
    public function detail(int $nftId): void
    {
        $hold = (new HoldService())->myOne((int) $this->auth->id, $nftId);
        $this->success('', $hold);
    }

    /* ==================== 寄售 ==================== */

    /**
     * POST nft/sell/up —— 单条寄售 {payWay, nftId, price, secondPassword}
     */
    public function sellUp(): void
    {
        $nftId = (int) $this->request->param('nftId/d', 0);
        $price = (int) $this->request->param('price/d', 0);
        $plain = $this->decryptPassword((string) $this->request->param('secondPassword/s', ''));

        (new MarketService())->sellUp((int) $this->auth->id, $nftId, $price, $plain);
        $this->success();
    }

    /**
     * POST nft/sell/up/batch —— 批量寄售 {payWay, iid, minPrice, maxPrice, quantity, secondPassword}
     */
    public function sellUpBatch(): void
    {
        $iid       = (int) $this->request->param('iid/d', 0);
        $minPrice  = (int) $this->request->param('minPrice/d', 0);
        $maxPrice  = (int) $this->request->param('maxPrice/d', 0);
        $quantity  = (int) $this->request->param('quantity/d', 0);
        $plain     = $this->decryptPassword((string) $this->request->param('secondPassword/s', ''));

        (new MarketService())->sellUpBatch((int) $this->auth->id, $iid, $minPrice, $maxPrice, $quantity, $plain);
        $this->success();
    }

    /**
     * PUT nft/sell/down —— 下架 {nftId}
     */
    public function sellDown(): void
    {
        $nftId = (int) $this->request->param('nftId/d', 0);
        (new MarketService())->sellDown((int) $this->auth->id, $nftId);
        $this->success();
    }

    /**
     * POST nft/sell/apply —— 出售给求购单 {orderId, nftId, secondPassword}
     */
    public function sellApply(): void
    {
        $orderId = (int) $this->request->param('orderId/d', 0);
        $nftId   = (int) $this->request->param('nftId/d', 0);
        $plain   = $this->decryptPassword((string) $this->request->param('secondPassword/s', ''));

        (new MarketService())->sellNeed((int) $this->auth->id, $orderId, $nftId, $plain);
        $this->success();
    }

    /**
     * GET nft/sell/sold —— 我的出售记录（MyPage tab 消费）
     */
    public function sellSold(): void
    {
        $this->success('', (new MarketService())->sellSoldList(
            (int) $this->auth->id,
            (string) $this->request->param('keyword/s', ''),
            $this->pageNo(),
            10
        ));
    }

    /* ==================== 市场数据 ==================== */

    /**
     * GET nft/secondary —— 市场列表 {q, cid, pageNo}（静态段先于 :iid 注册）
     */
    public function secondary(): void
    {
        $this->success('', (new MarketService())->indexList(
            (string) $this->request->param('q/s', ''),
            (int) $this->request->param('cid/d', 0),
            $this->pageNo()
        ));
    }

    /**
     * GET nft/secondary/{iid} —— 藏品市场详情
     */
    public function secondaryDetail(int $iid): void
    {
        $this->success('', (new MarketService())->itemDetail($iid));
    }

    /**
     * GET nft/secondary/sell —— 在售列表 {iid, pageNo}
     */
    public function secondarySell(): void
    {
        $this->success('', (new MarketService())->saleList(
            (int) $this->request->param('iid/d', 0),
            $this->pageNo()
        ));
    }

    /**
     * GET nft/secondary/sold —— 成交记录 {iid, pageNo, pageSize}
     */
    public function secondarySold(): void
    {
        $this->success('', (new MarketService())->soldList(
            (int) $this->request->param('iid/d', 0),
            $this->pageNo(),
            $this->pageSize(10)
        ));
    }

    /**
     * GET nft/secondary/apply —— 求购列表 {iid, pageNo}
     */
    public function secondaryApply(): void
    {
        $this->success('', (new MarketService())->applyList(
            (int) $this->request->param('iid/d', 0),
            $this->pageNo()
        ));
    }

    /**
     * GET nft/secondary/trust —— 委托列表 {iid, pageNo}
     */
    public function secondaryTrust(): void
    {
        $this->success('', (new MarketService())->trustList(
            (int) $this->request->param('iid/d', 0),
            $this->pageNo()
        ));
    }

    /**
     * GET nft/secondary/follow —— 关注列表 {pageNo}
     */
    public function secondaryFollow(): void
    {
        $this->success('', (new MarketService())->followList(
            (int) $this->auth->id,
            $this->pageNo()
        ));
    }
}
