<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;

/**
 * 转赠管理
 */
class Transfer extends Backend
{
    protected array $noNeedPermission = ['peritem', 'toggle', 'revoke'];

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

    /**
     * 单品转赠开关列表：藏品 / 流通量(持有人数) / 寄售开关 / 转赠开关
     */
    public function peritem(): void
    {
        $keyword = trim((string) $this->request->get('keyword/s', ''));
        $limit   = min(200, max(1, $this->request->get('limit/d', 100)));
        $page    = max(1, $this->request->get('page/d', 1));

        $query = Db::name('collection')->field(['id', 'name', 'cover', 'resale', 'transfer_enabled']);
        if ($keyword !== '') {
            $query = $query->where('name', 'like', '%' . str_replace('%', '\%', $keyword) . '%');
        }
        $total = (clone $query)->count();
        $list  = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();

        // 流通量 = 各藏品当前持有人数（去重）
        $ids        = array_column($list, 'id');
        $holderMaps = $ids ? Db::name('hold')
            ->where('collection_id', 'in', $ids)
            ->where('status', 0)
            ->field('collection_id, count(distinct user_id) as holders')
            ->group('collection_id')
            ->select()->toArray() : [];
        $holders = array_column($holderMaps, 'holders', 'collection_id');
        foreach ($list as &$row) {
            $row['holders'] = (int) ($holders[$row['id']] ?? 0);
        }
        unset($row);

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 切换单品转赠开关（与寄售开关互不干扰）
     */
    public function toggle(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id     = $this->request->post('id/d', 0);
        $status = $this->request->post('status/d', 0) ? 1 : 0;
        $col    = Db::name('collection')->where('id', $id)->find();
        if (!$col) {
            $this->error(__('Record not found'));
        }
        Db::name('collection')->where('id', $id)->update(['transfer_enabled' => $status, 'updatetime' => time()]);
        OperLog::record('转赠管理', $status ? '开启转赠' : '关闭转赠', '藏品#' . $id . ' ' . $col['name'], '');
        $this->success($status ? '已开启该藏品的转赠，C 端即时生效' : '已关闭该藏品的转赠，C 端按钮隐藏');
    }

    /**
     * 撤销转赠（后台强制回退）：仅「已完成」可撤销
     * 校验接收方仍持有该藏品（持仓未被再次转赠/寄售/合成/销毁），通过后过户回原转出方
     */
    public function revoke(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id = $this->request->post('id/d', 0);
        $t  = Db::name('transfer')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$t) {
            $this->error(__('Record not found'));
        }
        if ((int) $t['status'] !== 1) {
            $this->error('仅「已完成」的转赠可撤销');
        }
        $hold = Db::name('hold')->where('id', (int) $t['hold_id'])->find();
        if (!$hold) {
            $this->error('接收方持仓已不存在（可能已销毁/合成），不可撤销');
        }
        if ((int) $hold['user_id'] !== (int) $t['to_user_id']) {
            $this->error('该藏品已被再次转赠，不可撤销');
        }
        if ((int) $hold['status'] !== 0) {
            $this->error('该藏品已寄售或处于锁定状态，不可撤销');
        }

        Db::startTrans();
        try {
            Db::name('hold')->where('id', (int) $hold['id'])->update([
                'user_id'   => (int) $t['from_user_id'],
                'updatetime' => time(),
            ]);
            Db::name('transfer')->where('id', $id)->update(['status' => 2, 'updatetime' => time()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('撤销失败：' . $e->getMessage());
        }
        OperLog::record('转赠管理', '撤销转赠', '转赠#' . $t['transfer_no'], '已回退至原转出方');
        $this->success('撤销成功，藏品已回退至原转出方');
    }
}
