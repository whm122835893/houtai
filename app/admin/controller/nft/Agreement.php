<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\Agreement as AgreementModel;
use app\admin\model\nft\OperLog;

/**
 * 协议管理
 */
class Agreement extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AgreementModel();
    }

    /**
     * 协议列表（附版本记录，取自操作日志）
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);

        $where = [['is_deleted', '=', 0]];
        if ($keyword !== '') {
            $where[] = ['name', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $list = $this->model->where($where)
            ->field(['id', 'name', 'version', 'content', 'effective_time', 'status', 'createtime', 'updatetime'])
            ->order('id', 'asc')
            ->select();

        foreach ($list as $row) {
            $row->versions = OperLog::where('module', '协议管理')
                ->where('target', '协议#' . $row->id)
                ->order('id', 'desc')
                ->limit(20)
                ->field('admin_name,action,detail,createtime')
                ->select()
                ->each(function ($item) {
                    // 版本记录：detail 存版本号，action 为变更说明
                    $item->version   = $item->detail;
                    $item->editor    = $item->admin_name ?: '管理员';
                    $item->note      = $item->action;
                    $item->time      = $item->createtime;
                });
            $row->history_count = count($row->versions);
        }

        $this->success('', ['list' => $list, 'total' => count($list)]);
    }

    /**
     * 新增/编辑协议（保存即生效逻辑与原型一致：发布新版本即时生效并记录版本日志）
     */
    public function save(): void
    {
        $id = $this->request->post('id/d', 0);
        if ($id > 0) {
            $row = $this->model->where('is_deleted', 0)->find($id);
            if (!$row) {
                $this->error(__('Record not found'));
            }
        }
        $name = trim((string) $this->request->post('name/s', ''));
        if ($id <= 0 && $name === '') {
            $this->error('请输入协议名称');
        }
        $version = trim((string) $this->request->post('version/s', ''));
        if ($version === '') {
            $this->error('请输入版本号（如 v2.2）');
        }
        $content = (string) $this->request->post('content/s', '');
        if (mb_strlen(trim(strip_tags($content))) < 20) {
            $this->error('协议正文太短，请完善条款内容');
        }
        $status = $this->request->post('status/d', 0) ? 1 : 0;
        $isPublish = $status === 1 && ($id <= 0 || (int) $row->status !== 1);
        // 发布（或首次创建为发布态）时更新生效时间；草稿保持原生效时间
        if ($status) {
            $effective = $this->request->post('effective_time/s', '');
            $effTs = $effective !== '' ? (int) strtotime($effective) : time();
            if (!$effTs) {
                $effTs = time();
            }
        } else {
            $effTs = $id > 0 && $row->effective_time ? (int) $row->effective_time : 0;
        }

        $data = ['version' => $version, 'content' => $content, 'status' => $status, 'effective_time' => $effTs ?: time()];
        if ($id > 0) {
            $data['name'] = $name !== '' ? $name : $row->name;
            $row->save($data);
            OperLog::record('协议管理', $isPublish ? '发布新版本' : '编辑', '协议#' . $id, $version . ' ' . $data['name']);
        } else {
            $data['name']     = $name;
            $data['admin_id'] = $this->auth->id;
            $this->model->save($data);
            OperLog::record('协议管理', $isPublish ? '发布新版本' : '编辑', '协议#' . $this->model->id, $version . ' ' . $name);
        }
        $this->success('', ['id' => $id > 0 ? $id : (int) $this->model->id]);
    }
}
