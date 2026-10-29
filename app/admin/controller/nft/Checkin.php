<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\CheckinRule;
use app\admin\model\nft\CheckinRecord;
use app\admin\model\nft\OperLog;

/**
 * 签到活动
 */
class Checkin extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CheckinRule();
    }

    /**
     * 规则配置 + 签到记录
     */
    public function index(): void
    {
        $configNames = ['ck_enabled', 'ck_daily_type', 'ck_daily_num', 'ck_daily_collection_id', 'ck_repair_limit'];
        $config      = Db::name('nft_setting')->whereIn('name', $configNames)->column('value', 'name');

        $keyword = $this->request->get('keyword/s', '');
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $userIds = Db::name('nft_user')->where('nickname|username', 'like', "%{$keyword}%")->column('id');
            $where[] = ['user_id', 'in', $userIds ?: [0]];
        }

        $total  = Db::name('nft_checkin_record')->where($where)->count();
        $logs   = Db::name('nft_checkin_record')->where($where)->order('id', 'desc')->page($page, $limit)->select()->toArray();
        $users  = Db::name('nft_user')->whereIn('id', array_unique(array_column($logs, 'user_id')))->column('nickname', 'id');
        foreach ($logs as &$log) {
            $log['nickname'] = $users[$log['user_id']] ?? '';
        }
        unset($log);

        // 阶梯规则（附藏品名，奖励类型 1=抽奖次数 2=藏品 3=优先购资格）
        $rules      = $this->model->order('day_num', 'asc')->select();
        $ruleColIds = array_values(array_unique(array_column($rules->toArray(), 'collection_id')));
        $ruleCols   = $ruleColIds ? Db::name('nft_collection')->whereIn('id', $ruleColIds)->column('name', 'id') : [];
        foreach ($rules as $rule) {
            $rule->collection_name = $ruleCols[$rule->collection_id] ?? '';
        }

        $this->success('', [
            'config'  => $config,
            'rules'   => $rules,
            'records' => $logs,
            'total'   => $total,
        ]);
    }

    /**
     * 保存规则配置（基础参数 + 阶梯整体重建）
     */
    public function save(): void
    {
        $data = $this->request->post();
        $rules = $data['rules'] ?? [];
        unset($data['rules']);

        $allow = ['ck_enabled', 'ck_daily_type', 'ck_daily_num', 'ck_daily_collection_id', 'ck_repair_limit'];
        Db::startTrans();
        try {
            foreach ($allow as $name) {
                if (!isset($data[$name])) continue;
                $val = (string) $data[$name];
                if ($name != 'ck_enabled') {
                    $val = (string) intval($val);
                }
                Db::name('nft_setting')->where('name', $name)->update(['value' => $val, 'updatetime' => time()]);
            }
            // 阶梯整体重建
            // 奖励类型：1=抽奖次数 2=藏品(reward_num=份数+collection_id) 3=优先购资格(reward_num=可用次数)
            CheckinRule::where('id', '>', 0)->delete();
            $now = time();
            foreach ($rules as $rule) {
                $dayNum = intval($rule['day_num'] ?? 0);
                if ($dayNum <= 0) continue;
                $rewardType = max(1, min(3, intval($rule['reward_type'] ?? 1)));
                $collectionId = intval($rule['collection_id'] ?? 0);
                if ($rewardType == 2 && $collectionId <= 0) {
                    throw new \Exception("连续签到 {$dayNum} 天的阶梯：藏品奖励需选择藏品");
                }
                CheckinRule::create([
                    'day_num'       => $dayNum,
                    'reward_type'   => $rewardType,
                    'reward_num'    => max(1, intval($rule['reward_num'] ?? 1)),
                    'collection_id' => $rewardType == 2 ? $collectionId : 0,
                ]);
            }
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        OperLog::record('签到活动', '保存规则', 'checkin', json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->success();
    }
}
