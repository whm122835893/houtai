<?php

namespace app\admin\controller\nft;

use app\common\controller\Backend;
use app\admin\model\nft\Setting as SettingModel;
use app\admin\model\nft\LotteryActivity;
use app\admin\model\nft\OperLog;

/**
 * 活动中心
 * 统一管理四类活动的开启/关闭：
 * - 邀请有礼 / 签到活动 / 注册活动：系统唯一活动，开关存于 nft_setting
 * - 抽奖活动：可创建多场次，开关存于 nft_lottery_activity.status；
 *   次数获取规则（chance_rules：签到/注册/持有藏品/邀请/消耗藏品）绑定到每个抽奖场次
 */
class Activity extends Backend
{
    protected object $model;

    protected array $noNeedPermission = ['index', 'toggle'];

    /**
     * 系统唯一活动 => 设置项名
     */
    protected array $singleMap = [
        'invite'   => 'invite_reward_enable',
        'checkin'  => 'ck_enabled',
        'register' => 'rg_enabled',
    ];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new SettingModel();
    }

    /**
     * 活动列表（聚合四类活动状态）
     */
    public function index(): void
    {
        $settingRows = $this->model->where('name', 'in', array_values($this->singleMap))->column('value', 'name');

        $list = [
            ['type' => 'invite',   'name' => '邀请有礼',   'single' => 1, 'status' => (int) ($settingRows['invite_reward_enable'] ?? 1)],
            ['type' => 'checkin',  'name' => '每日签到',   'single' => 1, 'status' => (int) ($settingRows['ck_enabled'] ?? 1)],
            ['type' => 'register', 'name' => '新人注册礼', 'single' => 1, 'status' => (int) ($settingRows['rg_enabled'] ?? 1)],
        ];

        $lotteries = LotteryActivity::order('id', 'desc')->select();
        foreach ($lotteries as $activity) {
            $list[] = [
                'type'         => 'lottery',
                'id'           => (int) $activity->id,
                'name'         => (string) $activity->title,
                'single'       => 0,
                'status'       => (int) $activity->status,
                'start_time'   => (int) $activity->start_time,
                'end_time'     => (int) $activity->end_time,
                'chance_rules' => $activity->chance_rules ? json_decode($activity->chance_rules, true) : [],
            ];
        }

        $this->success('', ['list' => $list]);
    }

    /**
     * 开启/关闭活动
     * 参数：type=invite|checkin|register|lottery, id=抽奖场次ID(仅lottery), status=1|0
     */
    public function toggle(): void
    {
        $type   = $this->request->param('type/s', '');
        $id     = $this->request->param('id/d', 0);
        $status = $this->request->param('status/d', 0) ? 1 : 0;

        if (isset($this->singleMap[$type])) {
            $this->model->where('name', $this->singleMap[$type])->update(['value' => (string) $status, 'updatetime' => time()]);
            OperLog::record('活动中心', $status ? '开启活动' : '关闭活动', $type, '');
            $this->success();
        }

        if ($type === 'lottery') {
            $activity = LotteryActivity::find($id);
            if (!$activity) {
                $this->error(__('Record not found'));
            }
            $activity->status = $status;
            $activity->save();
            OperLog::record('活动中心', $status ? '开启活动' : '关闭活动', '抽奖活动#' . $id, $activity->title);
            $this->success();
        }

        $this->error('未知活动类型');
    }
}
