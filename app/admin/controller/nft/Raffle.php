<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\Raffle as RaffleModel;
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

    protected array $noNeedPermission = ['codes', 'grantcode', 'voidcode'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new RaffleModel();
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

$where[] = ['deletetime', '=', 0];
        $where = [];
        if ($keyword !== '') {
            // 按藏品名关联筛选
            $cids = Db::name('collection')
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
        $cols = $cids ? Db::name('collection')->where('id', 'in', $cids)->column('name', 'id') : [];
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
        if ($cid <= 0 || !Db::name('collection')->where('id', $cid)->find()) {
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
            $count = $this->model->where('id', 'in', $ids)->update(['deletetime' => time(), 'status' => 0]);
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
            $applyIds = Db::name('raffle_apply')
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
                Db::name('raffle_apply')
                    ->where('id', 'in', $winIds)
                    ->update(['status' => 1, 'win_num' => $row->per_limit]);
            }
            if ($loseIds) {
                Db::name('raffle_apply')
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
            $userIds = Db::name('user')
                ->where('id', $keyword)
                ->whereOr('nickname', 'like', '%' . str_replace('%', '\%', $keyword) . '%')
                ->column('id');
            $where[] = ['a.user_id', 'in', $userIds ?: [0]];
        }

        $total = Db::name('raffle_apply')->alias('a')->where($where)->count();
        $list  = Db::name('raffle_apply')->alias('a')->where($where)
            ->order('a.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $userIds = array_values(array_unique(array_column($list, 'user_id')));
        $users   = $userIds ? Db::name('user')->where('id', 'in', $userIds)->column('nickname', 'id') : [];
        foreach ($list as &$row) {
            $row['nickname'] = $users[$row['user_id']] ?? ('用户#' . $row['user_id']);
        }
        unset($row);

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 抽签码池：统计 + 明细分页（码/用户/来源/状态/备注/邀请人/发放时间）
     */
    public function codes(): void
    {
        $raffleId = $this->request->get('raffle_id/d', 0);
        $keyword  = trim((string) $this->request->get('keyword/s', ''));
        $status   = $this->request->get('status/d', -1);
        $source   = $this->request->get('source/d', -1);
        $limit    = min(200, max(1, $this->request->get('limit/d', 20)));
        $page     = max(1, $this->request->get('page/d', 1));

        $raffle = $this->model->find($raffleId);
        if (!$raffle) {
            $this->error(__('Record not found'));
        }

        // 全量统计（不受筛选影响）
        $statQ = Db::name('raffle_code')->where('raffle_id', $raffleId);
        $stat  = [
            'total'  => (clone $statQ)->count(),
            'join'   => (clone $statQ)->where('source', 1)->count(),
            'invite' => (clone $statQ)->where('source', 2)->count(),
            'manual' => (clone $statQ)->where('source', 3)->count(),
            'valid'  => (clone $statQ)->where('status', 0)->count(),
            'win'    => (clone $statQ)->where('status', 1)->count(),
            'void'   => (clone $statQ)->where('status', 2)->count(),
        ];

        $where = [['c.raffle_id', '=', $raffleId]];
        if ($status >= 0) {
            $where[] = ['c.status', '=', $status];
        }
        if ($source >= 0) {
            $where[] = ['c.source', '=', $source];
        }
        if ($keyword !== '') {
            $where[] = ['c.code|u.nickname|u.mobile', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }

        $query = Db::name('raffle_code')->alias('c')
            ->leftJoin('user u', 'u.id = c.user_id')
            ->leftJoin('user iu', 'iu.id = c.invite_from_user_id')
            ->where($where);
        $total = (clone $query)->count();
        $list  = $query
            ->field([
                'c.id', 'c.code', 'c.status', 'c.source', 'c.remark', 'c.createtime', 'c.user_id',
                'IFNULL(u.nickname, CONCAT("用户#", c.user_id)) as user_nickname',
                'u.mobile as user_mobile',
                'IFNULL(iu.nickname, "") as inviter_nickname',
            ])
            ->order('c.id', 'desc')
            ->page($page, $limit)
            ->select()->toArray();

        $this->success('', [
            'list'  => $list,
            'total' => $total,
            'stat'  => $stat,
            'raffle'=> ['id' => (int) $raffle->id, 'quota' => $raffle->per_limit > 0 ? (int) floor($raffle->total / $raffle->per_limit) : 0, 'draw_mode' => (int) $raffle->draw_mode],
        ]);
    }

    /**
     * 手动发码：按用户 ID / 手机号发码入池（source=3）
     */
    public function grantcode(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $raffleId = $this->request->post('raffle_id/d', 0);
        $userKey  = trim((string) $this->request->post('user/s', ''));
        $num      = min(100, max(1, $this->request->post('num/d', 1)));
        $remark   = trim((string) $this->request->post('remark/s', ''));

        $raffle = $this->model->find($raffleId);
        if (!$raffle) {
            $this->error(__('Record not found'));
        }
        if ($userKey === '') {
            $this->error('请填写用户 ID 或手机号');
        }
        if (preg_match('/^1[3-9]\d{9}$/', $userKey)) {
            $user = Db::name('user')->where('mobile', $userKey)->find();
        } else {
            $user = Db::name('user')->where('id', (int) $userKey)->find();
        }
        if (!$user) {
            $this->error('用户不存在');
        }

        // 按活动的码格式生成不重复码
        $codes = [];
        Db::startTrans();
        try {
            for ($i = 0; $i < $num; $i++) {
                $code = $this->genCode((int) $raffle->code_fmt, (string) $raffle->code_prefix, $raffleId);
                Db::name('raffle_code')->insert([
                    'raffle_id'  => $raffleId,
                    'user_id'    => (int) $user['id'],
                    'source'     => 3,
                    'code'       => $code,
                    'status'     => 0,
                    'remark'     => $remark ?: '后台手动发放',
                    'createtime' => time(),
                ]);
                $codes[] = $code;
            }
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('发码失败：' . $e->getMessage());
        }
        OperLog::record('抽签发售', '手动发码', '活动#' . $raffleId, '用户#' . $user['id'] . ' ' . $num . ' 个');
        $this->success('发码成功', ['codes' => $codes, 'user' => $user['nickname'] ?? ('用户#' . $user['id'])]);
    }

    /**
     * 按格式生成唯一码：1=纯数字10位 2=纯字母8位(去易混淆) 3=前缀+数字
     */
    private function genCode(int $fmt, string $prefix, int $raffleId): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($try = 0; $try < 50; $try++) {
            if ($fmt === 2) {
                $code = '';
                for ($i = 0; $i < 8; $i++) {
                    $code .= $letters[random_int(0, 23)];
                }
            } elseif ($fmt === 3) {
                $code = $prefix . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            } else {
                $code = str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
            }
            $exists = Db::name('raffle_code')->where('raffle_id', $raffleId)->where('code', $code)->count();
            if (!$exists) {
                return $code;
            }
        }
        // 兜底：追加时间戳保证唯一
        return ($fmt === 3 ? $prefix : '') . substr((string) time(), -8) . random_int(100, 999);
    }

    /**
     * 作废有效码
     */
    public function voidcode(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id = $this->request->post('id/d', 0);
        $c  = Db::name('raffle_code')->where('id', $id)->find();
        if (!$c) {
            $this->error(__('Record not found'));
        }
        if ((int) $c['status'] !== 0) {
            $this->error('仅「有效」状态的抽签码可作废');
        }
        Db::name('raffle_code')->where('id', $id)->update(['status' => 2]);
        OperLog::record('抽签发售', '作废抽签码', '活动#' . $c['raffle_id'] . ' 码 ' . $c['code'], '');
        $this->success('已作废');
    }
}
