<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\Banner as BannerModel;
use app\admin\model\nft\OperLog;

/**
 * 轮播管理
 */
class Banner extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new BannerModel();
    }

    /**
     * 轮播列表（默认全量按 weigh desc，传 page/limit 时分页）
     */
    public function index(): void
    {
        $page  = $this->request->get('page/d', 0);
        $limit = $this->request->get('limit/d', 0);

        $query = $this->model->field(['id', 'title', 'image', 'link', 'weigh', 'status', 'createtime'])
            ->order('weigh', 'desc')
            ->order('id', 'desc');

        if ($page > 0 && $limit > 0) {
            $total = $this->model->count();
            $list  = $query->page($page, $limit)->select();
            $this->success('', ['list' => $list, 'total' => $total]);
        }
        $list = $query->select();
        $this->success('', ['list' => $list, 'total' => count($list)]);
    }

    /**
     * 添加轮播
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['title', 'image', 'link', 'weigh', 'status'], 'post');
            try {
                validate(\app\admin\validate\nft\Banner::class)->scene('add')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $data['weigh'] = (int) ($data['weigh'] ?? 0);
            $data['status'] = (int) ($data['status'] ?? 1) ? 1 : 0;

            $this->model->save($data);
            OperLog::record('轮播管理', '添加', '轮播#' . $this->model->id, $data['title']);
            $this->success();
        }
        $this->success('', []);
    }

    /**
     * 编辑轮播
     */
    public function edit(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ($this->request->isPost()) {
            $data = $this->request->only(['title', 'image', 'link', 'weigh', 'status'], 'post');
            try {
                validate(\app\admin\validate\nft\Banner::class)->scene('edit')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $data['weigh']  = (int) ($data['weigh'] ?? 0);
            $data['status'] = (int) ($data['status'] ?? 1) ? 1 : 0;

            $row->save($data);
            OperLog::record('轮播管理', '编辑', '轮播#' . $id, $data['title']);
            $this->success();
        }
        $this->success('', ['row' => $row]);
    }

    /**
     * 删除轮播（支持批量）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        $count = $this->model->where('id', 'in', $ids)->delete();
        OperLog::record('轮播管理', '删除', '轮播#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }
}
