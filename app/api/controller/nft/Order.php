<?php

namespace app\api\controller\nft;

use app\common\service\nft\CException;
use app\common\service\nft\OrderService;

/**
 * 订单（对齐源站 order 分包的最小闭环：一级购下单/支付/取消/查询）
 *
 * 支付：当前支持余额支付（order/pay/balance）；
 * 微信/支付宝（hfpay/yeepay）需商户配置后接入，届时新增支付服务，控制器契约不变。
 */
class Order extends Cbase
{
    /**
     * POST order/primary —— 一级市场下单（创建待支付订单）
     */
    public function primary(): void
    {
        $iid = (int) $this->request->param('iid/d', 0);
        if ($iid <= 0) {
            throw CException::missing('iid');
        }
        $quantity = (int) $this->request->param('quantity/d', 1);
        $payWay   = (int) $this->request->param('payWay/d', 1);

        $this->success('', (new OrderService())->createPrimary(
            (int) $this->auth->id,
            $iid,
            $quantity,
            $payWay
        ));
    }

    /* ==================== 二级市场 ==================== */

    /**
     * POST order/secondary —— 市场单件买入 {nftId}
     */
    public function secondary(): void
    {
        $nftId = (int) $this->request->param('nftId/d', 0);
        if ($nftId <= 0) {
            throw CException::missing('nftId');
        }
        $this->success('', (new OrderService())->createSecondary((int) $this->auth->id, $nftId));
    }

    /**
     * POST order/secondary/quick —— 快捷买入（最低价成交）{iid}
     */
    public function secondaryQuick(): void
    {
        $iid = (int) $this->request->param('iid/d', 0);
        if ($iid <= 0) {
            throw CException::missing('iid');
        }
        $this->success('', (new OrderService())->createSecondaryQuick((int) $this->auth->id, $iid));
    }

    /**
     * POST order/secondary/batch —— 批量买入 {iid, unitPrice, quantity, payWay}
     */
    public function secondaryBatch(): void
    {
        $iid       = (int) $this->request->param('iid/d', 0);
        $unitPrice = (int) $this->request->param('unitPrice/d', 0);
        $quantity  = (int) $this->request->param('quantity/d', 1);
        if ($iid <= 0) {
            throw CException::missing('iid');
        }
        $this->success('', (new OrderService())->createSecondaryBatch((int) $this->auth->id, $iid, $unitPrice, $quantity));
    }

    /**
     * POST order/secondary/apply —— 求购 {iid, unitPrice, quantity}
     */
    public function secondaryApply(): void
    {
        $iid       = (int) $this->request->param('iid/d', 0);
        $unitPrice = (int) $this->request->param('unitPrice/d', 0);
        $quantity  = (int) $this->request->param('quantity/d', 1);
        if ($iid <= 0) {
            throw CException::missing('iid');
        }
        $this->success('', (new OrderService())->createNeed((int) $this->auth->id, $iid, $unitPrice, $quantity));
    }

    /**
     * POST order/secondary/trust —— 委托（按出价自动撮合）{iid, unitPrice, quantity}
     */
    public function secondaryTrust(): void
    {
        $iid       = (int) $this->request->param('iid/d', 0);
        $unitPrice = (int) $this->request->param('unitPrice/d', 0);
        $quantity  = (int) $this->request->param('quantity/d', 1);
        if ($iid <= 0) {
            throw CException::missing('iid');
        }
        $this->success('', (new OrderService())->createTrust((int) $this->auth->id, $iid, $unitPrice, $quantity));
    }

    /**
     * POST order/pay/balance —— 余额支付（支付成功即发货）
     */
    public function payBalance(): void
    {
        $orderId = (int) $this->request->param('orderId/d', (int) $this->request->param('id/d', 0));
        if ($orderId <= 0) {
            throw CException::missing('orderId');
        }

        $this->success('', (new OrderService())->payByBalance($orderId, (int) $this->auth->id));
    }

    /**
     * PUT order/cancel —— 取消待支付订单
     */
    public function cancel(): void
    {
        $orderId = (int) $this->request->param('orderId/d', (int) $this->request->param('id/d', 0));
        if ($orderId <= 0) {
            throw CException::missing('orderId');
        }

        (new OrderService())->cancel($orderId, (int) $this->auth->id);
        $this->success('', true);
    }

    /**
     * GET order —— 我的订单列表（源站契约：裸数组，items 恒为 []）
     */
    public function index(): void
    {
        $this->success('', (new OrderService())->myList(
            (int) $this->auth->id,
            (string) $this->request->param('type/s', ''),
            (string) $this->request->param('status/s', ''),
            $this->pageNo(),
            $this->pageSize()
        ));
    }

    /**
     * GET order/:id —— 订单详情（items 填真实明细）
     */
    public function detail(string $id = ''): void
    {
        $orderId = (int) $id ?: (int) $this->request->param('id/d', 0);
        if ($orderId <= 0) {
            throw CException::missing('id');
        }

        $this->success('', (new OrderService())->detail($orderId, (int) $this->auth->id));
    }
}
