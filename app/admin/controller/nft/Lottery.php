<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\LotteryActivity;
use app\admin\model\nft\LotteryRecord;
use app\admin\model\nft\OperLog;

/**
 * 抽奖活动
 *
 * 次数获取方式（chance_rules JSON，不使用积分）：
 * daily_free={enabled,num}          每日签到赠送
 * register={enabled,num}            新人注册赠送
 * hold={enabled,items:[{collection_id,num}]}  持有指定藏品（按持有件数）
 * invite={enabled,num,daily_cap}    邀请好友（可设每日上限）
 * consume={enabled,items:[{collection_id,num}]} 消耗(销毁)指定藏品
 * 抽奖本身每次消耗 1 次机会（来源 6）
 */
class Lottery extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new LotteryActivity();
    }

    /**
     * 活动分页（附奖品数、奖池总库存）
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $where[] = ['title', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $ids = array_column($list->toArray(), 'id');
        $map = [];
        if ($ids) {
            $stats = Db::name('nft_lottery_prize')
                ->field('activity_id, count(*) as prize_count, sum(stock) as total_stock')
                ->where('activity_id', 'in', $ids)
                ->group('activity_id')
                ->select()
                ->toArray();
            $map = array_column($stats, null, 'activity_id');
        }
        foreach ($list as $row) {
            $row->prize_count = (int) ($map[$row->id]['prize_count'] ?? 0);
            $row->total_stock = (int) ($map[$row->id]['total_stock'] ?? 0);
            $row->chance_rules = $row->chance_rules ? json_decode($row->chance_rules, true) : (object) [];
        }

        $this->success('', ['list' => $list, 'total' => $total]);
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
        $data = $this->request->only(['title', 'status', 'start_time', 'end_time'], 'post');
        try {
            validate(\app\admin\validate\nft\LotteryActivity::class)->scene($id ? 'edit' : 'add')->check($data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
        $data['start_time'] = !empty($data['start_time']) ? strtotime($data['start_time']) : null;
        $data['end_time']   = !empty($data['end_time']) ? strtotime($data['end_time']) : null;
        if ($data['start_time'] && $data['end_time'] && $data['end_time'] <= $data['start_time']) {
            $this->error('结束时间必须晚于开始时间');
        }

        // 次数获取配置（JSON 入库，只允许已知键）
        $rules         = $this->request->post('chance_rules/a', []);
        $allow         = ['daily_free', 'register', 'hold', 'invite', 'consume'];
        $clean         = [];
        foreach ($allow as $key) {
            if (!isset($rules[$key]) || !is_array($rules[$key])) {
                continue;
            }
            $r = $rules[$key];
            $item = ['enabled' => (int) !empty($r['enabled'])];
            if (in_array($key, ['hold', 'consume'])) {
                // 指定藏品：collection_id => 每件获得次数
                $items = [];
                foreach ((array) ($r['items'] ?? []) as $it) {
                    $cid = (int) ($it['collection_id'] ?? 0);
                    $num = (int) ($it['num'] ?? 0);
                    if ($cid > 0 && $num > 0) {
                        $items[] = ['collection_id' => $cid, 'num' => $num];
                    }
                }
                $item['items'] = $items;
            } else {
                $item['num'] = max(0, (int) ($r['num'] ?? 0));
                if ($key === 'invite') {
                    $item['daily_cap'] = max(0, (int) ($r['daily_cap'] ?? 0)); // 0=不限制
                }
            }
            $clean[$key] = $item;
        }
        $data['chance_rules'] = json_encode($clean, JSON_UNESCAPED_UNICODE);

        // 开启校验：奖池非空且概率合计 > 0（奖池在 prizes 端点维护，本表单不影响奖池）
        if ((int) ($data['status'] ?? 0) === 1) {
            $prizeQuery = Db::name('nft_lottery_prize')->where('activity_id', $id > 0 ? $id : 0);
            if ($prizeQuery->count() <= 0 || (int) $prizeQuery->sum('probability') <= 0) {
                $this->error('开启活动前请先在「奖池配置」中添加奖品并设置概率');
            }
        }

        if ($id) {
            $row = $this->model->find($id);
            if (!$row) {
                $this->error(__('Record not found'));
            }
            $row->save($data);
            OperLog::record('抽奖活动', '编辑', '活动#' . $id, $data['title']);
        } else {
            $this->model->save($data);
            OperLog::record('抽奖活动', '添加', '活动#' . $this->model->id, $data['title']);
        }
        $this->success();
    }

    /**
     * 删除活动（级联删奖池，保留中奖记录）
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
            Db::name('nft_lottery_prize')->where('activity_id', 'in', $ids)->delete();
            Db::name('nft_lottery_chance')->where('activity_id', 'in', $ids)->delete();
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('删除失败：' . $e->getMessage());
        }
        OperLog::record('抽奖活动', '删除', '活动#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }

    /**
     * 奖池：GET 查看 / POST 整体重建（先删后插）
     */
    public function prizes(): void
    {
        $activityId = $this->request->param('activity_id/d', 0);
        $activity   = $this->model->find($activityId);
        if (!$activity) {
            $this->error(__('Record not found'));
        }

        if ($this->request->isPost()) {
            $prizes = $this->request->post('prizes/a', []);
            // 保留已发放数：按奖品名匹配旧奖池（重建奖池不重置 sent，避免库存凭空回补）
            $oldSent = [];
            foreach (Db::name('nft_lottery_prize')->where('activity_id', $activityId)->field('name, sent')->select() as $old) {
                if (!isset($oldSent[$old['name']])) {
                    $oldSent[$old['name']] = (int) $old['sent'];
                }
            }
            Db::startTrans();
            try {
                Db::name('nft_lottery_prize')->where('activity_id', $activityId)->delete();
                foreach ($prizes as $p) {
                    $name = (string) ($p['name'] ?? '');
                    Db::name('nft_lottery_prize')->insert([
                        'activity_id'   => $activityId,
                        'name'          => $name,
                        'type'          => (int) ($p['type'] ?? 1),
                        'collection_id' => (int) ($p['collection_id'] ?? 0),
                        'num'           => (int) ($p['num'] ?? 0),
                        'probability'   => (int) ($p['probability'] ?? 0),
                        'stock'         => (int) ($p['stock'] ?? 0),
                        'sent'          => $oldSent[$name] ?? 0,
                        'createtime'    => time(),
                        'updatetime'    => time(),
                    ]);
                }
                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error('保存失败：' . $e->getMessage());
            }
            OperLog::record('抽奖活动', '奖池配置', '活动#' . $activityId, '共 ' . count($prizes) . ' 个奖品');
            $this->success();
        }

        $list = Db::name('nft_lottery_prize')->where('activity_id', $activityId)->order('id', 'asc')->select();
        $this->success('', ['list' => $list]);
    }

    /**
     * 中奖记录分页（附 user 昵称、奖品名）
     */
    public function records(): void
    {
        $activityId = $this->request->get('activity_id/d', 0);
        $limit      = $this->request->get('limit/d', 10);
        $page       = $this->request->get('page/d', 1);

        $where = [];
        if ($activityId > 0) {
            $where[] = ['activity_id', '=', $activityId];
        }

        $recordModel = new LotteryRecord();
        $total       = $recordModel->where($where)->count();
        $list        = $recordModel->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        // 两次查询拼装
        $userIds  = array_values(array_unique(array_column($list, 'user_id')));
        $prizeIds = array_values(array_unique(array_column($list, 'prize_id')));
        $users    = $userIds ? Db::name('nft_user')->where('id', 'in', $userIds)->column('nickname', 'id') : [];
        $prizes   = $prizeIds ? Db::name('nft_lottery_prize')->where('id', 'in', $prizeIds)->column('name', 'id') : [];
        foreach ($list as &$row) {
            $row['nickname']   = $users[$row['user_id']] ?? ('用户#' . $row['user_id']);
            $row['prize_name'] = $prizes[$row['prize_id']] ?? '-';
        }
        unset($row);

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 抽奖次数流水分页（source: 1签到 2注册 3持有 4邀请 5消耗藏品 6抽奖消耗 7后台发放）
     */
    public function chances(): void
    {
        $limit     = $this->request->get('limit/d', 10);
        $page      = $this->request->get('page/d', 1);
        $activityId = $this->request->get('activity_id/d', 0);
        $keyword   = $this->request->get('keyword/s', '');
        $source    = $this->request->get('source/d', -1);

        $where = [];
        if ($activityId > 0) {
            $where[] = ['c.activity_id', '=', $activityId];
        }
        if ($source >= 0) {
            $where[] = ['c.source', '=', $source];
        }

        $chanceModel = new \app\admin\model\nft\LotteryChance();
        $query = $chanceModel->alias('c');
        if ($keyword !== '') {
            // 按用户昵称/ID 关联筛选
            $userIds = Db::name('nft_user')
                ->where('id', $keyword)
                ->whereOr('nickname', 'like', '%' . str_replace('%', '\%', $keyword) . '%')
                ->column('id');
            $where[] = ['c.user_id', 'in', $userIds ?: [0]];
        }

        $total = $query->where($where)->count();
        $list  = $chanceModel->alias('c')->where($where)
            ->order('c.id', 'desc')
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

    /**
     * 后台手动补发次数（客服补偿/活动补偿）
     */
    public function grant(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $userId     = $this->request->post('user_id/d', 0);
        $activityId = $this->request->post('activity_id/d', 0);
        $num        = $this->request->post('num/d', 0);
        $remark     = $this->request->post('remark/s', '');
        if ($userId <= 0) {
            $this->error('请填写用户ID');
        }
        if ($num <= 0) {
            $this->error('补发次数必须大于 0');
        }
        $user = Db::name('nft_user')->where('id', $userId)->find();
        if (!$user) {
            $this->error('用户不存在');
        }
        if ($activityId > 0 && !$this->model->where('id', $activityId)->find()) {
            $this->error('活动不存在');
        }

        Db::name('nft_lottery_chance')->insert([
            'user_id'     => $userId,
            'activity_id' => $activityId,
            'num'         => $num,
            'source'      => 7,
            'ref'         => $remark ?: '后台手动补发',
            'createtime'  => time(),
        ]);
        OperLog::record('抽奖活动', '补发次数', '用户#' . $userId, $num . ' 次' . ($activityId ? ' / 活动#' . $activityId : ''));
        $this->success();
    }
}
