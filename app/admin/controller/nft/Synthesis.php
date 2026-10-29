<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\Synthesis;
use app\admin\model\nft\SynthesisRecord;
use app\admin\model\nft\OperLog;

/**
 * 合成活动
 */
class Synthesis extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new Synthesis();
    }

    /**
     * 活动分页（附目标藏品名）
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

        $collectionIds = array_values(array_unique(array_column($list->toArray(), 'target_collection_id')));
        $collections   = $collectionIds ? Db::name('nft_collection')->where('id', 'in', $collectionIds)->column('name', 'id') : [];
        foreach ($list as $row) {
            $row->target_collection_name = $collections[$row->target_collection_id] ?? '';
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 新增/编辑活动（materials 以 JSON 入库）
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id        = $this->request->param('id/d', 0);
        $data      = $this->request->only(['name', 'target_collection_id', 'limit_num', 'status', 'start_time', 'end_time'], 'post');
        $materials = $this->request->post('materials/a', []);
        try {
            validate(\app\admin\validate\nft\Synthesis::class)->scene($id ? 'edit' : 'add')->check($data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        $normalized = [];
        foreach ($materials as $m) {
            $cid = (int) ($m['collection_id'] ?? 0);
            $num = (int) ($m['num'] ?? 0);
            if ($cid > 0 && $num > 0) {
                $normalized[] = ['collection_id' => $cid, 'num' => $num];
            }
        }
        if (!$normalized) {
            $this->error('请至少配置一种材料');
        }
        $data['materials']   = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        $data['start_time']  = !empty($data['start_time']) ? strtotime($data['start_time']) : null;
        $data['end_time']    = !empty($data['end_time']) ? strtotime($data['end_time']) : null;

        if ($id) {
            $row = $this->model->find($id);
            if (!$row) {
                $this->error(__('Record not found'));
            }
            $row->save($data);
            OperLog::record('合成活动', '编辑', '活动#' . $id, $data['name']);
        } else {
            $this->model->save($data);
            OperLog::record('合成活动', '添加', '活动#' . $this->model->id, $data['name']);
        }
        $this->success();
    }

    /**
     * 删除活动
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        $count = $this->model->where('id', 'in', $ids)->delete();
        OperLog::record('合成活动', '删除', '活动#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }

    /**
     * 合成记录分页（附 user、活动名）
     */
    public function records(): void
    {
        $synthesisId = $this->request->get('synthesis_id/d', 0);
        $limit       = $this->request->get('limit/d', 10);
        $page        = $this->request->get('page/d', 1);

        $where = [];
        if ($synthesisId > 0) {
            $where[] = ['synthesis_id', '=', $synthesisId];
        }

        $recordModel = new SynthesisRecord();
        $total       = $recordModel->where($where)->count();
        $list        = $recordModel->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        // 两次查询拼装
        $userIds       = array_values(array_unique(array_column($list, 'user_id')));
        $synthesisIds  = array_values(array_unique(array_column($list, 'synthesis_id')));
        $users         = $userIds ? Db::name('nft_user')->where('id', 'in', $userIds)->column('nickname', 'id') : [];
        $syntheses     = $synthesisIds ? Db::name('nft_synthesis')->where('id', 'in', $synthesisIds)->column('name', 'id') : [];
        foreach ($list as &$row) {
            $row['nickname']      = $users[$row['user_id']] ?? ('用户#' . $row['user_id']);
            $row['synthesis_name'] = $syntheses[$row['synthesis_id']] ?? '-';
        }
        unset($row);

        $this->success('', ['list' => $list, 'total' => $total]);
    }
}
