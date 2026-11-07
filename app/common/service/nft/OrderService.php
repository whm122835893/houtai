<?php

namespace app\common\service\nft;

use app\admin\model\nft\Collection as CollectionModel;
use app\admin\model\nft\Hold as HoldModel;
use app\admin\model\nft\MarketOrder as MarketOrderModel;
use app\admin\model\nft\Order as OrderModel;
use Throwable;
use think\facade\Db;

/**
 * 订单服务 —— 一级市场下单 / 支付 / 取消 / 查询
 *
 * 订单状态（order.status）：0=待支付 1=已支付 2=已取消 3=已退款
 * 源站契约映射：0→0(待付款) 1→2(已完成) 2→3(已取消) 3→4(已退款)
 *
 * 事务边界：支付 = 扣余额 + 订单置已付 + 发持仓（含原子扣库存），任一失败整体回滚；
 * 幂等：已支付订单重复支付直接返回成功。
 */
class OrderService
{
    /** 待支付订单支付窗口（秒） */
    public const PAY_WINDOW = 900;

    private WalletService $wallet;
    private HoldService $hold;
    private MarketService $market;

    public function __construct()
    {
        $this->wallet = new WalletService();
        $this->hold   = new HoldService();
        $this->market = new MarketService();
    }

    /**
     * 一级市场下单（源站 POST /order/primary 契约：返回待支付订单）
     *
     * @param int $userId 买家
     * @param int $collectionId 藏品ID（源站参数 iid）
     * @param int $quantity 数量
     * @param int $payWay 支付方式 1=余额
     * @throws CException 藏品不可购 / 参数错误
     */
    public function createPrimary(int $userId, int $collectionId, int $quantity, int $payWay = 1): array
    {
        if ($quantity < 1 || $quantity > 99) {
            throw CException::bad('购买数量须为 1-99');
        }

        $collection = CollectionModel::where('id', $collectionId)
            ->where('status', 'in', CollectionService::VISIBLE_STATUS)
            ->find();
        if (!$collection) {
            throw CException::notFound();
        }

        // 下单即校验（支付时复核，防跨窗口状态变化）
        (new CollectionService())->ensureBuyable($collection->toArray(), $quantity, $userId);

        $unitPrice = (int) $collection->price;
        $now       = time();
        $orderNo   = $this->genOrderNo();

        $order = OrderModel::create([
            'order_no'      => $orderNo,
            'user_id'       => $userId,
            'type'          => 1,
            'collection_id' => $collectionId,
            'hold_id'       => 0,
            'num'           => $quantity,
            'amount'        => $unitPrice * $quantity,
            'pay_type'      => $payWay === 1 ? 'balance' : '',
            'status'        => 0,
            'createtime'    => $now,
            'updatetime'    => $now,
        ]);

        return $this->toOrder($order->toArray(), true, [], $collection->toArray());
    }

    /* ==================== 二级市场下单 ==================== */

    /**
     * 市场单件买入（源站 POST /order/secondary 契约：nftId = 持仓ID）
     *
     * 下单即锁定寄售单（防并发双卖），返回待支付订单
     */
    public function createSecondary(int $userId, int $nftId): array
    {
        $sellId = (int) (MarketOrderModel::where('hold_id', $nftId)
            ->where('status', MarketService::SELL_ON)
            ->value('id') ?: 0);
        if (!$sellId) {
            throw CException::biz('该藏品已被买走或已下架');
        }
        return $this->lockAndCreate($userId, [$sellId], 2);
    }

    /**
     * 快捷买入（源站 POST /order/secondary/quick 契约：按最低价成交）
     */
    public function createSecondaryQuick(int $userId, int $iid): array
    {
        $sellId = (int) (MarketOrderModel::where('collection_id', $iid)
            ->where('status', MarketService::SELL_ON)
            ->order('price', 'asc')->order('id', 'asc')
            ->value('id') ?: 0);
        if (!$sellId) {
            throw CException::biz('当前暂无在售藏品');
        }
        return $this->lockAndCreate($userId, [$sellId], 2);
    }

    /**
     * 批量买入（源站 POST /order/secondary/batch 契约：unitPrice 限价）
     *
     * 锁定价格 ≤ 限价的在售单（价低优先，排除自己），实际成交量以锁定结果为准
     */
    public function createSecondaryBatch(int $userId, int $iid, int $unitPrice, int $quantity): array
    {
        if ($unitPrice < 1) {
            throw CException::bad('购买单价须大于 0');
        }
        if ($quantity < 1 || $quantity > 99) {
            throw CException::bad('购买数量须为 1-99');
        }
        $sellIds = MarketOrderModel::where('collection_id', $iid)
            ->where('status', MarketService::SELL_ON)
            ->where('seller_id', '<>', $userId)
            ->where('price', '<=', $unitPrice)
            ->order('price', 'asc')->order('id', 'asc')
            ->limit($quantity)
            ->column('id');
        if (!$sellIds) {
            throw CException::biz('无符合条件的在售藏品');
        }
        return $this->lockAndCreate($userId, array_map('intval', $sellIds), 3);
    }

    /**
     * 求购（源站 POST /order/secondary/apply 契约：返回待支付订单，支付后挂出）
     */
    public function createNeed(int $userId, int $iid, int $unitPrice, int $quantity): array
    {
        return $this->createIntent($userId, $iid, $unitPrice, $quantity, 4);
    }

    /**
     * 委托（源站 POST /order/secondary/trust 契约：支付后按出价自动撮合寄售）
     */
    public function createTrust(int $userId, int $iid, int $unitPrice, int $quantity): array
    {
        return $this->createIntent($userId, $iid, $unitPrice, $quantity, 5);
    }

    /** 求购/委托共用：生成待支付意向单 */
    private function createIntent(int $userId, int $iid, int $unitPrice, int $quantity, int $type): array
    {
        if ($unitPrice < 1) {
            throw CException::bad('单价须大于 0');
        }
        if ($quantity < 1 || $quantity > 99) {
            throw CException::bad('数量须为 1-99');
        }
        $collection = CollectionModel::where('id', $iid)
            ->where('status', 'in', CollectionService::VISIBLE_STATUS)
            ->find();
        if (!$collection) {
            throw CException::notFound();
        }

        $now   = time();
        $order = OrderModel::create([
            'order_no'      => $this->genOrderNo(),
            'user_id'       => $userId,
            'type'          => $type,
            'collection_id' => $iid,
            'hold_id'       => 0,
            'num'           => $quantity,
            'done_num'      => 0,
            'amount'        => $unitPrice * $quantity,
            'pay_type'      => 'balance',
            'status'        => 0,
            'createtime'    => $now,
            'updatetime'    => $now,
        ]);
        return $this->toOrder($order->toArray(), false, [], $collection->toArray());
    }

    /** 市场买入共用：锁寄售单 + 生成待支付订单（事务） */
    private function lockAndCreate(int $userId, array $sellIds, int $type): array
    {
        try {
            Db::startTrans();

            // 行锁复核在售状态，防同一寄售单被并发下单
            $sells = MarketOrderModel::where('id', 'in', $sellIds)
                ->where('status', MarketService::SELL_ON)
                ->lock(true)
                ->select()
                ->toArray();
            if (count($sells) !== count($sellIds)) {
                throw CException::biz('手慢了，部分藏品已被买走');
            }
            foreach ($sells as $s) {
                if ((int) $s['seller_id'] === $userId) {
                    throw CException::biz('不能购买自己的寄售藏品');
                }
            }

            $amount = array_sum(array_column($sells, 'price'));
            $now    = time();
            $order  = OrderModel::create([
                'order_no'      => $this->genOrderNo(),
                'user_id'       => $userId,
                'type'          => $type,
                'collection_id' => (int) $sells[0]['collection_id'],
                'hold_id'       => 0,
                'num'           => count($sells),
                'done_num'      => 0,
                'amount'        => $amount,
                'pay_type'      => 'balance',
                'status'        => 0,
                'createtime'    => $now,
                'updatetime'    => $now,
            ]);

            // 锁定寄售单
            MarketOrderModel::where('id', 'in', array_column($sells, 'id'))->update([
                'status'     => MarketService::SELL_LOCK,
                'order_id'   => (int) $order->id,
                'updatetime' => $now,
            ]);

            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            if ($e instanceof CException) {
                throw $e;
            }
            throw CException::biz($e->getMessage());
        }

        $collection = CollectionModel::where('id', (int) $sells[0]['collection_id'])->find();
        return $this->toOrder($order->toArray(), false, [], $collection ? $collection->toArray() : []);
    }

    /**
     * 余额支付（支付成功即发货）
     *
     * @throws CException 订单不可支付 / 余额不足 / 库存不足
     */
    public function payByBalance(int $orderId, int $userId): array
    {
        $committed = false;
        try {
            Db::startTrans();
            // 订单行锁，防并发重复支付
            $order = OrderModel::where('id', $orderId)->where('user_id', $userId)->lock(true)->find();
            if (!$order) {
                throw CException::notFound('订单不存在');
            }
            if ((int) $order->status === 1) {
                // 幂等：已支付直接返回
                Db::commit();
                $committed = true;
                $col       = CollectionModel::where('id', (int) $order->collection_id)->find();
                return $this->toOrder(
                    $order->toArray(),
                    true,
                    $this->orderItems((int) $order->id),
                    $col ? $col->toArray() : []
                );
            }
            if ((int) $order->status !== 0) {
                throw CException::biz('订单已关闭');
            }
            if (time() - (int) $order->createtime > self::PAY_WINDOW) {
                // 超时自动取消（该写入需落库，先提交再抛业务异常）
                Db::name('order')->where('id', $order->id)->update(['status' => 2, 'updatetime' => time()]);
                Db::commit();
                $committed = true;
                throw CException::biz('订单已超时，请重新下单');
            }

            $orderArr = $order->toArray();

            /* ---- 二级市场订单：按类型分派结算 ---- */
            if (in_array((int) $order->type, [2, 3], true)) {
                // 市场买入/批量买入：扣款 → 交割（过户 + 卖方收款，扣 6% 服务费）
                $this->wallet->consume($userId, (int) $order->amount, (string) $order->order_no, '市场购买');
                Db::name('order')->where('id', $order->id)->update([
                    'status'     => 1,
                    'pay_type'   => 'balance',
                    'paytime'    => time(),
                    'updatetime' => time(),
                ]);
                $newIds = $this->market->settlePurchase($orderArr);
                Db::name('user')->where('id', $userId)->inc('order_count')->update();
                Db::commit();
                $committed = true;

                $order->status  = 1;
                $order->paytime = time();
                $col            = CollectionModel::where('id', (int) $order->collection_id)->find();
                return $this->toOrder($order->toArray(), true, $this->orderItems((int) $order->id, $newIds), $col ? $col->toArray() : []);
            }
            if (in_array((int) $order->type, [4, 5], true)) {
                // 求购/委托：余额转入托管，订单进入挂出中
                $this->wallet->escrow($userId, (int) $order->amount, (string) $order->order_no, '求购/委托托管');
                Db::name('order')->where('id', $order->id)->update([
                    'status'     => 1,
                    'pay_type'   => 'balance',
                    'paytime'    => time(),
                    'updatetime' => time(),
                ]);
                Db::name('user')->where('id', $userId)->inc('order_count')->update();
                Db::commit();
                $committed = true;

                $order->status  = 1;
                $order->paytime = time();
                return $this->toOrder($order->toArray());
            }

            $collection = CollectionModel::where('id', (int) $order->collection_id)->find();
            if (!$collection) {
                throw CException::notFound('藏品不存在');
            }
            // 支付前复核购买资格与库存
            (new CollectionService())->ensureBuyable($collection->toArray(), (int) $order->num, $userId);

            // 1. 扣余额（WalletService 内部有行锁与流水）
            $this->wallet->consume($userId, (int) $order->amount, (string) $order->order_no, '一级市场购买');

            // 2. 订单置已支付
            Db::name('order')->where('id', $order->id)->update([
                'status'     => 1,
                'pay_type'   => 'balance',
                'paytime'    => time(),
                'updatetime' => time(),
            ]);

            // 3. 发货（原子扣库存 + 生成持仓）
            $holdIds = $this->hold->issue($userId, (int) $order->collection_id, (int) $order->num, 1, (int) $order->id);

            // 4. 会员冗余计数：订单数 +1
            Db::name('user')->where('id', $userId)->inc('order_count')->update();

            Db::commit();
            $committed = true;

            $order->status  = 1;
            $order->paytime = time();

            return $this->toOrder($order->toArray(), true, $this->orderItems((int) $order->id, $holdIds), $collection->toArray());
        } catch (Throwable $e) {
            if (!$committed) {
                Db::rollback();
            }
            if ($e instanceof CException) {
                throw $e;
            }
            throw CException::biz($e->getMessage());
        }
    }

    /**
     * 取消待支付订单（仅本人待支付可取消）
     */
    public function cancel(int $orderId, int $userId): void
    {
        $order = OrderModel::where('id', $orderId)->where('user_id', $userId)->find();
        if (!$order) {
            throw CException::notFound('订单不存在');
        }
        if ((int) $order->status !== 0) {
            throw CException::biz('仅待支付订单可取消');
        }

        Db::startTrans();
        try {
            $order->save(['status' => 2, 'updatetime' => time()]);
            // 解锁本单占用的寄售单（市场买入下单即锁）
            MarketOrderModel::where('order_id', $orderId)
                ->where('status', MarketService::SELL_LOCK)
                ->update([
                    'status'     => MarketService::SELL_ON,
                    'order_id'   => 0,
                    'updatetime' => time(),
                ]);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            throw CException::biz($e->getMessage());
        }
    }

    /** 我的订单列表（源站 GET /order 契约：裸数组，items 恒为 []） */
    public function myList(int $userId, string $types = '', string $statuses = '', int $pageNo = 1, int $pageSize = 20): array
    {
        $query = OrderModel::where('user_id', $userId);

        // 源站 type（10常规/20市场/21批量/22求购/23委托）→ 本库 type
        $typeList = array_filter(array_map('intval', explode(',', $types)));
        if ($typeList) {
            $typeMap  = [10 => 1, 20 => 2, 21 => 3, 22 => 4, 23 => 5];
            $query->whereIn('type', array_map(fn(int $t) => $typeMap[$t] ?? 0, $typeList));
        }

        // 源站 status（0待付/1进行中/2取消/3完成/4退款）→ 本库 status
        $statusList = array_filter(array_map('intval', explode(',', $statuses)));
        if ($statusList) {
            $statusMap = [0 => 0, 1 => 1, 2 => 2, 3 => 1, 4 => 3];
            $query->whereIn('status', array_map(fn(int $s) => $statusMap[$s] ?? 0, $statusList));
            // 求购/委托的「进行中/已完成」以完成数量区分
            $intentOnly = $typeList && !array_diff($typeList, [22, 23]);
            if ($intentOnly && in_array(1, $statusList, true) && !in_array(3, $statusList, true)) {
                $query->whereRaw('done_num < num');
            }
            if ($intentOnly && in_array(3, $statusList, true)) {
                $query->whereRaw('done_num >= num');
            }
        }

        $rows = $query->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return $this->decorate($rows);
    }

    /**
     * 订单详情（源站 GET /order/{id} 契约：items 填真实明细）
     */
    public function detail(int $orderId, int $userId): array
    {
        $order = OrderModel::where('id', $orderId)->where('user_id', $userId)->find();
        if (!$order) {
            throw CException::notFound('订单不存在');
        }
        $rows = $this->decorate([$order->toArray()]);
        $out  = $rows[0];
        // 详情契约：items 填真实明细（列表恒为 []）
        $out['items'] = $this->orderItems($orderId);
        return $out;
    }

    /**
     * 批量补齐藏品标题/封面（订单行 → 源站对象数组）
     */
    private function decorate(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $colIds = array_values(array_unique(array_filter(array_column($rows, 'collection_id'))));
        $cols   = $colIds ? CollectionModel::where('id', 'in', $colIds)->column('name,cover', 'id') : [];

        return array_map(fn(array $row) => $this->toOrder($row, false, [], $cols[(int) $row['collection_id']] ?? []), $rows);
    }

    /**
     * 订单明细（一级购 source=1 / 市场买入过户新凭证 source=2，均挂在本单 ref_id 下）
     */
    private function orderItems(int $orderId, array $holdIds = []): array
    {
        $query = HoldModel::where('ref_id', $orderId)->whereIn('source', [1, 2]);
        if ($holdIds) {
            $query->where('id', 'in', $holdIds);
        }
        $rows = $query->order('id', 'asc')->select()->toArray();
        if (!$rows) {
            return [];
        }

        $colIds = array_values(array_unique(array_column($rows, 'collection_id')));
        $cols   = CollectionModel::where('id', 'in', $colIds)->column('name', 'id');

        return array_map(fn(array $row) => [
            'id'         => (string) ($row['id'] ?? ''),
            'orderId'    => (string) $orderId,
            'nftId'      => (string) ($row['id'] ?? ''),
            'token'      => (int) ($row['id'] ?? 0),
            'amount'     => 0,
            'createTime' => (string) ((int) ($row['createtime'] ?? 0) * 1000),
            'name'       => (string) ($cols[(int) $row['collection_id']] ?? ''),
            'sn'         => (string) ($row['sn'] ?? ''),
        ], $rows);
    }

    /**
     * 订单对象（对齐源站 Transformer::order 字段）
     */
    public function toOrder(array $row, bool $withItems = false, array $items = [], array $collection = []): array
    {
        $statusMap  = [0 => 0, 1 => 2, 2 => 3, 3 => 4]; // 本库 → 源站
        $typeMap    = [1 => 10, 2 => 20, 3 => 21, 4 => 22, 5 => 23];
        $createtime = (int) ($row['createtime'] ?? 0);
        $paytime    = (int) ($row['paytime'] ?? 0);

        $type    = (int) ($row['type'] ?? 1);
        $num     = (int) ($row['num'] ?? 1);
        $doneNum = (int) ($row['done_num'] ?? 0);

        if ($type === 4 || $type === 5) {
            // 求购/委托：0待付 1挂出中 2已取消，满额 → 3已完成
            $status = ($num > 0 && $doneNum >= $num) ? 3 : ([0 => 0, 1 => 1, 2 => 2][(int) ($row['status'] ?? 0)] ?? 0);
        } else {
            $status = $statusMap[(int) ($row['status'] ?? 0)] ?? 0;
        }

        return [
            'orderId'      => (string) ($row['id'] ?? ''),
            'buyer'        => (string) ($row['user_id'] ?? ''),
            'type'         => $typeMap[$type] ?? 10, // 源站 order_type
            'iid'          => (string) ($row['collection_id'] ?? ''),
            'title'        => (string) ($collection['name'] ?? ''),
            'image'        => (string) ($collection['cover'] ?? ''),
            'imageUrl'     => isset($collection['cover']) && $collection['cover'] !== ''
                ? full_url((string) $collection['cover'], true)
                : '',
            'unitPrice'    => (int) ((int) ($row['amount'] ?? 0) / max(1, $num)),
            'quantity'     => $num,
            'itemQuantity' => ($type === 4 || $type === 5) ? $doneNum : $num,
            'amount'       => (int) ($row['amount'] ?? 0),
            'subject'      => (string) ($collection['name'] ?? ''),
            'outTradeNo'   => (string) ($row['order_no'] ?? ''),
            'payTime'      => $paytime > 0 ? (string) ($paytime * 1000) : null,
            'status'       => $status,
            'payWay'       => 1,
            'isSnapUp'     => false,
            'remark'       => (string) ($row['remark'] ?? ''),
            'createTime'   => (string) ($createtime * 1000),
            // 支付截止 = 下单 + 15 分钟
            'endPayTime'   => (string) (($createtime + self::PAY_WINDOW) * 1000),
            'items'        => $withItems ? $items : [],
        ];
    }

    /** 订单号：N + 日期 + 随机（唯一索引兜底） */
    private function genOrderNo(): string
    {
        return 'N' . date('YmdHis') . substr((string) microtime(), 2, 4) . mt_rand(1000, 9999);
    }
}
