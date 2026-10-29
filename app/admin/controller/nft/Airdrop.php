<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\AirdropTask;
use app\admin\model\nft\OperLog;

/**
 * 定向发放（空投）
 *
 * 目标规则 rule_type：
 * 1=指定用户(用户ID逗号分隔) 2=全部用户 3=按邀请数(门槛) 4=持有指定藏品(凭证藏品ID,持有N份投N份)
 * 5=持仓门槛(持仓>=N) 6=白名单导入(用户ID或手机号逗号分隔) 7=按消费金额(累计消费>=N元)
 * 8=按注册时间(某日期前注册) 9=随机抽样(全体用户随机抽) 10=按用户标签(含指定标签) 11=邀请排行(Top N)
 *
 * 发放机制：claim_mode 到账方式(0直接到账/1需领取) dup_policy 重复策略(1跳过/2换发抽奖次数/3强制叠加)
 *           plan_time 定时空投 reason 空投凭证文案 revoke 批次撤销
 */
class Airdrop extends Backend
{
    protected object $model;

    /** 规则名称映射 */
    public const RULES = [
        1  => '指定用户',
        2  => '全部用户',
        3  => '按邀请数',
        4  => '持有指定藏品',
        5  => '持仓门槛',
        6  => '白名单导入',
        7  => '按消费金额',
        8  => '按注册时间',
        9  => '随机抽样',
        10 => '按用户标签',
        11 => '邀请排行',
    ];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AirdropTask();
    }

    /**
     * 任务分页（附藏品名 / 待领取数）
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $where[] = ['name', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $collectionIds = array_values(array_unique(array_column($list->toArray(), 'collection_id')));
        $collections   = $collectionIds ? Db::name('nft_collection')->where('id', 'in', $collectionIds)->column('name', 'id') : [];

        // 各任务待领取数（领取式空投）
        $taskIds   = array_column($list->toArray(), 'id');
        $claimMap  = $taskIds ? Db::name('nft_hold')
            ->where('source', 3)
            ->where('claim_status', 0)
            ->where('status', 0)
            ->where('ref_id', 'in', $taskIds)
            ->group('ref_id')
            ->column('count(*)', 'ref_id') : [];

        foreach ($list as $row) {
            $row->collection_name = $collections[$row->collection_id] ?? '';
            $row->rule_name       = self::RULES[(int) $row->rule_type] ?? '-';
            $row->claim_pending   = (int) ($claimMap[$row->id] ?? 0);
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 创建任务
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(
                ['name', 'collection_id', 'num', 'rule_type', 'rule_value', 'claim_mode', 'dup_policy', 'dup_reward', 'plan_time', 'reason'],
                'post'
            );
            try {
                validate(\app\admin\validate\nft\Airdrop::class)->scene('add')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }

            // 规则参数校验
            $ruleType  = (int) $data['rule_type'];
            $ruleValue = trim((string) ($data['rule_value'] ?? ''));
            $needValue = [1, 3, 4, 5, 6, 7, 8, 10, 11];
            if (in_array($ruleType, $needValue) && $ruleValue === '') {
                $this->error('请填写规则参数');
            }
            if ($ruleType == 4) {
                // 凭证藏品必须存在
                $exists = Db::name('nft_collection')->where('id', (int) $ruleValue)->count();
                if (!$exists) {
                    $this->error('凭证藏品不存在');
                }
            }
            if ($ruleType == 8 && strtotime($ruleValue) === false) {
                $this->error('注册时间格式错误');
            }

            $data['rule_value'] = $ruleValue;
            $data['dup_reward'] = max(0, (int) ($data['dup_reward'] ?? 0));
            $data['plan_time']  = !empty($data['plan_time']) ? strtotime($data['plan_time']) : null;
            if ($data['dup_policy'] == 2 && $data['dup_reward'] <= 0) {
                $this->error('换发抽奖次数策略需填写换发次数');
            }
            $data['admin_id'] = $this->auth->id;
            $data['status']   = 0;
            $this->model->save($data);
            OperLog::record('定向发放', '创建任务', '任务#' . $this->model->id, $data['name'] . '（' . self::RULES[$ruleType] . '）');
            $this->success();
        }
        $this->success('', []);
    }

    /**
     * 执行/重试发放（含全部 11 种规则解析）
     */
    public function retry(): void
    {
        $id   = $this->request->param('id/d', 0);
        $task = $this->model->find($id);
        if (!$task) {
            $this->error(__('Record not found'));
        }
        if ($task->status == 1) {
            $this->error('正在发放中');
        }
        if (!in_array($task->status, [0, 2])) {
            $this->error('当前状态不可执行发放');
        }

        // 续发偏移 = 本任务现存（未被撤销的）持仓数：撤销后重跑可自动补发被回收的份额
        $done      = (int) Db::name('nft_hold')->where('source', 3)->where('ref_id', $id)->count();
        $num       = (int) $task->num;
        $ruleType  = (int) $task->rule_type;
        $ruleValue = (string) $task->rule_value;
        $remain    = max(0, $num - $done);

        $task->status = 1;
        $task->save();

        $success = 0;
        $fail    = 0;
        $reward  = 0;
        $now     = time();

        Db::startTrans();
        try {
            $userIds = $this->resolveUsers($ruleType, $ruleValue, $num, $remain, $done);

            $claimStatus = (int) $task->claim_mode == 1 ? 0 : 1; // 领取式空投铸造后为待领取
            $dupPolicy   = (int) $task->dup_policy;
            $dupReward   = (int) $task->dup_reward;

            foreach ($userIds as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0) {
                    continue;
                }
                // 重复拥有处理
                $has = Db::name('nft_hold')
                    ->where('user_id', $uid)
                    ->where('collection_id', $task->collection_id)
                    ->count();
                if ($has > 0 && $dupPolicy == 1) {
                    // 跳过
                    $fail++;
                    continue;
                }
                if ($has > 0 && $dupPolicy == 2) {
                    // 换发抽奖次数：写入抽奖次数流水（来源 7 后台发放）
                    Db::name('nft_lottery_chance')->insert([
                        'user_id'     => $uid,
                        'activity_id' => 0,
                        'num'         => $dupReward,
                        'source'      => 7,
                        'ref'         => '空投放弃换发：' . ($task->reason ?: '任务#' . $task->id),
                        'createtime'  => $now,
                    ]);
                    $reward++;
                    continue;
                }
                // 铸造持仓（dup_policy=3 强制叠加时即使已拥有也铸造）
                $seq = 1;
                $sn  = 'A' . $task->id . 'U' . $uid . 'N' . $seq;
                while (Db::name('nft_hold')->where('sn', $sn)->count() > 0) {
                    $seq++;
                    $sn = 'A' . $task->id . 'U' . $uid . 'N' . $seq;
                }
                Db::name('nft_hold')->insert([
                    'sn'            => $sn,
                    'collection_id' => (int) $task->collection_id,
                    'user_id'       => $uid,
                    'source'        => 3, // 定向发放
                    'status'        => 0, // 持有
                    'claim_status'  => $claimStatus,
                    'ref_id'        => (int) $task->id,
                    'mint_time'     => $now,
                    'createtime'    => $now,
                    'updatetime'    => $now,
                ]);
                $success++;
            }

            $task->success_num = $done + $success;
            $task->fail_num    = (int) $task->fail_num + $fail;
            $task->status      = 2;
            $task->save();
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $task->status = 0;
            $task->save();
            $this->error('发放失败：' . $e->getMessage());
        }

        OperLog::record('定向发放', $done > 0 ? '重试发放' : '执行发放', '任务#' . $id, '入仓 ' . $success . '，跳过 ' . $fail . '，换发 ' . $reward);
        $this->success('发放完成', ['success' => $success, 'fail' => $fail, 'reward' => $reward]);
    }

    /**
     * 撤销批次：回收本任务投出、仍处于「持有」状态的持仓（已寄售/锁定的跳过）
     */
    public function revoke(): void
    {
        $id   = $this->request->param('id/d', 0);
        $task = $this->model->find($id);
        if (!$task) {
            $this->error(__('Record not found'));
        }
        if ($task->status != 2) {
            $this->error('仅完成的任务可撤销');
        }

        $holds = Db::name('nft_hold')
            ->where('source', 3)
            ->where('ref_id', $id)
            ->where('status', 0) // 仅持有中（未寄售、未锁定）
            ->field('id')
            ->select()
            ->toArray();
        if (!$holds) {
            $this->error('没有可回收的持仓（已寄售/锁定的部分不可回收）');
        }

        Db::startTrans();
        try {
            $ids = array_column($holds, 'id');
            Db::name('nft_hold')->where('id', 'in', $ids)->delete();
            $task->revoke_num = (int) $task->revoke_num + count($ids);
            $task->save();
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('撤销失败：' . $e->getMessage());
        }

        OperLog::record('定向发放', '撤销批次', '任务#' . $id, '回收 ' . count($ids) . ' 份');
        $this->success('撤销完成', ['revoked' => count($ids)]);
    }

    /**
     * 删除任务（发放中不可删）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        $count = $this->model->where('id', 'in', $ids)->where('status', '<>', 1)->delete();
        OperLog::record('定向发放', '删除', '任务#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }

    /**
     * 拆分规则参数：换行 / 英文逗号 / 中文逗号均视为分隔符
     */
    private function splitRuleValue(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\n\r,，]+/', $value)), fn ($v) => $v !== ''));
    }

    /**
     * 按规则解析目标用户 ID 列表（可含重复：规则 4 按持有件数展开）
     */
    private function resolveUsers(int $ruleType, string $ruleValue, int $num, int $remain, int $done): array
    {
        $enabled = fn () => Db::name('nft_user')->where('status', 1);

        switch ($ruleType) {
            case 1: // 指定用户（支持换行 / 逗号分隔）
                $ids = array_values(array_filter(array_map('intval', $this->splitRuleValue($ruleValue)), fn ($v) => $v > 0));
                return array_slice($ids, $done, $remain);

            case 2: // 全部用户
                return $enabled()->order('id', 'asc')->limit($done, $remain)->column('id');

            case 3: // 按邀请数门槛
            case 11: // 邀请排行 Top N
                $query = Db::name('nft_invite_record')
                    ->field('inviter_id, count(*) as invite_count')
                    ->group('inviter_id');
                if ($ruleType == 3) {
                    $query->having('invite_count >= ' . (int) $ruleValue);
                }
                $inviters   = $query->select()->toArray();
                usort($inviters, fn ($a, $b) => $b['invite_count'] <=> $a['invite_count']);
                $enabledIds = array_flip($enabled()->column('id'));
                $ids        = array_values(array_filter(array_column($inviters, 'inviter_id'), fn ($v) => isset($enabledIds[$v])));
                if ($ruleType == 11) {
                    $ids = array_slice($ids, 0, (int) $ruleValue);
                }
                return array_slice($ids, $done, $remain);

            case 4: // 持有指定藏品：持有 N 份投 N 份（列表按持有件数展开）
                $holders = Db::name('nft_hold')
                    ->where('collection_id', (int) $ruleValue)
                    ->where('status', '<>', 3)
                    ->field('user_id, count(*) as c')
                    ->group('user_id')
                    ->select()
                    ->toArray();
                usort($holders, fn ($a, $b) => $b['c'] <=> $a['c']);
                $list = [];
                foreach ($holders as $h) {
                    $list = array_merge($list, array_fill(0, (int) $h['c'], (int) $h['user_id']));
                }
                return array_slice($list, $done, $remain);

            case 5: // 持仓门槛：持仓 >= N
                $holders = Db::name('nft_hold')
                    ->field('user_id, count(*) as c')
                    ->group('user_id')
                    ->having('c >= ' . (int) $ruleValue)
                    ->column('user_id');
                $enabledIds = array_flip($enabled()->column('id'));
                $ids        = array_values(array_filter($holders, fn ($v) => isset($enabledIds[$v])));
                return array_slice($ids, $done, $remain);

            case 6: // 白名单导入：支持用户 ID 或手机号（每行一个或逗号分隔）
                $parts = $this->splitRuleValue($ruleValue);
                $ids   = [];
                $mobiles = [];
                foreach ($parts as $p) {
                    if (preg_match('/^1\d{10}$/', $p)) {
                        $mobiles[] = $p;
                    } elseif (ctype_digit($p) && (int) $p > 0) {
                        $ids[] = (int) $p;
                    }
                }
                $mobileIds = $mobiles ? $enabled()->where('mobile', 'in', $mobiles)->column('id') : [];
                $all = array_values(array_unique(array_merge($ids, $mobileIds)));
                return array_slice($all, $done, $remain);

            case 7: // 按消费金额：累计支出 >= N 元
                $minFen = (int) bcmul(round((float) $ruleValue, 2), '100');
                $ids    = $enabled()->where('total_expense', '>=', $minFen)->order('total_expense', 'desc')->column('id');
                return array_slice($ids, $done, $remain);

            case 8: // 按注册时间：某日期前注册
                $ts  = strtotime($ruleValue);
                $ids = $enabled()->where('createtime', '<', $ts)->order('id', 'asc')->column('id');
                return array_slice($ids, $done, $remain);

            case 9: // 随机抽样
                $all = $enabled()->column('id');
                shuffle($all);
                return array_slice($all, 0, $num);

            case 10: // 按用户标签
                $ids = $enabled()->whereLike('tags', '%' . $ruleValue . '%')->order('id', 'asc')->column('id');
                return array_slice($ids, $done, $remain);
        }
        return [];
    }
}
