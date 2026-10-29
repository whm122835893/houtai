<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\Category as CategoryModel;
use app\admin\model\nft\Collection as CollectionModel;
use app\admin\model\nft\OperLog;

/**
 * 藏品分类管理
 */
class Category extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CategoryModel();
    }

    /**
     * 分类列表（附藏品计数）
     */
    public function index(): void
    {
        $limit = $this->request->get('limit/d', 10);
        $page  = $this->request->get('page/d', 1);

        $total = $this->model->count();
        $list  = $this->model
            ->field(['id', 'name', 'weigh', 'status', 'createtime'])
            ->order('weigh', 'desc')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 附藏品计数
        $ids = array_column($list->toArray(), 'id');
        $countMap = $ids ? CollectionModel::where('category_id', 'in', $ids)->group('category_id')->column('COUNT(*)', 'category_id') : [];
        foreach ($list as $row) {
            $row->count_collection = (int) ($countMap[$row->id] ?? 0);
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 新增分类
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['name', 'weigh', 'status'], 'post');
            try {
                validate(\app\admin\validate\nft\Category::class)->scene('add')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $data['weigh'] = (int) ($data['weigh'] ?? 0);
            $data['status'] = (int) ($data['status'] ?? 1);
            $this->model->save($data);
            OperLog::record('分类管理', '添加', '分类#' . $this->model->id, $data['name']);
            $this->success();
        }
        $this->success('', []);
    }

    /**
     * 编辑分类
     */
    public function edit(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ($this->request->isPost()) {
            $data = $this->request->only(['name', 'weigh', 'status'], 'post');
            try {
                validate(\app\admin\validate\nft\Category::class)->scene('edit')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $data['weigh']  = (int) ($data['weigh'] ?? 0);
            $data['status'] = (int) ($data['status'] ?? 1);
            $row->save($data);
            OperLog::record('分类管理', '编辑', '分类#' . $id, $data['name']);
            $this->success();
        }
        $this->success('', ['row' => $row]);
    }

    /**
     * 删除（引用校验）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        $refCount = CollectionModel::where('category_id', 'in', $ids)->count();
        if ($refCount > 0) {
            $this->error('该分类下有 ' . $refCount . ' 个藏品，不能删除');
        }
        $count = $this->model->where('id', 'in', $ids)->delete();
        OperLog::record('分类管理', '删除', '分类#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }
}
