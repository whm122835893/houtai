<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;

/**
 * 转赠管理
 */
class Transfer extends Backend
{
    /**
     * 转赠记录列表（查询/明细）
     * 筛选：转赠单号 / 藏品名称 / 接收手机号 / 状态 + 分页
     * 关联：user 昵称（转出方/接收方）、collection 名称
     */
    public function index(): void
    {
        $transferNo = trim((string) $this->request->get('transfer_no/s', ''));
        $collection = trim((string) $this->request->get('collection/s', ''));
        $mobile     = trim((string) $this->request->get('mobile/s', ''));
        $status     = $this->request->get('status/d', -1);
        $limit      = min(100, max(1, $this->request->get('limit/d', 10)));
        $page       = max(1, $this->request->get('page/d', 1));

        // 状态统计（不受筛选影响）：转赠总数 / 已完成 / 待接收 / 已取消
        $statQuery = Db::name('transfer')->where('is_deleted', 0);
        $stat = [
            'total'    => (clone $statQuery)->count(),
            'done'     => (clone $statQuery)->where('status', 1)->count(),
            'pending'  => (clone $statQuery)->where('status', 0)->count(),
            'canceled' => (clone $statQuery)->where('status', 2)->count(),
        ];

        $where = [['t.is_deleted', '=', 0]];
        if ($transferNo !== '') {
            $where[] = ['t.transfer_no', 'like', '%' . str_replace('%', '\%', $transferNo) . '%'];
        }
        if ($mobile !== '') {
            $where[] = ['t.to_mobile', 'like', '%' . str_replace('%', '\%', $mobile) . '%'];
        }
        if ($status >= 0) {
            $where[] = ['t.status', '=', $status];
        }
        if ($collection !== '') {
            $cids = Db::name('collection')
                ->where('name', 'like', '%' . str_replace('%', '\%', $collection) . '%')
                ->column('id');
            if (!$cids) {
                $this->success('', ['list' => [], 'total' => 0, 'stat' => $stat]);
            }
            $where[] = ['t.collection_id', 'in', $cids];
        }

        $query = Db::name('transfer')->alias('t')
            ->leftJoin('user fu', 'fu.id = t.from_user_id')
            ->leftJoin('user tu', 'tu.id = t.to_user_id')
            ->leftJoin('collection c', 'c.id = t.collection_id')
            ->where($where);
        $total = (clone $query)->count();
        $list  = $query
            ->field([
                't.id',
                't.transfer_no',
                't.status',
                't.to_mobile',
                't.createtime',
                't.done_time',
                'c.name as collection_name',
                'fu.nickname as from_user',
                'IFNULL(tu.nickname, t.to_mobile) as to_user',
            ])
            ->order('t.id', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('', ['list' => $list, 'total' => $total, 'stat' => $stat]);
    }
}
