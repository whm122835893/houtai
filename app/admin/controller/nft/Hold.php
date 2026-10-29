<?php

namespace app\admin\controller\nft;

use app\common\controller\Backend;
use app\admin\model\nft\Hold as HoldModel;
use app\admin\model\nft\User as UserModel;
use app\admin\model\nft\Collection as CollectionModel;

/**
 * 持仓查询（只读）
 */
class Hold extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new HoldModel();
    }

    /**
     * 持仓列表
     */
    public function index(): void
    {
        $sn          = $this->request->get('sn/s', '');
        $collectionId = $this->request->get('collection_id/d', 0);
        $userId      = $this->request->get('user_id/d', 0);
        $status      = $this->request->get('status/d', -1);
        $limit       = $this->request->get('limit/d', 10);
        $page        = $this->request->get('page/d', 1);

        $where = [];
        if ($sn !== '') {
            $where[] = ['sn', 'like', '%' . str_replace('%', '\%', $sn) . '%'];
        }
        if ($collectionId > 0) {
            $where[] = ['collection_id', '=', $collectionId];
        }
        if ($userId > 0) {
            $where[] = ['user_id', '=', $userId];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->field(['id', 'sn', 'collection_id', 'user_id', 'source', 'status', 'sell_order_id', 'mint_time', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 拼装修饰字段
        $rows     = $list->toArray();
        $userIds  = array_values(array_unique(array_filter(array_column($rows, 'user_id'))));
        $colIds   = array_values(array_unique(array_filter(array_column($rows, 'collection_id'))));
        $userMap  = $userIds ? UserModel::where('id', 'in', $userIds)->column('nickname', 'id') : [];
        $colMap   = $colIds ? CollectionModel::where('id', 'in', $colIds)->column('name', 'id') : [];
        foreach ($list as $row) {
            $row->user_name       = $userMap[$row->user_id] ?? ('用户#' . $row->user_id);
            $row->collection_name = $colMap[$row->collection_id] ?? '-';
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }
}
