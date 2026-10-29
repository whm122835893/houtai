<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\Notice as NoticeModel;
use app\admin\model\nft\OperLog;

/**
 * 公告管理
 */
class Notice extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new NoticeModel();
    }

    /**
     * 公告列表
     */
    public function index(): void
    {
        $keyword  = $this->request->get('keyword/s', '');
        $category = $this->request->get('category/d', 0);
        $status   = $this->request->get('status/d', -1);
        $limit    = $this->request->get('limit/d', 10);
        $page     = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $where[] = ['title', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($category > 0) {
            $where[] = ['category', '=', $category];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->field(['id', 'title', 'category', 'is_top', 'status', 'publish_time', 'createtime'])
            ->order('is_top', 'desc')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 发布公告
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['title', 'category', 'content', 'is_top', 'status', 'publish_time'], 'post');
            try {
                validate(\app\admin\validate\nft\Notice::class)->scene('add')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $data['category']     = (int) ($data['category'] ?? 1) ?: 1;
            $data['is_top']       = (int) ($data['is_top'] ?? 0) ? 1 : 0;
            $data['status']       = (int) ($data['status'] ?? 1) ? 1 : 0;
            $data['publish_time'] = !empty($data['publish_time']) ? strtotime($data['publish_time']) : time();

            $this->model->save($data);
            OperLog::record('公告管理', '添加', '公告#' . $this->model->id, $data['title']);
            $this->success();
        }
        $this->success('', []);
    }

    /**
     * 编辑公告
     */
    public function edit(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ($this->request->isPost()) {
            $data = $this->request->only(['title', 'category', 'content', 'is_top', 'status', 'publish_time'], 'post');
            try {
                validate(\app\admin\validate\nft\Notice::class)->scene('edit')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $data['category']     = (int) ($data['category'] ?? 1) ?: 1;
            $data['is_top']       = (int) ($data['is_top'] ?? 0) ? 1 : 0;
            $data['status']       = (int) ($data['status'] ?? 1) ? 1 : 0;
            $data['publish_time'] = !empty($data['publish_time']) ? strtotime($data['publish_time']) : $row->publish_time;

            $row->save($data);
            OperLog::record('公告管理', '编辑', '公告#' . $id, $data['title']);
            $this->success();
        }
        $this->success('', ['row' => $row]);
    }

    /**
     * 删除公告（支持批量）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        $count = $this->model->where('id', 'in', $ids)->delete();
        OperLog::record('公告管理', '删除', '公告#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }
}
