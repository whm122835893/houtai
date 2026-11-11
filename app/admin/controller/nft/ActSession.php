<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;

/**
 * 多场次活动管理（act_session）
 * type: 1=邀请有礼 2=每日签到 3=新人注册礼（抽奖场次走 lottery_activity）
 * 同类型同时只允许一个启用场次；空配置（config='{}' 且无关联规则）不允许开启。
 * 开关同步 setting（invite_reward_enable / ck_enabled / rg_enabled）保证 C 端旧读取兼容。
 */
class ActSession extends Backend
{
    protected array $noNeedPermission = ['index', 'save', 'del', 'toggle'];

    /** type => 同步的 setting 键 */
    protected array $syncMap = [
        1 => 'invite_reward_enable',
        2 => 'ck_enabled',
        3 => 'rg_enabled',
    ];

    /**
     * 场次列表（可按 type 过滤）
     */
    public function index(): void
    {
        $type = $this->request->get('type/d', 0);
        $query = Db::name('act_session')->where('is_deleted', 0)
            ->field(['id', 'type', 'title', 'start_time', 'end_time', 'status', 'config', 'createtime']);
        if ($type > 0) {
            $query->where('type', $type);
        }
        $list = $query->order('id', 'asc')->select()->toArray();
        foreach ($list as &$row) {
            $row['has_config'] = $this->hasConfig((int) $row['type'], (int) $row['id'], (string) $row['config']);
            unset($row['config']);
        }
        unset($row);
        $this->success('', ['list' => $list]);
    }

    /**
     * 新建/编辑场次（id=0 新建，默认关闭）
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id      = $this->request->post('id/d', 0);
        $type    = $this->request->post('type/d', 0);
        $title   = trim((string) $this->request->post('title/s', ''));
        $start   = $this->request->post('start_time/d', 0);
        $end     = $this->request->post('end_time/d', 0);

        if (!isset($this->syncMap[$type])) {
            $this->error('无效的活动类型');
        }
        if ($title === '') {
            $this->error('请输入活动名称');
        }
        if ($end > 0 && $start > 0 && $end <= $start) {
            $this->error('结束时间必须晚于开始时间');
        }
        $now = time();
        if ($id > 0) {
            $row = Db::name('act_session')->where('is_deleted', 0)->find($id);
            if (!$row) {
                $this->error(__('Record not found'));
            }
            Db::name('act_session')->where('id', $id)->update([
                'title' => $title, 'start_time' => $start, 'end_time' => $end, 'updatetime' => $now,
            ]);
            OperLog::record('活动场次', '编辑', '场次#' . $id, $title);
        } else {
            $id = (int) Db::name('act_session')->insertGetId([
                'type' => $type, 'title' => $title, 'start_time' => $start, 'end_time' => $end,
                'status' => 0, 'config' => '{}', 'admin_id' => $this->auth->id,
                'createtime' => $now, 'updatetime' => $now, 'is_deleted' => 0,
            ]);
            OperLog::record('活动场次', '新建', '场次#' . $id, $title);
        }
        $this->success('保存成功', ['id' => $id]);
    }

    /**
     * 删除场次（启用中的不可删）
     */
    public function del(): void
    {
        $id  = $this->request->post('id/d', 0);
        $row = Db::name('act_session')->where('is_deleted', 0)->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ((int) $row['status'] === 1) {
            $this->error('启用中的场次不能删除，请先关闭');
        }
        Db::name('act_session')->where('id', $id)->update(['is_deleted' => 1, 'updatetime' => time()]);
        // 场次删除后，挂在该场次的规则一并移除（场次不可恢复，规则随之失效）
        $defaultId = (int) Db::name('act_session')->where('is_deleted', 0)->where('type', (int) $row['type'])->min('id');
        $aid = ($id === $defaultId) ? 0 : $id;
        if ((int) $row['type'] === 1) {
            Db::name('invite_rule')->where('activity_id', $aid)->delete();
        } elseif ((int) $row['type'] === 2) {
            Db::name('checkin_rule')->where('activity_id', $aid)->delete();
        }
        OperLog::record('活动场次', '删除', '场次#' . $id, (string) $row['title']);
        $this->success();
    }

    /**
     * 开关场次（同类互斥 + 空配置拦截 + 同步 setting）
     */
    public function toggle(): void
    {
        $id     = $this->request->post('id/d', 0);
        $status = $this->request->post('status/d', 0) ? 1 : 0;
        $row    = Db::name('act_session')->where('is_deleted', 0)->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $type = (int) $row['type'];

        if ($status === 1 && !$this->hasConfig($type, $id, (string) $row['config'])) {
            $this->error('该场次尚未配置奖励，请先完成配置再开启');
        }
        $now = time();
        Db::startTrans();
        try {
            if ($status === 1) {
                // 同类型互斥：先关掉其他场次
                Db::name('act_session')->where('is_deleted', 0)->where('type', $type)->where('id', '<>', $id)->update(['status' => 0, 'updatetime' => $now]);
            }
            Db::name('act_session')->where('id', $id)->update(['status' => $status, 'updatetime' => $now]);
            // 同步模块开关（C 端 setting 读取兼容）
            $moduleOn = (int) Db::name('act_session')->where('is_deleted', 0)->where('type', $type)->where('status', 1)->count() > 0;
            Db::name('setting')->where('name', $this->syncMap[$type])->update(['value' => (string) $moduleOn, 'updatetime' => $now]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('操作失败：' . $e->getMessage());
        }
        OperLog::record('活动场次', $status ? '开启' : '关闭', '场次#' . $id, (string) $row['title']);
        $this->success($status ? '已开启（同类型其他场次已自动关闭）' : '已关闭');
    }

    /**
     * 场次是否有可用配置
     */
    protected function hasConfig(int $type, int $id, string $config): bool
    {
        $cfg = json_decode($config ?: '{}', true);
        if (is_array($cfg) && $cfg !== []) {
            return true;
        }
        // 规则表挂到该场次的记录也算有配置（activity_id=0 归默认场次）
        $defaultId = (int) Db::name('act_session')->where('is_deleted', 0)->where('type', $type)->min('id');
        $aid = ($id === $defaultId) ? 0 : $id;
        return match ($type) {
            1 => (bool) Db::name('invite_rule')->where('activity_id', $aid)->count(),
            2 => (bool) Db::name('checkin_rule')->where('activity_id', $aid)->count(),
            default => false,
        };
    }
}
