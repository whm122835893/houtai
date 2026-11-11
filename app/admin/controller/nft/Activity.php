<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\Setting as SettingModel;
use app\admin\model\nft\LotteryActivity;
use app\admin\model\nft\OperLog;

/**
 * 活动中心
 * 统一管理四类活动：
 * - 邀请有礼 / 签到活动 / 注册活动：多场次（act_session，type=1/2/3），开关在场次上；
 *   开关同步 setting（invite_reward_enable / ck_enabled / rg_enabled）保证 C 端旧读取兼容
 * - 抽奖活动：多场次（lottery_activity.status），次数获取规则 chance_rules 绑定场次
 * 每行输出奖励概览（reward），对齐原型「活动中心」列表
 */
class Activity extends Backend
{
    protected object $model;

    protected array $noNeedPermission = ['index', 'toggle'];

    /** 场次类型 => setting 同步键 */
    protected array $syncMap = [
        1 => 'invite_reward_enable',
        2 => 'ck_enabled',
        3 => 'rg_enabled',
    ];

    /** 场次类型 => [名称, tag样式] */
    protected array $typeMap = [
        1 => ['邀请', 'tag-orange'],
        2 => ['签到', 'tag-blue'],
        3 => ['注册', 'tag-green'],
    ];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new SettingModel();
    }

    /**
     * 活动列表（聚合场次 + 奖励概览 + 模块总开关）
     */
    public function index(): void
    {
        $list = [];

        $sessions = Db::name('act_session')->where('is_deleted', 0)->where('type', 'in', [1, 2, 3])
            ->order('id', 'asc')->select()->toArray();
        $defaultMap = [];
        foreach ($sessions as $s) {
            $t = (int) $s['type'];
            if (!isset($defaultMap[$t]) || (int) $s['id'] < $defaultMap[$t]) {
                $defaultMap[$t] = (int) $s['id'];
            }
        }
        foreach ($sessions as $s) {
            $type = (int) $s['type'];
            $aid  = ((int) $s['id'] === $defaultMap[$type]) ? 0 : (int) $s['id'];
            $list[] = [
                'type'       => $type,
                'type_name'  => $this->typeMap[$type][0],
                'id'         => (int) $s['id'],
                'name'       => (string) $s['title'],
                'start_time' => (int) $s['start_time'],
                'end_time'   => (int) $s['end_time'],
                'status'     => (int) $s['status'],
                'has_config' => $this->hasConfig($type, (int) $s['id'], (string) $s['config'], $aid),
                'reward'     => $this->rewardOf($type, $aid, (string) $s['config']),
                'page'       => [1 => 'invite', 2 => 'checkin', 3 => 'register'][$type],
            ];
        }

        // 抽奖场次
        $lotteries = LotteryActivity::order('id', 'desc')->select();
        foreach ($lotteries as $activity) {
            $prizeCount = Db::name('lottery_prize')->where('activity_id', (int) $activity->id)->count();
            $chance     = json_decode((string) $activity->chance_rules, true) ?: [];
            $chanceNum  = 0;
            foreach ($chance as $v) {
                if (is_array($v)) {
                    if (!empty($v['enabled'])) {
                        $chanceNum++;
                    }
                } elseif ($v) {
                    $chanceNum++;
                }
            }
            $list[] = [
                'type'         => 'lottery',
                'type_name'    => '抽奖',
                'id'           => (int) $activity->id,
                'name'         => (string) $activity->title,
                'status'       => (int) $activity->status,
                'start_time'   => (int) $activity->start_time,
                'end_time'     => (int) $activity->end_time,
                'has_config'   => true,
                'reward'       => $prizeCount . ' 种奖品' . ($chanceNum ? ' · 次数来源 ' . $chanceNum . ' 种' : ''),
                'chance_rules' => $chance,
                'page'         => 'lottery',
            ];
        }

        $mods = [];
        foreach (['invite' => '邀请', 'checkin' => '签到', 'register' => '注册', 'lottery' => '抽奖', 'synthesis' => '合成'] as $k => $n) {
            $mods[] = ['key' => $k, 'name' => $n, 'on' => $this->model->where('name', 'mod_' . $k)->value('value') !== '0'];
        }

        $this->success('', ['list' => $list, 'mods' => $mods]);
    }

    /**
     * 奖励概览文本
     */
    protected function rewardOf(int $type, int $aid, string $config): string
    {
        if ($type === 1) {
            $n = Db::name('invite_rule')->where('activity_id', $aid)->where('status', 1)->count();
            return $n . ' 档奖励规则';
        }
        if ($type === 2) {
            $n = Db::name('checkin_rule')->where('activity_id', $aid)->count();
            return '每日 1 次 · ' . $n . ' 档阶梯';
        }
        if ($type === 3) {
            $cfg = json_decode($config ?: '{}', true) ?: [];
            $parts = [];
            if (!empty($cfg['chance'])) {
                $parts[] = '抽奖次数 ' . (int) $cfg['chance'];
            }
            if (!empty($cfg['collection_id'])) {
                $parts[] = '新人藏品';
            }
            if (!empty($cfg['pre_num'])) {
                $parts[] = '优先购 ' . (int) $cfg['pre_num'] . ' 次';
            }
            if (!empty($cfg['inviter_num'])) {
                $parts[] = '邀请人奖 ' . (int) $cfg['inviter_num'];
            }
            if (!$parts) {
                $chance = (int) Db::name('setting')->where('name', 'rg_chance')->value('value');
                if ($chance > 0) {
                    $parts[] = '抽奖次数 ' . $chance;
                }
            }
            return $parts ? implode(' + ', $parts) : '待配置';
        }
        return '';
    }

    /**
     * 场次是否有配置
     */
    protected function hasConfig(int $type, int $id, string $config, int $aid = -1): bool
    {
        $cfg = json_decode($config ?: '{}', true);
        if (is_array($cfg) && $cfg !== []) {
            return true;
        }
        if ($aid < 0) {
            $defaultId = (int) Db::name('act_session')->where('is_deleted', 0)->where('type', $type)->min('id');
            $aid = ($id === $defaultId) ? 0 : $id;
        }
        return match ($type) {
            1 => (bool) Db::name('invite_rule')->where('activity_id', $aid)->count(),
            2 => (bool) Db::name('checkin_rule')->where('activity_id', $aid)->count(),
            default => false,
        };
    }

    /**
     * 开启/关闭活动
     * 参数：id=场次ID（act_session 或 lottery_activity），status=1|0
     */
    public function toggle(): void
    {
        $type   = $this->request->param('type/s', '');
        $id     = $this->request->param('id/d', 0);
        $status = $this->request->param('status/d', 0) ? 1 : 0;
        $now    = time();

        // 模块总开关（mod_invite / mod_checkin / mod_register / mod_lottery / mod_synthesis）
        if ($type === 'mod') {
            $key = $this->request->param('id/s', '');
            $allow = ['invite', 'checkin', 'register', 'lottery', 'synthesis'];
            if (!in_array($key, $allow, true)) {
                $this->error('未知模块');
            }
            $this->model->where('name', 'mod_' . $key)->update(['value' => (string) $status, 'updatetime' => $now]);
            OperLog::record('活动中心', $status ? '开启模块' : '关闭模块', 'mod_' . $key, '');
            $this->success();
        }

        // 先按 act_session 场次定位
        $session = Db::name('act_session')->where('is_deleted', 0)->where('id', $id)->find();
        if ($session) {
            $sType = (int) $session['type'];
            if (isset($this->syncMap[$sType])) {
                if ($status === 1 && !$this->hasConfig($sType, $id, (string) $session['config'])) {
                    $this->error('该场次尚未配置奖励，请先完成配置再开启');
                }
                Db::startTrans();
                try {
                    if ($status === 1) {
                        Db::name('act_session')->where('is_deleted', 0)->where('type', $sType)->where('id', '<>', $id)->update(['status' => 0, 'updatetime' => $now]);
                    }
                    Db::name('act_session')->where('id', $id)->update(['status' => $status, 'updatetime' => $now]);
                    $moduleOn = (int) Db::name('act_session')->where('is_deleted', 0)->where('type', $sType)->where('status', 1)->count() > 0;
                    Db::name('setting')->where('name', $this->syncMap[$sType])->update(['value' => (string) $moduleOn, 'updatetime' => $now]);
                    Db::commit();
                } catch (\Throwable $e) {
                    Db::rollback();
                    $this->error('操作失败：' . $e->getMessage());
                }
                OperLog::record('活动中心', $status ? '开启活动' : '关闭活动', '场次#' . $id, (string) $session['title']);
                $this->success($status ? '已开启，同类型其他场次已自动关闭' : '已关闭');
            }
        }

        // 抽奖场次
        $activity = LotteryActivity::find($id);
        if (!$activity) {
            $this->error(__('Record not found'));
        }
        $activity->status = $status;
        $activity->save();
        OperLog::record('活动中心', $status ? '开启活动' : '关闭活动', '抽奖活动#' . $id, $activity->title);
        $this->success();
    }
}
