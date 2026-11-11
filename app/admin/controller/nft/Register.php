<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;

/**
 * 注册活动（新人礼包）
 */
class Register extends Backend
{
    /**
     * 无需模型（配置存 setting，记录走 Db 查询）
     */
    protected object $model;

    public function index(): void
    {
        $configNames = ['rg_enabled', 'rg_chance', 'rg_lot_activity_id', 'rg_collection_id', 'rg_pre_num', 'rg_pre_collection_id', 'rg_inviter_type', 'rg_inviter_num', 'rg_inviter_collection_id', 'rg_inviter_pre_collection_id'];
        $config      = Db::name('setting')->whereIn('name', $configNames)->column('value', 'name');

        // 非默认场次配置回显
        $sessionCfg = (object) [];
        $activityId = (int) $this->request->get('activity_id/d', 0);
        $defaultId  = (int) Db::name('act_session')->where('is_deleted', 0)->where('type', 3)->min('id');
        if ($activityId > 0 && $activityId !== $defaultId) {
            $raw = Db::name('act_session')->where('is_deleted', 0)->where('id', $activityId)->value('config');
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $sessionCfg = [
                    'rg_chance'         => (string) ($decoded['chance'] ?? 0),
                    'rg_lot_activity_id'=> (string) ($decoded['lot_activity_id'] ?? 0),
                    'rg_collection_id'  => (string) ($decoded['collection_id'] ?? 0),
                    'rg_pre_num'        => (string) ($decoded['pre_num'] ?? 0),
                    'rg_pre_collection_id' => (string) ($decoded['pre_collection_id'] ?? 0),
                    'rg_inviter_type'   => (string) ($decoded['inviter_type'] ?? 1),
                    'rg_inviter_num'    => (string) ($decoded['inviter_num'] ?? 0),
                    'rg_inviter_collection_id' => (string) ($decoded['inviter_collection_id'] ?? 0),
                    'rg_inviter_pre_collection_id' => (string) ($decoded['inviter_pre_collection_id'] ?? 0),
                ];
            }
        }

        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $userIds = Db::name('user')->where('nickname|username', 'like', "%{$keyword}%")->column('id');
            $where[] = ['user_id', 'in', $userIds ?: [0]];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = Db::name('register_log')->where($where)->count();
        $logs  = Db::name('register_log')->where($where)->order('id', 'desc')->page($page, $limit)->select()->toArray();
        $users = Db::name('user')->whereIn('id', array_unique(array_column($logs, 'user_id')))->column('nickname', 'id');
        $invi  = Db::name('user')->whereIn('id', array_unique(array_filter(array_column($logs, 'inviter_id'))))->column('nickname', 'id');
        foreach ($logs as &$log) {
            $log['nickname']      = $users[$log['user_id']] ?? '';
            $log['inviter_nickname'] = $invi[$log['inviter_id']] ?? '';
        }
        unset($log);

        $this->success('', ['config' => $config, 'session_config' => $sessionCfg, 'logs' => $logs, 'total' => $total]);
    }

    /**
     * 保存配置
     * 新人礼包 = 抽奖次数(rg_chance，绑定抽奖活动 rg_lot_activity_id，0=通用；绑定场次未开启时次数待生效)
     *          + 藏品(rg_collection_id) + 优先购资格(rg_pre_num，目标藏品 rg_pre_collection_id，可预配置未发售藏品)
     * 邀请人奖励 = 类型(rg_inviter_type: 1=抽奖次数 2=藏品 3=优先购资格) + 数量 + 藏品ID + 优先购目标藏品ID(rg_inviter_pre_collection_id，类型=3 时必填，未发售可预配置)
     */
    public function save(): void
    {
        $data  = $this->request->post();
        $allow = ['rg_enabled', 'rg_chance', 'rg_lot_activity_id', 'rg_collection_id', 'rg_pre_num', 'rg_pre_collection_id', 'rg_inviter_type', 'rg_inviter_num', 'rg_inviter_collection_id', 'rg_inviter_pre_collection_id'];
        try {
            $inviterType = intval($data['rg_inviter_type'] ?? 1);
            if ($inviterType < 1 || $inviterType > 3) {
                $this->error('邀请人奖励类型错误');
            }
            if ($inviterType == 2 && intval($data['rg_inviter_collection_id'] ?? 0) <= 0) {
                $this->error('邀请人藏品奖励需选择藏品');
            }
            if ($inviterType == 3 && intval($data['rg_inviter_pre_collection_id'] ?? 0) <= 0) {
                $this->error('邀请人优先购奖励需选择目标藏品（未发售藏品可预配置）');
            }
            $preNum  = intval($data['rg_pre_num'] ?? 0);
            $preCol  = intval($data['rg_pre_collection_id'] ?? 0);
            if ($preNum > 0 && $preCol <= 0) {
                $this->error('优先购资格需选择目标藏品（未发售藏品可预配置）');
            }

            // 场次绑定：默认场次（最小 id）→ setting；其余场次 → act_session.config JSON
            $activityId = (int) ($data['activity_id'] ?? 0);
            $defaultId  = (int) Db::name('act_session')->where('is_deleted', 0)->where('type', 3)->min('id');
            if ($activityId > 0 && $activityId !== $defaultId) {
                $session = Db::name('act_session')->where('is_deleted', 0)->find($activityId);
                if (!$session) {
                    $this->error(__('Record not found'));
                }
                $cfg = [
                    'chance'          => intval($data['rg_chance'] ?? 0),
                    'lot_activity_id' => intval($data['rg_lot_activity_id'] ?? 0),
                    'collection_id'   => intval($data['rg_collection_id'] ?? 0),
                    'pre_num'         => $preNum,
                    'pre_collection_id' => $preCol,
                    'inviter_type'    => $inviterType,
                    'inviter_num'     => intval($data['rg_inviter_num'] ?? 0),
                    'inviter_collection_id' => intval($data['rg_inviter_collection_id'] ?? 0),
                    'inviter_pre_collection_id' => intval($data['rg_inviter_pre_collection_id'] ?? 0),
                ];
                Db::name('act_session')->where('id', $activityId)->update([
                    'config' => json_encode($cfg, JSON_UNESCAPED_UNICODE), 'updatetime' => time(),
                ]);
                OperLog::record('注册活动', '保存场次配置', '场次#' . $activityId, (string) $session['title']);
                $this->success();
            }

            foreach ($allow as $name) {
                if (!isset($data[$name])) continue;
                Db::name('setting')->where('name', $name)->update([
                    'value'      => (string) intval($data[$name]),
                    'updatetime' => time(),
                ]);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
        OperLog::record('注册活动', '保存配置', 'register', json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->success();
    }
}
