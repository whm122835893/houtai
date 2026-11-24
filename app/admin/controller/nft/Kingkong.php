<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\Kingkong as KkModel;
use app\admin\model\nft\OperLog;
use app\common\library\KkIcons;
use think\facade\Db;

/**
 * C端首页金刚区管理
 */
class Kingkong extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new KkModel();
    }

    /** 列表（含样式配置，一次拉全） */
    public function index(): void
    {
        $list = $this->model->order('weigh', 'desc')->order('id', 'asc')->select()->toArray();
        $cfg  = [
            'kk_enabled'    => (int) (Db::name('setting')->where('name', 'kk_enabled')->value('value') ?: 0),
            'kk_icon_shape' => (string) (Db::name('setting')->where('name', 'kk_icon_shape')->value('value') ?: 'squircle'),
            'icon_keys'     => KkIcons::keys(),
        ];
        $this->success('', ['list' => $list, 'cfg' => $cfg]);
    }

    /** 添加/编辑（带 id 为编辑） */
    public function save(): void
    {
        $data = $this->request->only(['id', 'title', 'icon', 'icon_key', 'link_type', 'link_value', 'badge_type', 'badge_value', 'platform', 'weigh', 'status'], 'post');
        try {
            validate(\app\admin\validate\nft\Kingkong::class)->scene('save')->check($data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
        if (!empty($data['id'])) {
            $row = $this->model->find((int) $data['id']);
            if (!$row) $this->error('记录不存在');
            unset($data['id']);
            $row->save($data);
            OperLog::record('金刚区管理', '编辑', '金刚区#' . $row->id, $row->title);
            $this->success('保存成功', ['id' => $row->id]);
        }

        $row = new KkModel();
        $row->save($data);
        OperLog::record('金刚区管理', '添加', '金刚区#' . $row->id, $data['title']);
        $this->success('添加成功', ['id' => $row->id]);
    }

    /** 删除 */
    public function del(): void
    {
        $id = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) $this->error('记录不存在');
        $title = $row->title;
        $row->delete();
        OperLog::record('金刚区管理', '删除', '金刚区#' . $id, $title);
        $this->success('删除成功');
    }

    /** 显隐切换 */
    public function status(): void
    {
        $id     = $this->request->param('id/d', 0);
        $status = $this->request->param('status/d', 0) ? 1 : 0;
        $row    = $this->model->find($id);
        if (!$row) $this->error('记录不存在');
        $row->save(['status' => $status]);
        OperLog::record('金刚区管理', $status ? '显示' : '隐藏', '金刚区#' . $id, $row->title);
        $this->success();
    }

    /** 排序 */
    public function sort(): void
    {
        $id    = $this->request->param('id/d', 0);
        $weigh = $this->request->param('weigh/d', 0);
        $row   = $this->model->find($id);
        if (!$row) $this->error('记录不存在');
        $row->save(['weigh' => $weigh]);
        $this->success();
    }

    /** 金刚区总开关（关闭后 C 端首页整个金刚区隐藏） */
    public function master(): void
    {
        $enabled = $this->request->param('enabled/d', 0) ? 1 : 0;
        Db::name('setting')->where('name', 'kk_enabled')->update(['value' => (string) $enabled, 'updatetime' => time()]);
        OperLog::record('金刚区管理', '总开关', 'kk_enabled', $enabled ? '开启金刚区（C 端显示）' : '关闭金刚区（C 端隐藏）');
        $this->success($enabled ? '已开启，C 端将显示金刚区' : '已关闭，C 端将隐藏整个金刚区', ['enabled' => $enabled]);
    }

    /** 图标样式模板（circle/squircle/plain），随运营参数 brand 组存储 */
    public function shape(): void
    {
        $shape = (string) $this->request->param('shape/s', '');
        if (!in_array($shape, ['circle', 'squircle', 'plain'], true)) {
            $this->error('样式模板不合法（circle/squircle/plain）');
        }
        Db::name('setting')->where('name', 'kk_icon_shape')->update(['value' => $shape, 'updatetime' => time()]);
        OperLog::record('金刚区管理', '切换图标样式', 'kk_icon_shape', '金刚区图标样式切换为：' . $shape);
        $this->success('已生效', ['shape' => $shape]);
    }
}
