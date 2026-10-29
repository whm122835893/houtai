<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\InviteRule;
use app\admin\model\nft\InviteRecord;
use app\admin\model\nft\OperLog;

/**
 * 邀请有礼
 */
class Invite extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new InviteRule();
    }

    /**
     * 一次返回规则全量 + 邀请记录分页
     */
    public function index(): void
    {
        $limit = $this->request->get('limit/d', 10);
        $page  = $this->request->get('page/d', 1);

        // 规则全量（附奖励藏品名）
        $rules = $this->model->order('invite_num', 'asc')->select();
        $collectionIds = array_values(array_unique(array_column($rules->toArray(), 'collection_id')));
        $collections   = $collectionIds ? Db::name('nft_collection')->where('id', 'in', $collectionIds)->column('name', 'id') : [];
        foreach ($rules as $rule) {
            $rule->collection_name = $collections[$rule->collection_id] ?? '';
        }

        // 邀请记录分页（附邀请人/被邀请人昵称）
        $recordModel = new InviteRecord();
        $total       = $recordModel->count();
        $records     = $recordModel->order('id', 'desc')->page($page, $limit)->select()->toArray();

        $userIds = array_values(array_unique(array_merge(array_column($records, 'inviter_id'), array_column($records, 'invitee_id'))));
        $users   = $userIds ? Db::name('nft_user')->where('id', 'in', $userIds)->column('nickname', 'id') : [];
        foreach ($records as &$row) {
            $row['inviter_nickname'] = $users[$row['inviter_id']] ?? ('用户#' . $row['inviter_id']);
            $row['invitee_nickname'] = $users[$row['invitee_id']] ?? ('用户#' . $row['invitee_id']);
        }
        unset($row);

        $this->success('', [
            'rules'          => $rules,
            'records'        => $records,
            'total'          => $total,
            'invite_enabled' => (int) Db::name('nft_setting')->where('name', 'invite_reward_enable')->value('value') ?: 0,
        ]);
    }

    /**
     * 新增/编辑规则
     * 奖励类型：1=藏品(collection_id) 2=抽奖次数(reward_num) 3=优先购资格(reward_num=可用次数)
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id   = $this->request->param('id/d', 0);
        $data = $this->request->only(['invite_num', 'reward_type', 'collection_id', 'reward_num', 'status'], 'post');
        try {
            validate(\app\admin\validate\nft\InviteRule::class)->scene($id ? 'edit' : 'add')->check($data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        $data['reward_type'] = (int) $data['reward_type'];
        if ($data['reward_type'] == 1) {
            // 藏品奖励
            $data['collection_id'] = (int) ($data['collection_id'] ?: 0);
            if ($data['collection_id'] <= 0) {
                $this->error('请选择奖励藏品');
            }
            $data['reward_num'] = 0;
        } else {
            // 抽奖次数 / 优先购资格
            $data['reward_num'] = max(0, (int) ($data['reward_num'] ?? 0));
            if ($data['reward_num'] <= 0) {
                $this->error($data['reward_type'] == 2 ? '请填写奖励抽奖次数' : '请填写优先购可用次数');
            }
            $data['collection_id'] = 0;
        }

        if ($id) {
            $row = $this->model->find($id);
            if (!$row) {
                $this->error(__('Record not found'));
            }
            $row->save($data);
            OperLog::record('邀请有礼', '编辑规则', '规则#' . $id, json_encode($data, JSON_UNESCAPED_UNICODE));
        } else {
            $this->model->save($data);
            OperLog::record('邀请有礼', '添加规则', '规则#' . $this->model->id, json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        $this->success();
    }

    /**
     * 删除规则
     */
    public function del(): void
    {
        $id = $this->request->param('id/d', 0);
        if (!$id) {
            $this->error(__('Parameter %s can not be empty', ['id']));
        }
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $row->delete();
        OperLog::record('邀请有礼', '删除规则', '规则#' . $id);
        $this->success();
    }
}
