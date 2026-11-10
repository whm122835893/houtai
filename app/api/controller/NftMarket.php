<?php

namespace app\api\controller;

use Throwable;
use think\facade\Db;
use app\common\controller\Frontend;
use app\common\service\NftTrade;

/**
 * C 端市场寄售（二级市场）
 *
 * 接口：
 *   GET  /api/nft_market/index            在售寄售单（免登录，需 market_open=1）
 *   GET  /api/nft_market/mine             我的寄售单（需 token）
 *   POST /api/nft_market/sell             挂单寄售 {hold_id, price(元)}（需 token）
 *   POST /api/nft_market/buy              买入 {id: 寄售单ID}（需 token）
 *   POST /api/nft_market/cancel           撤单 {id}（需 token，卖家）
 *
 * 规则：market_open 总闸 → collection.resale 单品开关 → 限价区间 [resale_price_min, resale_price_limit]
 * 成交：手续费率读 setting.sell_fee_rate(%)，卖家净入 price−fee；hold 过户 source=2 市场购。
 */
class NftMarket extends Frontend
{
    protected array $noNeedLogin = ['index'];
    protected array $noNeedRight = ['*'];

    /**
     * 在售寄售单
     */
    public function index(): void
    {
        $cid    = $this->request->get('collection_id/d', 0);
        $limit  = min(100, max(1, $this->request->get('limit/d', 20)));
        $page   = max(1, $this->request->get('page/d', 1));

        $where = [['m.status', '=', 0]];
        if ($cid > 0) {
            $where[] = ['m.collection_id', '=', $cid];
        }
        $query = Db::name('market_order')->alias('m')
            ->leftJoin('collection c', 'c.id = m.collection_id')
            ->leftJoin('user u', 'u.id = m.seller_id')
            ->where($where);
        $total = $query->count();
        $list  = $query
            ->field('m.id,m.collection_id,c.name as collection_name,c.cover,m.hold_id,m.price,u.nickname as seller')
            ->order('m.price', 'asc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$row) {
            $row['price']  = NftTrade::yuan((int) $row['price']);
            $row['seller'] = $row['seller'] ?: '藏友';
        }
        unset($row);
        $this->success('', ['list' => $list, 'total' => $total, 'market_open' => (int) NftTrade::cfg('market_open', '1')]);
    }

    /**
     * 我的寄售单
     */
    public function mine(): void
    {
        $limit = min(100, max(1, $this->request->get('limit/d', 20)));
        $page  = max(1, $this->request->get('page/d', 1));
        $query = Db::name('market_order')->alias('m')
            ->leftJoin('collection c', 'c.id = m.collection_id')
            ->where('m.seller_id', $this->auth->id);
        $total = $query->count();
        $list  = $query->field('m.id,m.collection_id,c.name as collection_name,c.cover,m.hold_id,m.price,m.status,m.soldtime,m.createtime')
            ->order('m.id', 'desc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$row) {
            $row['price']     = NftTrade::yuan((int) $row['price']);
            $row['fee_rate']  = (float) NftTrade::cfg('sell_fee_rate', '5');
            $row['net_price'] = NftTrade::yuan((int) round((int) $row['price'] * (100 - (float) NftTrade::cfg('sell_fee_rate', '5')) / 100));
            $row['status_txt'] = ['0' => '在售', '1' => '已售', '2' => '已下架'][(string) $row['status']] ?? '未知';
        }
        unset($row);
        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 挂单寄售
     */
    public function sell(): void
    {
        if ((int) NftTrade::cfg('market_open', '1') !== 1) {
            $this->error('二级市场当前关闭');
        }
        $holdId = $this->request->post('hold_id/d', 0);
        $price  = (int) round((float) $this->request->post('price/f', 0) * 100);

        Db::startTrans();
        try {
            $hold = Db::name('hold')->where('id', $holdId)->lock(true)->find();
            if (!$hold || (int) $hold['user_id'] !== (int) $this->auth->id) {
                throw new \RuntimeException('持仓不存在');
            }
            if ((int) $hold['status'] !== 0) {
                $labels = ['持有', '寄售中', '已锁定', '已回收'];
                throw new \RuntimeException('该藏品当前不可寄售（' . ($labels[(int) $hold['status']] ?? '状态异常') . '）');
            }
            $col = Db::name('collection')->where('id', (int) $hold['collection_id'])->lock(true)->find();
            if (!$col || (int) $col['resale'] !== 1) {
                throw new \RuntimeException('该藏品未开放二级市场');
            }
            $limitMax = (int) $col['resale_price_limit'];
            $limitMin = (int) $col['resale_price_min'];
            if ($price <= 0) {
                throw new \RuntimeException('请输入有效寄售价');
            }
            if ($limitMax > 0 && $price > $limitMax) {
                throw new \RuntimeException('寄售价不得超过限价 ' . NftTrade::yuan($limitMax) . ' 元');
            }
            if ($limitMin > 0 && $price < $limitMin) {
                throw new \RuntimeException('寄售价不得低于下限 ' . NftTrade::yuan($limitMin) . ' 元');
            }
            if (Db::name('market_order')->where('hold_id', $holdId)->where('status', 0)->count() > 0) {
                throw new \RuntimeException('该持仓已在寄售中');
            }

            $now = time();
            $orderNo = 'MK' . date('YmdHis') . $this->auth->id . mt_rand(100, 999);
            while (Db::name('market_order')->where('order_no', $orderNo)->count() > 0) {
                $orderNo = 'MK' . date('YmdHis') . $this->auth->id . mt_rand(1000, 9999);
            }
            Db::name('market_order')->insert([
                'order_no'      => $orderNo,
                'seller_id'     => $this->auth->id,
                'hold_id'       => $holdId,
                'collection_id' => (int) $hold['collection_id'],
                'price'         => $price,
                'status'        => 0,
                'createtime'    => $now,
            ]);
            Db::name('hold')->where('id', $holdId)->update(['status' => 1, 'updatetime' => $now]); // 寄售中
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '挂单失败');
        }
        $this->success('寄售挂单成功', ['order_no' => $orderNo ?? '', 'price' => NftTrade::yuan($price)]);
    }

    /**
     * 买入（锁单过户 + 手续费 + 双方钱包流水，同事务）
     */
    public function buy(): void
    {
        if ((int) NftTrade::cfg('market_open', '1') !== 1) {
            $this->error('二级市场当前关闭');
        }
        $id = $this->request->post('id/d', 0);

        Db::startTrans();
        try {
            $mo = Db::name('market_order')->where('id', $id)->lock(true)->find();
            if (!$mo || (int) $mo['status'] !== 0) {
                throw new \RuntimeException('该寄售单已不是在售状态');
            }
            if ((int) $mo['seller_id'] === (int) $this->auth->id) {
                throw new \RuntimeException('不能购买自己挂售的藏品');
            }
            $hold = Db::name('hold')->where('id', (int) $mo['hold_id'])->lock(true)->find();
            if (!$hold || (int) $hold['status'] !== 1 || (int) $hold['user_id'] !== (int) $mo['seller_id']) {
                throw new \RuntimeException('藏品持仓状态异常，交易终止');
            }
            $col = Db::name('collection')->where('id', (int) $mo['collection_id'])->find();
            $price = (int) $mo['price'];
            $fee   = (int) round($price * (float) NftTrade::cfg('sell_fee_rate', '5') / 100);
            $now   = time();

            // 寄售单成交
            Db::name('market_order')->where('id', $id)->update([
                'status' => 1, 'buyer_id' => $this->auth->id, 'soldtime' => $now,
            ]);
            // 持仓过户：来源=市场购，恢复持有
            Db::name('hold')->where('id', (int) $hold['id'])->update([
                'user_id' => $this->auth->id, 'source' => 2, 'status' => 0,
                'sell_order_id' => $id, 'updatetime' => $now,
            ]);
            // 买家订单（市场购）
            $orderNo = 'MB' . date('YmdHis') . $this->auth->id . mt_rand(100, 999);
            while (Db::name('order')->where('order_no', $orderNo)->count() > 0) {
                $orderNo = 'MB' . date('YmdHis') . $this->auth->id . mt_rand(1000, 9999);
            }
            Db::name('order')->insert([
                'order_no' => $orderNo, 'user_id' => $this->auth->id,
                'type' => 2, 'collection_id' => (int) $mo['collection_id'],
                'hold_id' => (int) $hold['id'], 'num' => 1,
                'amount' => $price, 'pay_type' => 'mock',
                'pay_no' => 'MOCK' . date('YmdHis') . mt_rand(1000, 9999),
                'status' => 1, 'paytime' => $now, 'createtime' => $now, 'updatetime' => $now,
            ]);
            // 钱包：卖家净入 / 买家支出
            NftTrade::walletMove((int) $mo['seller_id'], 'income', $price - $fee, $orderNo, '寄售成交「' . ($col['name'] ?? '') . '」（费率 ' . NftTrade::cfg('sell_fee_rate', '5') . '%，手续费 ' . NftTrade::yuan($fee) . ' 元）', true);
            NftTrade::walletMove((int) $this->auth->id, 'expense', -$price, $orderNo, '市场购入「' . ($col['name'] ?? '') . '」（外部支付）', false);

            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '购买失败');
        }
        $this->success('购买成功，藏品已入仓', [
            'order_no' => $orderNo ?? '',
            'hold_id'  => (int) $hold['id'] ?? 0,
            'price'    => NftTrade::yuan($price),
        ]);
    }

    /**
     * 卖家撤单
     */
    public function cancel(): void
    {
        $id = $this->request->post('id/d', 0);
        Db::startTrans();
        try {
            $mo = Db::name('market_order')->where('id', $id)->lock(true)->find();
            if (!$mo || (int) $mo['seller_id'] !== (int) $this->auth->id) {
                throw new \RuntimeException('寄售单不存在');
            }
            if ((int) $mo['status'] !== 0) {
                throw new \RuntimeException('仅在「在售」状态下可撤单');
            }
            Db::name('market_order')->where('id', $id)->update(['status' => 2, 'updatetime' => time()]);
            Db::name('hold')->where('id', (int) $mo['hold_id'])->where('status', 1)->update(['status' => 0, 'updatetime' => time()]);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '撤单失败');
        }
        $this->success('已撤单，藏品回到持仓');
    }
}
