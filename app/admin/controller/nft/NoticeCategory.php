<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\NoticeCategory as CatModel;
use app\admin\model\nft\Notice as NoticeModel;
use app\admin\model\nft\OperLog;

/**
 * 公告分类管理
 */
class NoticeCategory extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CatModel();
    }

    /**
     * 分类列表（含各分类在用公告数）
     */
    public function index(): void
    {
        $list = $this->model->where('deletetime', 0)
            ->field(['id', 'title', 'weigh', 'status', 'createtime'])
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        foreach ($list as &$row) {
            $row['notice_count'] = NoticeModel::where('category', $row['id'])->where('deletetime', 0)->count();
        }
        unset($row);
        $this->success('', ['list' => $list]);
    }

    /**
     * 添加/编辑（带 id 为编辑）
     */
    public function save(): void
    {
        $data = $this->request->only(['id', 'title', 'weigh', 'status'], 'post');
        try {
            validate(\app\admin\validate\nft\NoticeCategory::class)->scene('save')->check($data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
        $data['title']  = trim((string) $data['title']);
        $data['weigh']  = max(0, (int) ($data['weigh'] ?? 50));
        $data['status'] = (int) ($data['status'] ?? 1) ? 1 : 0;

        if (!empty($data['id'])) {
            $row = $this->model->where('deletetime', 0)->find((int) $data['id']);
            if (!$row) {
                $this->error('记录不存在');
            }
            unset($data['id']);
            $row->save($data);
            OperLog::record('公告分类', '编辑', '公告分类#' . $row->id, $data['title']);
            $this->success('保存成功', ['id' => $row->id]);
        }

        $row = new CatModel();
        $row->save($data);
        OperLog::record('公告分类', '添加', '公告分类#' . $row->id, $data['title']);
        $this->success('添加成功', ['id' => $row->id]);
    }

    /**
     * 删除（在用分类不可删，防止公告分类悬空）
     */
    public function del(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->where('deletetime', 0)->find($id);
        if (!$row) {
            $this->error('记录不存在');
        }
        $count = NoticeModel::where('category', $id)->where('deletetime', 0)->count();
        if ($count > 0) {
            $this->error('该分类下还有 ' . $count . ' 条公告，请先移出后再删除');
        }
        $title = $row->title;
        $row->save(['deletetime' => time()]);
        OperLog::record('公告分类', '删除', '公告分类#' . $id, $title);
        $this->success('删除成功');
    }

    /**
     * 显隐切换
     */
    public function status(): void
    {
        $id     = $this->request->param('id/d', 0);
        $status = $this->request->param('status/d', 0) ? 1 : 0;
        $row    = $this->model->where('deletetime', 0)->find($id);
        if (!$row) {
            $this->error('记录不存在');
        }
        $row->save(['status' => $status]);
        OperLog::record('公告分类', $status ? '显示' : '隐藏', '公告分类#' . $id, $row->title);
        $this->success('操作成功');
    }
}
