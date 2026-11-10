<?php

namespace app\api\controller;

use Throwable;
use think\facade\Db;
use app\common\controller\Frontend;
use app\common\service\NftTrade;

/**
 * C 端藏品直购（一级市场）
 *
 * 接口：
 *   GET  /api/nft_collection/index            发售中列表（免登录）
 *   GET  /api/nft_collection/detail?id=       详情（免登录）
 *   POST /api/nft_collection/order            下单 {collection_id, num}（需 token）
 *   POST /api/nft_collection/pay_mock         模拟支付并铸造持仓 {order_no}（需 token）
 *   POST /api/nft_collection/pay_mock         （复用盲盒引擎骨架：锁单/锁藏品/原子扣销量/发持仓/全事务）
 *
 * 优先购：pre_sale_time ~ sale_time 窗口内，仅优先购资格用户可买（pre_rule_type 1=持有凭证藏品 2=指定用户ID）。
 * 库存口径与管理端一致（NftTrade::poolRemain）。
 */
class NftCollection extends Frontend
{
    protected array $noNeedLogin = ['index', 'detail'];
    protected array $noNeedRight = ['*'];

    /**
     * 发售中列表
     */
    public function index(): void
    {
        $list = Db::name('collection')->where('status', 2)
            ->field('id,name,cover,publisher,price,market_price,stock,sold,sale_end_time,pre_sale_time,pre_rule_type')
            ->order('id', 'desc')
            ->select()->toArray();
        $now = time();
        foreach ($list as &$row) {
            $row['price']        = NftTrade::yuan((int) $row['price']);
            $row['market_price'] = (int) $row['market_price'] > 0 ? NftTrade::yuan((int) $row['market_price']) : '';
            $row['remaining']    = NftTrade::poolRemain($row);
            $row['sale_end_time_txt'] = (int) $row['sale_end_time'] > 0 ? date('Y-m-d H:i', (int) $row['sale_end_time']) : '';
            $row['in_pre_sale']  = ((int) $row['pre_sale_time'] > 0 && $now >= (int) $row['pre_sale_time'] && $now < max((int) $row['sale_time'], 1)) ? 1 : 0;
        }
        unset($row);
        $this->success('', ['list' => $list]);
    }

    /**
     * 详情
     */
    public function detail(): void
    {
        $id  = $this->request->get('id/d', 0);
        $col = Db::name('collection')->where('id', $id)->where('status', 'in', [2, 4])
            ->field('id,name,category_id,author,publisher,cover,price,market_price,stock,sold,detail,resale,status,sale_time,sale_end_time,pre_sale_time,pre_rule_type,pre_limit')
            ->find();
        if (!$col) {
            $this->error('藏品不存在或未在发售');
        }
        $col['price']        = NftTrade::yuan((int) $col['price']);
        $col['market_price'] = (int) $col['market_price'] > 0 ? NftTrade::yuan((int) $col['market_price']) : '';
        $col['remaining']    = NftTrade::poolRemain($col);
        $col['is_on_sale']   = ((int) $col['status'] === 2 && $col['remaining'] > 0 && ((int) $col['sale_end_time'] === 0 || time() < (int) $col['sale_end_time'])) ? 1 : 0;
        $col['sale_time_txt']      = (int) $col['sale_time'] > 0 ? date('Y-m-d H:i', (int) $col['sale_time']) : '';
        $col['sale_end_time_txt']  = (int) $col['sale_end_time'] > 0 ? date('Y-m-d H:i', (int) $col['sale_end_time']) : '';
        $col['pre_sale_time_txt']  = (int) $col['pre_sale_time'] > 0 ? date('Y-m-d H:i', (int) $col['pre_sale_time']) : '';
        unset($col['detail']);
        $this->success('', ['collection' => $col]);
    }

    /**
     * 优先购资格校验：窗口内返回 true 时要求资格
     */
    private function checkPreSale(array $col): void
    {
        $now = time();
        $inPreWindow = (int) $col['pre_sale_time'] > 0 && $now >= (int) $col['pre_sale_time'] && $now < max((int) $col['sale_time'], 1);
        if ((int) $col['pre_rule_type'] === 0 || !$inPreWindow) {
            return; // 不在优先购窗口，任何人都可买
        }
        $uid   = (int) $this->auth->id;
        $value = trim((string) $col['pre_rule_value']);
        $ok    = false;
        if ((int) $col['pre_rule_type'] === 1) {
            // 持有凭证藏品（支持多个，逗号分隔）
            $ids = array_map('intval', array_filter(explode(',', $value)));
            if ($ids) {
                $ok = (bool) Db::name('hold')->where('user_id', $uid)->where('collection_id', 'in', $ids)->where('status', '<>', 3)->count();
            }
        } elseif ((int) $col['pre_rule_type'] === 2) {
            $ids = array_map('intval', array_filter(explode(',', $value)));
            $ok  = in_array($uid, $ids);
        }
        if (!$ok) {
            $this->error('当前为优先购阶段，暂无优先购资格');
        }

        // 优先购限购：窗口内每人最多 pre_limit 份（按已支付订单计）
        $limit = max(1, (int) $col['pre_limit']);
        $bought = (int) Db::name('order')->where('user_id', $uid)->where('type', 1)
            ->where('collection_id', (int) $col['id'])->where('status', 1)->sum('num');
        $num = max(1, $this->request->post('num/d', 1));
        if ($bought + $num > $limit) {
            $this->error('优先购阶段每人限购 ' . $limit . ' 份，您已购 ' . $bought . ' 份');
        }
    }

    /**
     * 下单
     */
    public function order(): void
    {
        $cid  = $this->request->post('collection_id/d', 0);
        $num  = max(1, $this->request->post('num/d', 1));
        if ($num > 99) {
            $this->error('单笔最多购买 99 份');
        }
        $col = Db::name('collection')->where('id', $cid)->where('status', 2)->find();
        if (!$col) {
            $this->error('藏品不存在或未在发售');
        }
        $now = time();
        if ((int) $col['sale_end_time'] > 0 && $now >= (int) $col['sale_end_time']) {
            $this->error('本期发售已结束');
        }
        $this->checkPreSale($col);
        if (NftTrade::poolRemain($col) < $num) {
            $this->error('剩余库存不足（剩 ' . NftTrade::poolRemain($col) . ' 份）');
        }

        $orderNo = 'CL' . date('YmdHis') . $this->auth->id . mt_rand(100, 999);
        while (Db::name('order')->where('order_no', $orderNo)->count() > 0) {
            $orderNo = 'CL' . date('YmdHis') . $this->auth->id . mt_rand(1000, 9999);
        }
        $amount = (int) $col['price'] * $num;
        Db::name('order')->insert([
            'order_no'     => $orderNo,
            'user_id'      => $this->auth->id,
            'type'         => 1, // 一级购
            'collection_id'=> $cid,
            'box_id'       => 0,
            'num'          => $num,
            'amount'       => $amount,
            'status'       => 0,
            'createtime'   => $now,
            'updatetime'   => $now,
        ]);
        $this->success('下单成功，请完成支付', [
            'order_no' => $orderNo,
            'amount'   => NftTrade::yuan($amount),
            'num'      => $num,
            'pay_type' => 'mock',
        ]);
    }

    /**
     * 模拟支付成功回调：支付 + 铸造持仓（同事务）
     */
    public function pay_mock(): void
    {
        $orderNo = $this->request->post('order_no/s', '');
        if ($orderNo === '') {
            $this->error('缺少订单号');
        }

        Db::startTrans();
        try {
            $order = Db::name('order')->where('order_no', $orderNo)->lock(true)->find();
            if (!$order || (int) $order['user_id'] !== (int) $this->auth->id || (int) $order['type'] !== 1) {
                throw new \RuntimeException('订单不存在');
            }
            if ((int) $order['status'] !== 0) {
                throw new \RuntimeException('订单状态不可支付（可能已支付）');
            }
            $cid  = (int) $order['collection_id'];
            $num  = (int) $order['num'];

            $col = Db::name('collection')->where('id', $cid)->lock(true)->find();
            if (!$col || (int) $col['status'] !== 2) {
                throw new \RuntimeException('藏品已停止发售，支付未完成');
            }
            $occupy = (int) $col['stock'] - NftTrade::poolRemain($col) - (int) $col['sold'];
            $hit = Db::name('collection')->where('id', $cid)
                ->whereRaw('sold + ' . $num . ' <= stock - ' . max(0, $occupy))
                ->update(['sold' => Db::raw('sold + ' . $num), 'updatetime' => time()]);
            if (!$hit) {
                throw new \RuntimeException('库存不足，支付未完成');
            }

            $now     = time();
            $results = [];
            for ($i = 1; $i <= $num; $i++) {
                $seq = $i;
                $sn  = 'C' . $cid . 'O' . $order['id'] . 'N' . $seq;
                while (Db::name('hold')->where('sn', $sn)->count() > 0) {
                    $seq++;
                    $sn = 'C' . $cid . 'O' . $order['id'] . 'N' . $seq;
                }
                $holdId = (int) Db::name('hold')->insertGetId([
                    'sn'            => $sn,
                    'collection_id' => $cid,
                    'user_id'       => (int) $order['user_id'],
                    'source'        => 1, // 一级购
                    'status'        => 0, // 持有
                    'ref_id'        => (int) $order['id'],
                    'mint_time'     => $now,
                    'createtime'    => $now,
                    'updatetime'    => $now,
                ]);
                $results[] = ['seq' => $i, 'hold_id' => $holdId, 'sn' => $sn];
            }

            Db::name('order')->where('id', (int) $order['id'])->update([
                'status' => 1, 'pay_type' => 'mock',
                'pay_no' => 'MOCK' . date('YmdHis') . mt_rand(1000, 9999),
                'paytime' => $now, 'updatetime' => $now,
            ]);
            NftTrade::walletMove((int) $order['user_id'], 'expense', -(int) $order['amount'], $orderNo, '一级购「' . $col['name'] . '」×' . $num . '（外部支付）', false);

            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '支付失败，请重试');
        }

        $this->success('支付成功，购买完成', [
            'order_no' => $orderNo,
            'num'      => $num,
            'collection' => ['id' => $cid, 'name' => $col['name'], 'cover' => $col['cover']],
            'holds'    => $results,
        ]);
    }

    /**
     * 我的一级购订单
     */
    public function orders(): void
    {
        $limit = min(100, max(1, $this->request->get('limit/d', 20)));
        $page  = max(1, $this->request->get('page/d', 1));
        $query = Db::name('order')->alias('o')
            ->leftJoin('collection c', 'c.id = o.collection_id')
            ->where('o.user_id', $this->auth->id)->where('o.type', 1);
        $total = $query->count();
        $list  = $query->field('o.id,o.order_no,o.collection_id,c.name as collection_name,c.cover,o.num,o.amount,o.status,o.paytime,o.createtime')
            ->order('o.id', 'desc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$row) {
            $row['amount']       = NftTrade::yuan((int) $row['amount']);
            $row['status_txt']   = ['0' => '待支付', '1' => '已支付', '2' => '已取消', '3' => '已退款'][(string) $row['status']] ?? '未知';
        }
        unset($row);
        $this->success('', ['list' => $list, 'total' => $total]);
    }
}
