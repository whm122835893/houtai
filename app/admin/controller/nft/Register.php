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
     * 无需模型（配置存 ba_nft_setting，记录走 Db 查询）
     */
    protected object $model;

    public function index(): void
    {
        $configNames = ['rg_enabled', 'rg_chance', 'rg_lot_activity_id', 'rg_collection_id', 'rg_pre_num', 'rg_pre_collection_id', 'rg_inviter_type', 'rg_inviter_num', 'rg_inviter_collection_id'];
        $config      = Db::name('nft_setting')->whereIn('name', $configNames)->column('value', 'name');

        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $userIds = Db::name('nft_user')->where('nickname|username', 'like', "%{$keyword}%")->column('id');
            $where[] = ['user_id', 'in', $userIds ?: [0]];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = Db::name('nft_register_log')->where($where)->count();
        $logs  = Db::name('nft_register_log')->where($where)->order('id', 'desc')->page($page, $limit)->select()->toArray();
        $users = Db::name('nft_user')->whereIn('id', array_unique(array_column($logs, 'user_id')))->column('nickname', 'id');
        $invi  = Db::name('nft_user')->whereIn('id', array_unique(array_filter(array_column($logs, 'inviter_id'))))->column('nickname', 'id');
        foreach ($logs as &$log) {
            $log['nickname']      = $users[$log['user_id']] ?? '';
            $log['inviter_nickname'] = $invi[$log['inviter_id']] ?? '';
        }
        unset($log);

        $this->success('', ['config' => $config, 'logs' => $logs, 'total' => $total]);
    }

    /**
     * 保存配置
     * 新人礼包 = 抽奖次数(rg_chance，绑定抽奖活动 rg_lot_activity_id，0=通用；绑定场次未开启时次数待生效)
     *          + 藏品(rg_collection_id) + 优先购资格(rg_pre_num，目标藏品 rg_pre_collection_id，可预配置未发售藏品)
     * 邀请人奖励 = 类型(rg_inviter_type: 1=抽奖次数 2=藏品 3=优先购资格) + 数量 + 藏品ID
     */
    public function save(): void
    {
        $data  = $this->request->post();
        $allow = ['rg_enabled', 'rg_chance', 'rg_lot_activity_id', 'rg_collection_id', 'rg_pre_num', 'rg_pre_collection_id', 'rg_inviter_type', 'rg_inviter_num', 'rg_inviter_collection_id'];
        try {
            $inviterType = intval($data['rg_inviter_type'] ?? 1);
            if ($inviterType < 1 || $inviterType > 3) {
                $this->error('邀请人奖励类型错误');
            }
            if ($inviterType == 2 && intval($data['rg_inviter_collection_id'] ?? 0) <= 0) {
                $this->error('邀请人藏品奖励需选择藏品');
            }
            $preNum  = intval($data['rg_pre_num'] ?? 0);
            $preCol  = intval($data['rg_pre_collection_id'] ?? 0);
            if ($preNum > 0 && $preCol <= 0) {
                $this->error('优先购资格需选择目标藏品（未发售藏品可预配置）');
            }
            foreach ($allow as $name) {
                if (!isset($data[$name])) continue;
                Db::name('nft_setting')->where('name', $name)->update([
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
