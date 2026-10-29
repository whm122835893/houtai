<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\Raffle;
use app\admin\model\nft\OperLog;

/**
 * 抽签发售
 *
 * 流程：报名期(apply_start ~ apply_end) → 截止后执行抽签(draw) → 中签者购买期(至 buy_deadline) → 结束
 * 中签名额 = floor(发售数量 / 每人限购)；报名数不足名额时全部中签
 */
class Raffle extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new Raffle();
    }

    /**
     * 活动分页（附藏品名、实时阶段）
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            // 按藏品名关联筛选
            $cids = Db::name('nft_collection')
                ->where('name', 'like', '%' . str_replace('%', '\%', $keyword) . '%')
                ->column('id');
            $where[] = ['collection_id', 'in', $cids ?: [0]];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        // 拼装藏品名 + 实时阶段
        $cids = array_values(array_unique(array_column($list, 'collection_id')));
        $cols = $cids ? Db::name('nft_collection')->where('id', 'in', $cids)->column('name', 'id') : [];
        $now  = time();
        foreach ($list as &$row) {
            $row['collection_name'] = $cols[$row['collection_id']] ?? ('藏品#' . $row['collection_id']);
            $row['phase']           = $this->phaseOf($row, $now);
            $row['quota']           = $row['per_limit'] > 0 ? (int) floor($row['total'] / $row['per_limit']) : 0;
        }
        unset($row);

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 实时阶段：0未开始 1报名中 2待抽签 3购买期(已抽签) 4已结束
     */
    private function phaseOf(array $row, int $now): int
    {
        if ((int) $row['status'] === 3) {
            return $row['buy_deadline'] && $now > $row['buy_deadline'] ? 4 : 3;
        }
        if ($row['apply_start'] && $now < $row['apply_start']) {
            return 0;
        }
        if ($row['apply_end'] && $now <= $row['apply_end']) {
            return 1;
        }
        return 2;
    }

    /**
     * 新增/编辑活动
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id   = $this->request->param('id/d', 0);
        $data = $this->request->only(['collection_id', 'total', 'per_limit', 'apply_start', 'apply_end', 'buy_deadline'], 'post');

        $cid = (int) ($data['collection_id'] ?? 0);
        if ($cid <= 0 || !Db::name('nft_collection')->where('id', $cid)->find()) {
            $this->error('请选择发售藏品');
        }
        if ((int) ($data['total'] ?? 0) <= 0) {
            $this->error('发售数量必须大于 0');
        }
        if ((int) ($data['per_limit'] ?? 0) <= 0) {
            $this->error('每人限购必须大于 0');
        }
        $applyStart = !empty($data['apply_start']) ? strtotime((string) $data['apply_start']) : 0;
        $applyEnd   = !empty($data['apply_end']) ? strtotime((string) $data['apply_end']) : 0;
        $buyEnd     = !empty($data['buy_deadline']) ? strtotime((string) $data['buy_deadline']) : 0;
        if (!$applyStart || !$applyEnd || !$buyEnd) {
            $this->error('请填写完整的报名与购买时间');
        }
        if ($applyStart >= $applyEnd) {
            $this->error('报名开始时间必须早于报名截止时间');
        }
        if ($applyEnd >= $buyEnd) {
            $this->error('报名截止时间必须早于购买截止时间');
        }

        $save = [
            'collection_id' => $cid,
            'total'         => (int) $data['total'],
            'per_limit'     => (int) $data['per_limit'],
            'apply_start'   => $applyStart,
            'apply_end'     => $applyEnd,
            'buy_deadline'  => $buyEnd,
        ];
        // 初始状态：按当前时间判定
        $now = time();
        $save['status'] = $now < $applyStart ? 0 : ($now <= $applyEnd ? 1 : 2);

        if ($id) {
            $row = $this->model->find($id);
            if (!$row) {
                $this->error(__('Record not found'));
            }
            if ((int) $row->status >= 3) {
                $this->error('已抽签的活动不可修改');
            }
            $row->save($save);
            OperLog::record('抽签发售', '编辑', '活动#' . $id, $save['total'] . ' 份');
        } else {
            $this->model->save($save);
            OperLog::record('抽签发售', '添加', '活动#' . $this->model->id, $save['total'] . ' 份');
        }
        $this->success();
    }

    /**
     * 删除（级联删除报名记录）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        Db::startTrans();
        try {
            $count = $this->model->where('id', 'in', $ids)->delete();
            Db::name('nft_raffle_apply')->where('raffle_id', 'in', $ids)->delete();
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('删除失败：' . $e->getMessage());
        }
        OperLog::record('抽签发售', '删除', '活动#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }

    /**
     * 执行抽签：随机中签（事务）
     * 中签名额 = floor(发售数量 / 每人限购)；报名数 <= 名额时全部中签
     */
    public function draw(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id   = $this->request->param('id/d', 0);
        $row  = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ((int) $row->status >= 3) {
            $this->error('该活动已完成抽签');
        }
        $now = time();
        if ($row->apply_end && $now < (int) $row->apply_end) {
            $this->error('报名尚未截止，不能抽签');
        }

        $quota = $row->per_limit > 0 ? (int) floor($row->total / $row->per_limit) : 0;
        if ($quota <= 0) {
            $this->error('中签名额计算异常，请检查发售数量与每人限购');
        }

        Db::startTrans();
        try {
            $applyIds = Db::name('nft_raffle_apply')
                ->where('raffle_id', $id)
                ->where('status', 0)
                ->column('id');
            if (!$applyIds) {
                $this->error('暂无报名记录，无法抽签');
            }

            // 随机打乱，取前 N 个为中签
            shuffle($applyIds);
            $winIds  = array_slice($applyIds, 0, min($quota, count($applyIds)));
            $loseIds = array_diff($applyIds, $winIds);

            if ($winIds) {
                Db::name('nft_raffle_apply')
                    ->where('id', 'in', $winIds)
                    ->update(['status' => 1, 'win_num' => $row->per_limit]);
            }
            if ($loseIds) {
                Db::name('nft_raffle_apply')
                    ->where('id', 'in', array_values($loseIds))
                    ->update(['status' => 2]);
            }
            $row->save(['status' => 3, 'win_count' => count($winIds), 'apply_count' => count($applyIds)]);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('抽签失败：' . $e->getMessage());
        }
        OperLog::record('抽签发售', '执行抽签', '活动#' . $id, '报名 ' . count($applyIds) . ' 人，中签 ' . count($winIds) . ' 人');
        $this->success('抽签完成', ['apply_count' => count($applyIds), 'win_count' => count($winIds), 'quota' => $quota]);
    }

    /**
     * 报名/中签记录分页
     */
    public function applys(): void
    {
        $raffleId = $this->request->get('raffle_id/d', 0);
        $status   = $this->request->get('status/d', -1);
        $keyword  = $this->request->get('keyword/s', '');
        $limit    = $this->request->get('limit/d', 10);
        $page     = $this->request->get('page/d', 1);

        $where = [];
        if ($raffleId > 0) {
            $where[] = ['a.raffle_id', '=', $raffleId];
        }
        if ($status >= 0) {
            $where[] = ['a.status', '=', $status];
        }
        if ($keyword !== '') {
            $userIds = Db::name('nft_user')
                ->where('id', $keyword)
                ->whereOr('nickname', 'like', '%' . str_replace('%', '\%', $keyword) . '%')
                ->column('id');
            $where[] = ['a.user_id', 'in', $userIds ?: [0]];
        }

        $total = Db::name('nft_raffle_apply')->alias('a')->where($where)->count();
        $list  = Db::name('nft_raffle_apply')->alias('a')->where($where)
            ->order('a.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $userIds = array_values(array_unique(array_column($list, 'user_id')));
        $users   = $userIds ? Db::name('nft_user')->where('id', 'in', $userIds)->column('nickname', 'id') : [];
        foreach ($list as &$row) {
            $row['nickname'] = $users[$row['user_id']] ?? ('用户#' . $row['user_id']);
        }
        unset($row);

        $this->success('', ['list' => $list, 'total' => $total]);
    }
}
