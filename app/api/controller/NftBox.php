<?php

namespace app\api\controller;

use Throwable;
use think\facade\Db;
use app\common\controller\Frontend;

/**
 * C 端盲盒
 *
 * 接口：
 *   GET  /api/nftbox/index                 在售盲盒列表（免登录）
 *   GET  /api/nftbox/detail?id=            盲盒详情 + 奖池概率公示（免登录）
 *   POST /api/nftbox/order                 下单 {box_id, num}（需 token）
 *   POST /api/nftbox/payMock               模拟支付并立即开盒 {order_no}（需 token）
 *   GET  /api/nftbox/records               我的开盒记录（需 token）
 *
 * 金额单位：库内为「分」，对外统一输出「元」（两位小数字符串）。
 * 开盒规则：支付成功即自动开盒；按万分比概率抽取，奖池原子扣减（sent<quantity 才放行），
 * 全部奖品发完则降级空盒并记录；下单/扣库存/发持仓/写记录同事务。
 */
class NftBox extends Frontend
{
    protected array $noNeedLogin = ['index', 'detail'];
    protected array $noNeedRight = ['*'];

    /**
     * 分 → 元字符串
     */
    private function yuan(int $fen): string
    {
        return bcdiv((string) $fen, '100', 2);
    }

    /**
     * 在售盲盒列表
     */
    public function index(): void
    {
        $list = Db::name('blindbox')->where('deletetime', 0)
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->field('id,name,cover,description,price,total_supply,sold_count,limit_per_user')
            ->order('id', 'desc')
            ->select()
            ->toArray();
        foreach ($list as &$row) {
            $row['price']      = $this->yuan((int) $row['price']);
            $row['remaining']  = max(0, (int) $row['total_supply'] - (int) $row['sold_count']);
            $row['is_on_sale'] = $row['remaining'] > 0;
        }
        unset($row);
        $this->success('', ['list' => $list]);
    }

    /**
     * 盲盒详情（含奖池概率公示与剩余量）
     */
    public function detail(): void
    {
        $id  = $this->request->get('id/d', 0);
        $box = Db::name('blindbox')->where('deletetime', 0)->where('id', $id)->where('status', 1)->where('is_deleted', 0)
            ->field('id,name,cover,description,rule_text,price,total_supply,sold_count,limit_per_user')
            ->find();
        if (!$box) {
            $this->error('盲盒不存在或已下架');
        }
        $box['price']     = $this->yuan((int) $box['price']);
        $box['remaining'] = max(0, (int) $box['total_supply'] - (int) $box['sold_count']);
        $box['is_on_sale'] = $box['remaining'] > 0;

        $prizes = Db::name('blindbox_prize')->where('box_id', $id)
            ->field('id,collection_id,name,probability,quantity,sent')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        foreach ($prizes as &$p) {
            $p['prob_pct']   = rtrim(rtrim(sprintf('%.4f', $p['probability'] / 10000), '0'), '.');
            $p['remaining']  = max(0, (int) $p['quantity'] - (int) $p['sent']);
        }
        unset($p);

        $this->success('', ['box' => $box, 'prizes' => $prizes]);
    }

    /**
     * 下单（创建待支付订单）
     */
    public function order(): void
    {
        $boxId = $this->request->post('box_id/d', 0);
        $num   = max(1, $this->request->post('num/d', 1));
        if ($num > 99) {
            $this->error('单笔最多购买 99 份');
        }

        $box = Db::name('blindbox')->where('deletetime', 0)->where('id', $boxId)->where('status', 1)->where('is_deleted', 0)->find();
        if (!$box) {
            $this->error('盲盒不存在或已下架');
        }
        if ((int) $box['sold_count'] + $num > (int) $box['total_supply']) {
            $this->error('剩余份数不足（剩 ' . max(0, (int) $box['total_supply'] - (int) $box['sold_count']) . ' 份）');
        }

        // 限购：按用户在此盲盒已成功开出的份数计
        $limit = (int) $box['limit_per_user'];
        if ($limit > 0) {
            $bought = (int) Db::name('blindbox_record')->where('box_id', $boxId)->where('user_id', $this->auth->id)->count();
            if ($bought + $num > $limit) {
                $this->error('已超出限购：此盲盒每人限购 ' . $limit . ' 份，您已购 ' . $bought . ' 份');
            }
        }

        $orderNo = 'BB' . date('YmdHis') . $this->auth->id . mt_rand(100, 999);
        while (Db::name('order')->where('order_no', $orderNo)->count() > 0) {
            $orderNo = 'BB' . date('YmdHis') . $this->auth->id . mt_rand(1000, 9999);
        }

        $now = time();
        Db::name('order')->insert([
            'order_no'     => $orderNo,
            'user_id'      => $this->auth->id,
            'type'         => 1, // 一级购
            'collection_id'=> 0, // 盲盒订单，奖品以 blindbox_record.order_id 关联
            'box_id'       => $boxId,
            'num'          => $num,
            'amount'       => (int) $box['price'] * $num,
            'status'       => 0, // 待支付
            'createtime'   => $now,
            'updatetime'   => $now,
        ]);

        $this->success('下单成功，请完成支付', [
            'order_no' => $orderNo,
            'amount'   => $this->yuan((int) $box['price'] * $num),
            'num'      => $num,
            'pay_type' => 'mock', // 上线真实支付时替换为 wxpay 等
        ]);
    }

    /**
     * 模拟支付成功回调：支付 + 立即开盒（同事务）
     */
    public function pay_mock(): void
    {
        $orderNo = $this->request->post('order_no/s', '');
        if ($orderNo === '') {
            $this->error('缺少订单号');
        }

        Db::startTrans();
        try {
            // 锁单防重复支付（幂等）
            $order = Db::name('order')->where('order_no', $orderNo)->lock(true)->find();
            if (!$order || (int) $order['user_id'] !== (int) $this->auth->id) {
                throw new \RuntimeException('订单不存在');
            }
            if ((int) $order['status'] !== 0) {
                throw new \RuntimeException('订单状态不可支付（可能已支付）');
            }

            // 锁盒 + 原子扣销量（超卖防线第一道）
            $boxId = (int) ($order['box_id'] ?? 0);
            $num   = (int) $order['num'];
            if ($boxId <= 0) {
                throw new \RuntimeException('订单缺少盲盒信息');
            }
            $box = Db::name('blindbox')->where('deletetime', 0)->where('id', $boxId)->where('is_deleted', 0)->lock(true)->find();
            if (!$box) {
                throw new \RuntimeException('盲盒不存在');
            }
            $hit = Db::name('blindbox')->where('deletetime', 0)->where('id', $boxId)
                ->whereRaw('sold_count + ' . $num . ' <= total_supply')
                ->update(['sold_count' => Db::raw('sold_count + ' . $num), 'updatetime' => time()]);
            if (!$hit) {
                throw new \RuntimeException('盲盒剩余份数不足，支付未完成');
            }

            // 开盒：逐份抽奖
            $results = $this->drawAll($boxId, (int) $order['user_id'], (int) $order['id'], $num);

            // 订单置为已支付
            Db::name('order')->where('id', (int) $order['id'])->update([
                'status'     => 1,
                'pay_type'   => 'mock',
                'pay_no'     => 'MOCK' . date('YmdHis') . mt_rand(1000, 9999),
                'paytime'    => time(),
                'updatetime' => time(),
            ]);

            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '开盒失败，请重试');
        }

        $this->success('支付成功，开盒完成', [
            'order_no' => $orderNo,
            'num'      => (int) $order['num'],
            'results'  => $results,
        ]);
    }

    /**
     * 批量开盒（在事务内调用）：概率抽取 + 奖池原子扣减 + 发持仓 + 写记录
     */
    private function drawAll(int $boxId, int $userId, int $orderId, int $num): array
    {
        $now     = time();
        $results = [];
        $exhaust = []; // 本轮已确认发完的奖池行

        $prizes = Db::name('blindbox_prize')->where('box_id', $boxId)->where('status', 1)
            ->field('id,collection_id,name,probability,quantity,sent')
            ->order('id', 'asc')
            ->select()->toArray();

        for ($i = 1; $i <= $num; $i++) {
            $hit      = null;

            // 最多 5 轮：命中奖品已发完则从剩余池重抽
            for ($try = 0; $try < 5; $try++) {
                $pool = [];
                foreach ($prizes as $p) {
                    if (in_array($p['id'], $exhaust)) {
                        continue;
                    }
                    if ((int) $p['sent'] >= (int) $p['quantity']) {
                        $exhaust[] = $p['id'];
                        continue;
                    }
                    $pool[] = $p;
                }
                if (!$pool) {
                    break;
                }
                // 按概率重加权随机
                $total = array_sum(array_column($pool, 'probability'));
                $rand  = mt_rand(1, max(1, (int) $total));
                $acc   = 0;
                foreach ($pool as $p) {
                    $acc += (int) $p['probability'];
                    if ($rand <= $acc) {
                        $hit = $p;
                        break;
                    }
                }
                if (!$hit) {
                    $hit = $pool[count($pool) - 1];
                }
                // 原子扣减：sent<quantity 才放行（并发防线）
                $ok = Db::name('blindbox_prize')
                    ->where('id', $hit['id'])
                    ->whereRaw('sent < quantity')
                    ->update(['sent' => Db::raw('sent + 1'), 'updatetime' => $now]);
                if ($ok) {
                    $hit['sent'] = (int) $hit['sent'] + 1; // 本地同步，保证同请求内多份连抽不超发
                    break;
                }
                $hit = null; // 被并发抢走，重抽
            }

            $prizeId      = 0;
            $collectionId = 0;
            $prizeName    = '空盒';
            $holdId       = 0;

            if ($hit) {
                $prizeId      = (int) $hit['id'];
                $collectionId = (int) $hit['collection_id'];
                $prizeName    = (string) $hit['name'];

                // 铸造持仓（SN 唯一）
                $seq = 1;
                $sn  = 'B' . $boxId . 'O' . $orderId . 'N' . $seq;
                while (Db::name('hold')->where('sn', $sn)->count() > 0) {
                    $seq++;
                    $sn = 'B' . $boxId . 'O' . $orderId . 'N' . $seq;
                }
                $holdId = (int) Db::name('hold')->insertGetId([
                    'sn'            => $sn,
                    'collection_id' => $collectionId,
                    'user_id'       => $userId,
                    'source'        => 1, // 一级购
                    'status'        => 0, // 持有
                    'ref_id'        => $orderId,
                    'mint_time'     => $now,
                    'createtime'    => $now,
                    'updatetime'    => $now,
                ]);
            }

            Db::name('blindbox_record')->insert([
                'box_id'        => $boxId,
                'user_id'       => $userId,
                'order_id'      => $orderId,
                'prize_id'      => $prizeId,
                'collection_id' => $collectionId,
                'prize_name'    => $prizeName,
                'hold_id'       => $holdId,
                'createtime'    => $now,
            ]);

            $results[] = [
                'seq'           => $i,
                'is_empty'      => $prizeId === 0,
                'prize_name'    => $prizeName,
                'collection_id' => $collectionId,
                'hold_id'       => $holdId,
            ];
        }
        return $results;
    }

    /**
     * 我的开盒记录
     */
    public function records(): void
    {
        $limit = min(100, max(1, $this->request->get('limit/d', 20)));
        $page  = max(1, $this->request->get('page/d', 1));

        $query = Db::name('blindbox_record')->alias('r')
            ->leftJoin('blindbox b', 'b.id = r.box_id')
            ->leftJoin('collection c', 'c.id = r.collection_id')
            ->leftJoin('order o', 'o.id = r.order_id')
            ->where('r.user_id', $this->auth->id);

        $total = $query->count();
        $list  = $query
            ->field('r.id,r.box_id,b.name as box_name,r.prize_name,r.collection_id,c.name as collection_name,c.cover,r.hold_id,o.order_no,r.createtime')
            ->order('r.id', 'desc')
            ->page($page, $limit)
            ->select();
        foreach ($list as &$row) {
            $row['is_empty'] = ((int) $row['collection_id'] === 0) ? 1 : 0;
        }
        unset($row);
        $this->success('', ['list' => $list, 'total' => $total]);
    }
}
