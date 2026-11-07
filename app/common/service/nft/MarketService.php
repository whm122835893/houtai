<?php

namespace app\common\service\nft;

use app\admin\model\nft\Banner as BannerModel;
use app\admin\model\nft\Collection as CollectionModel;
use app\admin\model\nft\Hold as HoldModel;
use app\admin\model\nft\MarketOrder as MarketOrderModel;
use app\admin\model\nft\Notice as NoticeModel;
use app\admin\model\nft\Order as OrderModel;
use think\facade\Db;

/**
 * 二级市场服务 —— 寄售 / 下架 / 求购成交 / 委托撮合 / 市场列表
 *
 * 寄售单（market_order）状态：0=在售 1=已售 2=已下架 3=已锁定(下单占单)
 * hold.status 扩展：3=已转出（原持仓凭证关闭，买入方生成新凭证 source=2）
 *
 * 资金口径：
 *   - 市场成交扣 6% 综合服务费，卖方实收 floor(价格 * 0.94)；
 *   - 求购/委托单支付后金额进入买方 user.frozen 托管，按件释放给卖方。
 *
 * 订单类型（order.type）：2=市场买入(源站20) 3=批量买入(21) 4=求购(22) 5=委托(23)
 */
class MarketService
{
    /** 综合服务费（万分比）：6% */
    public const FEE_BASIS = 600;

    /** 寄售单状态 */
    public const SELL_ON = 0;
    public const SELL_SOLD = 1;
    public const SELL_OFF = 2;
    public const SELL_LOCK = 3;

    private WalletService $wallet;
    private UserService $user;

    public function __construct()
    {
        $this->wallet = new WalletService();
        $this->user   = new UserService();
    }

    /* ==================== 寄售 ==================== */

    /**
     * 单条寄售（源站 POST /nft/sell/up 契约：payWay 固定 1）
     *
     * @param string $secondPassword 二级密码明文（未设置时放行）
     * @throws CException 持仓不可售 / 超过限价
     */
    public function sellUp(int $userId, int $nftId, int $price, string $secondPassword = ''): void
    {
        if ($price <= 0) {
            throw CException::bad('寄售价格须大于 0');
        }
        $this->user->verifySecondPassword($userId, $secondPassword);

        $hold = HoldModel::where('id', $nftId)->where('user_id', $userId)->find();
        if (!$hold || (int) $hold->status !== 0) {
            throw CException::biz('藏品当前不可寄售');
        }
        $this->listOneHold($userId, $hold->toArray(), $price);
    }

    /**
     * 批量寄售（源站 POST /nft/sell/up/batch 契约：价格区间内随机定价）
     */
    public function sellUpBatch(int $userId, int $iid, int $minPrice, int $maxPrice, int $quantity, string $secondPassword = ''): void
    {
        if ($minPrice <= 0 || $maxPrice < $minPrice) {
            throw CException::bad('价格区间不合法');
        }
        if ($quantity < 1 || $quantity > 99) {
            throw CException::bad('数量须为 1-99');
        }
        $this->user->verifySecondPassword($userId, $secondPassword);

        $holds = HoldModel::where('user_id', $userId)
            ->where('collection_id', $iid)
            ->where('status', 0)
            ->order('id', 'asc')
            ->limit($quantity)
            ->select()
            ->toArray();
        if (count($holds) < $quantity) {
            throw CException::biz('可寄售数量不足');
        }
        foreach ($holds as $hold) {
            $this->listOneHold($userId, $hold, random_int($minPrice, $maxPrice));
        }
    }

    /**
     * 下架（源站 PUT /nft/sell/down 契约：nftId = 持仓ID）
     */
    public function sellDown(int $userId, int $nftId): void
    {
        $sell = MarketOrderModel::where('hold_id', $nftId)
            ->where('seller_id', $userId)
            ->where('status', self::SELL_ON)
            ->find();
        if (!$sell) {
            throw CException::notFound('寄售单不存在');
        }

        Db::startTrans();
        try {
            MarketOrderModel::where('id', $sell->id)->update([
                'status'     => self::SELL_OFF,
                'updatetime' => time(),
            ]);
            Db::name('hold')->where('id', $nftId)->update(['status' => 0, 'sell_order_id' => 0, 'updatetime' => time()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw CException::biz($e->getMessage());
        }
    }

    /**
     * 出售给求购单（源站 POST /nft/sell/apply 契约）
     *
     * 求购单须已支付且未满；卖方实收 = floor(求购单价 * 0.94)，买方按单价释放托管
     */
    public function sellNeed(int $userId, int $orderId, int $nftId, string $secondPassword = ''): void
    {
        $this->user->verifySecondPassword($userId, $secondPassword);

        $need = OrderModel::where('id', $orderId)->where('type', 4)->find();
        if (!$need) {
            throw CException::notFound('求购单不存在');
        }
        $buyerId = (int) $need->user_id;
        if ($buyerId === $userId) {
            throw CException::biz('不能出售给自己的求购单');
        }
        if ((int) $need->status !== 1 || (int) $need->done_num >= (int) $need->num) {
            throw CException::biz('求购单已完成或已取消');
        }

        $hold = HoldModel::where('id', $nftId)->where('user_id', $userId)->find();
        if (!$hold || (int) $hold->status !== 0) {
            throw CException::biz('藏品当前不可出售');
        }
        if ((int) $hold->collection_id !== (int) $need->collection_id) {
            throw CException::biz('藏品与求购单不匹配');
        }

        $unitPrice = (int) (((int) $need->amount) / max(1, (int) $need->num));
        Db::startTrans();
        try {
            // 求购单行锁复核，防并发超卖
            $fresh = Db::name('order')->where('id', $orderId)->where('type', 4)->lock(true)->find();
            if ((int) $fresh['status'] !== 1 || (int) $fresh['done_num'] >= (int) $fresh['num']) {
                throw CException::biz('求购单已完成或已取消');
            }

            // 1. 释放买方托管 + 卖方收款（6% 综合服务费）
            $this->wallet->releaseEscrow($buyerId, $unitPrice, (string) $fresh['order_no'], '求购单托管释放');
            $this->wallet->income($userId, intdiv($unitPrice * (10000 - self::FEE_BASIS), 10000), (string) $fresh['order_no'], '市场出售给求购单');

            // 2. 过户持仓
            $this->transferHold($hold->toArray(), $buyerId, $orderId);

            // 3. 求购单进度
            Db::name('order')->where('id', $orderId)->inc('done_num')->update(['updatetime' => time()]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            if ($e instanceof CException) {
                throw $e;
            }
            throw CException::biz($e->getMessage());
        }
    }

    /** 我的出售记录（源站 GET /nft/sell/sold 契约：按藏品聚合） */
    public function sellSoldList(int $userId, string $keyword = '', int $pageNo = 1, int $pageSize = 10): array
    {
        $query = MarketOrderModel::where('seller_id', $userId)->where('status', self::SELL_SOLD);

        $rows = $query->field('collection_id, count(id) as cnt, max(price) as last_price')
            ->group('collection_id')
            ->order(Db::raw('max(id) DESC'))
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();
        if (!$rows) {
            return [];
        }

        $cols = CollectionModel::where('id', 'in', array_column($rows, 'collection_id'))->column('name,cover', 'id');
        $out  = [];
        foreach ($rows as $r) {
            $kw    = (string) ($cols[(int) $r['collection_id']]['name'] ?? '');
            $cover = (string) ($cols[(int) $r['collection_id']]['cover'] ?? '');
            if ($keyword !== '' && !str_contains($kw, $keyword)) {
                continue;
            }
            $out[] = [
                'name'        => $kw,
                'imageUrl'    => $cover !== '' ? full_url($cover, true) : '',
                'ownQuantity' => (int) $r['cnt'],
                'price'       => (int) $r['last_price'],
                'priceChange' => 0,
            ];
        }
        return $out;
    }

    /* ==================== 市场数据 ==================== */

    /** 市场列表（源站 GET /nft/secondary 契约：q/cid/pageNo） */
    public function indexList(string $q = '', int $cid = 0, int $pageNo = 1, int $pageSize = 10): array
    {
        $query = CollectionModel::where('status', 'in', CollectionService::VISIBLE_STATUS)->where('resale', 1);
        if ($q !== '') {
            $query->whereLike('name', '%' . $q . '%');
        }
        if ($cid > 0) {
            $query->where('category_id', $cid);
        }
        $rows = $query->order('id', 'desc')->page($pageNo, $pageSize)->select()->toArray();
        return array_map(fn(array $c) => $this->rowOfSecondary($c, $this->statsOf((int) $c['id'])), $rows);
    }

    /** 关注列表（源站 GET /nft/secondary/follow 契约） */
    public function followList(int $userId, int $pageNo = 1, int $pageSize = 10): array
    {
        $iids = Db::name('user_follow')->where('user_id', $userId)
            ->order('id', 'desc')->page($pageNo, $pageSize)->column('iid');
        if (!$iids) {
            return [];
        }
        $rows = CollectionModel::where('id', 'in', $iids)
            ->where('status', 'in', CollectionService::VISIBLE_STATUS)
            ->select()
            ->toArray();
        return array_map(fn(array $c) => $this->rowOfSecondary($c, $this->statsOf((int) $c['id']), true), $rows);
    }

    /** 藏品市场详情（源站 GET /nft/secondary/{iid} 契约） */
    public function itemDetail(int $iid): array
    {
        $c = CollectionModel::where('id', $iid)->find();
        if (!$c) {
            throw CException::notFound();
        }
        $stats = $this->statsOf($iid);
        $row   = $this->rowOfSecondary($c->toArray(), $stats);

        // 源站市场详情字段（今日成交 / 近一小时成交）
        $dayStart = strtotime(date('Y-m-d'));
        $daySold  = MarketOrderModel::where('collection_id', $iid)
            ->where('status', self::SELL_SOLD)->where('soldtime', '>=', $dayStart)
            ->field('count(id) cnt, ifnull(max(price),0) maxp, ifnull(min(price),0) minp')->find();
        $hourCnt = MarketOrderModel::where('collection_id', $iid)
            ->where('status', self::SELL_SOLD)->where('soldtime', '>=', time() - 3600)->count();

        return array_merge($row, [
            'authorName'           => (string) ($c->author ?? ''),
            'issueQuantity'        => (int) $c->stock,
            'nftQuantity'          => (int) $c->sold,
            'todayTradeQuantity'   => (int) ($daySold['cnt'] ?? 0),
            'todayTradeMaxAmount'  => (int) ($daySold['maxp'] ?? 0),
            'todayTradeMinAmount'  => (int) ($daySold['minp'] ?? 0),
            'hourTradeQuantity'    => $hourCnt,
        ]);
    }

    /** 在售列表（源站 GET /nft/secondary/sell 契约） */
    public function saleList(int $iid, int $pageNo = 1, int $pageSize = 10): array
    {
        $rows = MarketOrderModel::alias('m')
            ->join('collection c', 'c.id = m.collection_id')
            ->where('m.collection_id', $iid)
            ->where('m.status', self::SELL_ON)
            ->field('m.hold_id, m.price, c.name')
            ->order('m.price', 'asc')->order('m.id', 'asc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return array_map(fn(array $r) => [
            'nftId'   => (string) $r['hold_id'],
            'name'    => (string) $r['name'],
            'price'   => (int) $r['price'],
            'token'   => (int) $r['hold_id'],
            'payLock' => false,
            'chainId' => '',
        ], $rows);
    }

    /** 成交记录（源站 GET /nft/secondary/sold 契约） */
    public function soldList(int $iid, int $pageNo = 1, int $pageSize = 10): array
    {
        $rows = MarketOrderModel::where('collection_id', $iid)
            ->where('status', self::SELL_SOLD)
            ->order('soldtime', 'desc')->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return array_map(fn(array $r) => [
            'id'        => (string) $r['id'],
            'token'     => (int) $r['hold_id'],
            'soldPrice' => (int) $r['price'],
            'soldTime'  => (string) ((int) ($r['soldtime'] ?? 0) * 1000),
            'chainId'   => '',
        ], $rows);
    }

    /** 求购列表（源站 GET /nft/secondary/apply 契约：进行中的求购单） */
    public function applyList(int $iid, int $pageNo = 1, int $pageSize = 10): array
    {
        $rows = OrderModel::where('type', 4)
            ->where('status', 1)
            ->where('collection_id', $iid)
            ->whereRaw('done_num < num')
            ->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();
        if (!$rows) {
            return [];
        }

        $names = CollectionModel::where('id', 'in', array_column($rows, 'collection_id'))->column('name', 'id');
        return array_map(fn(array $r) => [
            'orderId'   => (string) $r['id'],
            'title'     => (string) ($names[(int) $r['collection_id']] ?? ''),
            'unitPrice' => intdiv((int) $r['amount'], max(1, (int) $r['num'])),
            'quantity'  => (int) $r['num'],
        ], $rows);
    }

    /** 委托列表（源站 GET /nft/secondary/trust 契约） */
    public function trustList(int $iid, int $pageNo = 1, int $pageSize = 10): array
    {
        $rows = OrderModel::where('type', 5)
            ->where('status', 1)
            ->where('collection_id', $iid)
            ->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return array_map(fn(array $r) => [
            'orderId'           => (string) $r['id'],
            'createTime'        => (string) ((int) ($r['createtime'] ?? 0) * 1000),
            'unitPrice'         => intdiv((int) $r['amount'], max(1, (int) $r['num'])),
            'quantity'          => (int) $r['num'],
            'completedQuantity' => (int) ($r['done_num'] ?? 0),
        ], $rows);
    }

    /* ==================== 关注 ==================== */

    /** 关注藏品（源站 POST /item/follow/{iid}） */
    public function follow(int $userId, int $iid): void
    {
        if (!CollectionModel::where('id', $iid)->count()) {
            throw CException::notFound();
        }
        Db::name('user_follow')->replace()->insert([
            'user_id'    => $userId,
            'iid'        => $iid,
            'createtime' => time(),
        ]);
    }

    /** 取消关注（源站 DELETE /item/unfollow/{iid}） */
    public function unfollow(int $userId, int $iid): void
    {
        Db::name('user_follow')->where('user_id', $userId)->where('iid', $iid)->delete();
    }

    /* ==================== 成交落账（OrderService 支付成功后调用，须处于其事务内） ==================== */

    /**
     * 市场买入结算：本订单锁定的寄售单逐一过户 + 卖方收款
     *
     * @param array $order 已支付订单行（type 2/3）
     * @return int[] 买入方新持仓 ID
     */
    public function settlePurchase(array $order): array
    {
        $sells = MarketOrderModel::where('order_id', (int) $order['id'])
            ->where('status', self::SELL_LOCK)
            ->select()
            ->toArray();
        if (!$sells) {
            throw CException::biz('订单无可交割的寄售单');
        }

        $newIds = [];
        foreach ($sells as $sell) {
            $hold = HoldModel::where('id', (int) $sell['hold_id'])->find();
            if (!$hold || (int) $hold->status !== 1) {
                throw CException::biz('寄售持仓状态异常');
            }

            // 卖方收款（扣 6% 综合服务费）
            $price = (int) $sell['price'];
            $this->wallet->income((int) $sell['seller_id'], intdiv($price * (10000 - self::FEE_BASIS), 10000), (string) $order['order_no'], '市场出售');

            // 过户 + 寄售单成交
            $newIds[] = $this->transferHold($hold->toArray(), (int) $order['user_id'], (int) $order['id']);
            MarketOrderModel::where('id', $sell['id'])->update([
                'status'    => self::SELL_SOLD,
                'buyer_id'  => (int) $order['user_id'],
                'soldtime'  => time(),
                'updatetime' => time(),
            ]);
        }
        return $newIds;
    }

    /**
     * 委托撮合（寄售后调用）：与委托单按出价从高到低撮合
     *
     * @param array $hold 待售持仓行
     * @param int   $sellOrderId 寄售单ID（成交后标记已售）
     * @param int   $sellPrice 寄售价
     * @return bool 是否成交
     */
    public function matchTrust(int $sellerId, int $collectionId, array $hold, int $sellOrderId, int $sellPrice): bool
    {
        $trusts = OrderModel::where('type', 5)
            ->where('status', 1)
            ->where('collection_id', $collectionId)
            ->where('user_id', '<>', $sellerId)
            ->whereRaw('done_num < num')
            ->order('amount', 'desc')->order('id', 'asc')
            ->lock(true)
            ->select()
            ->toArray();

        foreach ($trusts as $trust) {
            $unitPrice = intdiv((int) $trust['amount'], max(1, (int) $trust['num']));
            if ($unitPrice < 1) {
                continue;
            }
            // 委托撮合前提：寄售价 ≤ 委托出价；成交价 = 委托单价（委托方按出价买断）
            if ($sellPrice > $unitPrice) {
                continue;
            }

            try {
                Db::startTrans();
                // 委托单行锁复核
                $fresh = Db::name('order')->where('id', $trust['id'])->lock(true)->find();
                if ((int) $fresh['status'] !== 1 || (int) $fresh['done_num'] >= (int) $fresh['num']) {
                    Db::rollback();
                    continue;
                }

                $this->wallet->releaseEscrow((int) $fresh['user_id'], $unitPrice, (string) $fresh['order_no'], '委托单托管释放');
                $this->wallet->income($sellerId, intdiv($unitPrice * (10000 - self::FEE_BASIS), 10000), (string) $fresh['order_no'], '委托单出售');

                $this->transferHold($hold, (int) $fresh['user_id'], (int) $fresh['id']);
                Db::name('order')->where('id', $fresh['id'])->inc('done_num')->update(['updatetime' => time()]);

                // 寄售单标记成交
                if ($sellOrderId > 0) {
                    MarketOrderModel::where('id', $sellOrderId)->update([
                        'status'    => self::SELL_SOLD,
                        'buyer_id'  => (int) $fresh['user_id'],
                        'soldtime'  => time(),
                        'updatetime' => time(),
                    ]);
                }

                Db::commit();
                return true;
            } catch (\Throwable $e) {
                Db::rollback();
                // 撮合失败不影响寄售成功
                continue;
            }
        }
        return false;
    }

    /* ==================== 内部 ==================== */

    /** 单件持仓上架（寄售单 + hold 置寄售中 + 委托撮合） */
    private function listOneHold(int $userId, array $hold, int $price): void
    {
        $collection = CollectionModel::where('id', (int) $hold['collection_id'])->find();
        if (!$collection || (int) $collection->resale !== 1) {
            throw CException::biz('该藏品不支持寄售');
        }
        $limit = (int) $collection->resale_price_limit;
        if ($limit > 0 && $price > $limit) {
            throw CException::biz('寄售价格超过限价');
        }

        $now = time();
        Db::startTrans();
        try {
            $sell = MarketOrderModel::create([
                'order_no'      => $this->genSellNo(),
                'seller_id'     => $userId,
                'hold_id'       => (int) $hold['id'],
                'collection_id' => (int) $hold['collection_id'],
                'price'         => $price,
                'status'        => self::SELL_ON,
                'order_id'      => 0,
                'buyer_id'      => 0,
                'createtime'    => $now,
                'updatetime'    => $now,
            ]);
            Db::name('hold')->where('id', $hold['id'])->update(['status' => 1, 'sell_order_id' => $sell->id, 'updatetime' => $now]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw CException::biz($e->getMessage());
        }

        // 委托撮合（成交则寄售单直接完成，不再对外挂售）
        $this->matchTrust($userId, (int) $hold['collection_id'], $hold, (int) $sell->id, $price);
    }

    /**
     * 持仓过户：原凭证置已转出，买入方生成新凭证（source=2）
     *
     * 冗余计数：卖方 hold_count -1，买方 +1
     *
     * @return int 新持仓 ID
     */
    private function transferHold(array $hold, int $toUserId, int $refOrderId): int
    {
        $now = time();
        Db::name('hold')->where('id', (int) $hold['id'])->update(['status' => 3, 'updatetime' => $now]);

        $new = HoldModel::create([
            'sn'            => (new HoldService())->genSn((int) $hold['collection_id']),
            'collection_id' => (int) $hold['collection_id'],
            'user_id'       => $toUserId,
            'source'        => 2,
            'status'        => 0,
            'ref_id'        => $refOrderId,
            'mint_time'     => (int) ($hold['mint_time'] ?? $now),
            'createtime'    => $now,
            'updatetime'    => $now,
        ]);
        Db::name('user')->where('id', $toUserId)->inc('hold_count')->update();
        Db::name('user')->where('id', (int) $hold['user_id'])->inc('hold_count', -1)->update();
        return (int) $new->id;
    }

    /** 藏品市场统计（行内字段 + 在售最低价 / 最高价） */
    private function statsOf(int $iid): array
    {
        $row = MarketOrderModel::where('collection_id', $iid)->where('status', self::SELL_ON)
            ->field('count(id) cnt, ifnull(min(price),0) minp, ifnull(max(price),0) maxp')->find();
        return [
            'saleCount' => (int) ($row['cnt'] ?? 0),
            'minPrice'  => (int) ($row['minp'] ?? 0),
            'maxPrice'  => (int) ($row['maxp'] ?? 0),
        ];
    }

    /** 市场列表行（源站 Transformer::secondary 字段） */
    private function rowOfSecondary(array $c, array $stats, bool $isFollow = false): array
    {
        $cover = (string) ($c['cover'] ?? '');
        return [
            'iid'                => (string) $c['id'],
            'name'               => (string) ($c['name'] ?? ''),
            'imageUrl'           => $cover !== '' ? full_url($cover, true) : '',
            'issueQuantity'      => (int) ($c['stock'] ?? 0),
            'nftQuantity'        => (int) ($c['sold'] ?? 0),
            'tradeQuantity'      => $stats['saleCount'],
            'secondaryHighPrice' => $this->highPrice((int) $c['id'], $stats),
            'minPrice'           => $stats['minPrice'],
            'priceChange'        => 0,
            'isFollow'           => $isFollow,
        ];
    }

    /** 二级最高价：优先限价，否则在售最高价（求购页用作最高限价） */
    private function highPrice(int $iid, array $stats): int
    {
        $limit = (int) (CollectionModel::where('id', $iid)->value('resale_price_limit') ?: 0);
        if ($limit > 0) {
            return $limit;
        }
        $maxSold = (int) (MarketOrderModel::where('collection_id', $iid)
            ->where('status', self::SELL_SOLD)->max('price') ?: 0);
        return max($stats['maxPrice'], $maxSold);
    }

    /** 寄售单号：M + 日期 + 随机 */
    private function genSellNo(): string
    {
        return 'M' . date('YmdHis') . substr((string) microtime(), 2, 4) . mt_rand(1000, 9999);
    }
}
