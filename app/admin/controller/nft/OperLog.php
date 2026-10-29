<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;

/**
 * 日志审计（只读）
 */
class OperLog extends Backend
{
    protected string $table = 'nft_oper_log';

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 日志列表
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $kw = str_replace('%', '\%', $keyword);
            $where[] = ['admin_name|module', 'like', "%{$kw}%"];
        }

        $total = Db::name($this->table)->where($where)->count();
        $list  = Db::name($this->table)->where($where)
            ->field(['id', 'admin_id', 'admin_name', 'module', 'action', 'target', 'detail', 'ip', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('', ['list' => $list, 'total' => $total]);
    }
}
