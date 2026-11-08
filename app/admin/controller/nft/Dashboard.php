<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;

/**
 * 数据仪表盘（只读统计，无需模型）
 */
class Dashboard extends Backend
{
    /**
     * 仪表盘汇总数据
     */
    public function index(): void
    {
        try {
            $todayStart = strtotime(date('Y-m-d'));

            // 用户统计
            $userTotal = Db::name('user')->count();
            $userToday = Db::name('user')->where('createtime', '>=', $todayStart)->count();

            // 藏品统计（status=2 发售中即在售）
            $collectionTotal   = Db::name('collection')->count();
            $collectionOnsale  = Db::name('collection')->where('status', 2)->count();

            // NFT 持仓统计（status=1 寄售中）
            $holdTotal   = Db::name('hold')->count();
            $holdSelling = Db::name('hold')->where('status', 1)->count();

            // 订单统计（已支付订单金额累计为成交额，单位分）
            $orderTotal = Db::name('order')->count();
            $orderToday = Db::name('order')->where('createtime', '>=', $todayStart)->count();
            $gmvTotal   = (int) Db::name('order')->where('status', 1)->sum('amount');

            // 近 7 日订单趋势（按 createtime 时间戳逐日统计）
            $trend = [];
            for ($i = 6; $i >= 0; $i--) {
                $start = strtotime(date('Y-m-d', strtotime("-{$i} day")));
                $end   = $start + 86400;
                $row   = Db::name('order')
                    ->where('createtime', '>=', $start)
                    ->where('createtime', '<', $end)
                    ->field('COUNT(*) AS count, IFNULL(SUM(amount), 0) AS amount')
                    ->find();
                $trend[] = [
                    'date'   => date('m-d', $start),
                    'count'  => (int) ($row['count'] ?? 0),
                    'amount' => (int) ($row['amount'] ?? 0),
                ];
            }

            // 最新 8 条订单（两次查询后手动拼装用户昵称与藏品名称，避免复杂 JOIN）
            $orders = Db::name('order')->order('id', 'desc')->limit(8)->select()->toArray();
            $userIds = array_values(array_unique(array_column($orders, 'user_id')));
            $colIds  = array_values(array_unique(array_column($orders, 'collection_id')));
            $users   = $userIds ? Db::name('user')->whereIn('id', $userIds)->column('nickname', 'id') : [];
            $cols    = $colIds ? Db::name('collection')->whereIn('id', $colIds)->column('name', 'id') : [];
            foreach ($orders as &$order) {
                $order['nickname']        = $users[$order['user_id']] ?? '';
                $order['collection_name'] = $cols[$order['collection_id']] ?? '';
            }
            unset($order);

            $this->success('', [
                'user_total'       => $userTotal,
                'user_today'       => $userToday,
                'collection_total' => $collectionTotal,
                'collection_onsale'=> $collectionOnsale,
                'hold_total'       => $holdTotal,
                'hold_selling'     => $holdSelling,
                'order_total'      => $orderTotal,
                'order_today'      => $orderToday,
                'gmv_total'        => $gmvTotal,
                'trend'            => $trend,
                'latest_orders'    => $orders,
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof \think\exception\HttpResponseException) throw $e; // success()/error() 靠该异常短路返回，不能吞
            $this->error($e->getMessage());
        }
    }
}
