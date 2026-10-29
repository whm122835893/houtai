<?php

namespace app\admin\controller\nft;

use app\common\controller\Backend;
use app\admin\model\nft\Order as OrderModel;
use app\admin\model\nft\User as UserModel;
use app\admin\model\nft\Collection as CollectionModel;

/**
 * 订单管理（只读）
 */
class Order extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new OrderModel();
    }

    /**
     * 订单列表
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $type    = $this->request->get('type/d', 0);
        $status  = $this->request->get('status/d', -1);
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $where[] = ['order_no|pay_no', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($type > 0) {
            $where[] = ['type', '=', $type];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->field(['id', 'order_no', 'user_id', 'type', 'collection_id', 'num', 'amount', 'pay_type', 'pay_no', 'status', 'paytime', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 拼装买家昵称与藏品名
        $rows = $list->toArray();
        $userIds = array_values(array_unique(array_filter(array_column($rows, 'user_id'))));
        $colIds  = array_values(array_unique(array_filter(array_column($rows, 'collection_id'))));
        $userMap = $userIds ? UserModel::where('id', 'in', $userIds)->column('nickname', 'id') : [];
        $colMap  = $colIds ? CollectionModel::where('id', 'in', $colIds)->column('name', 'id') : [];
        foreach ($list as $row) {
            $row->user_name       = $userMap[$row->user_id] ?? ('用户#' . $row->user_id);
            $row->collection_name = $colMap[$row->collection_id] ?? '-';
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 订单详情（全字段 + 买家 + 藏品）
     */
    public function detail(): void
    {
        $id  = $this->request->get('id/d', 0);
        $row = $this->model->where('id', $id)->withoutField('updatetime')->find();
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $user = $row->user_id ? UserModel::where('id', $row->user_id)->field(['id', 'nickname', 'username', 'mobile'])->find() : null;
        $collection = $row->collection_id ? CollectionModel::where('id', $row->collection_id)->field(['id', 'name', 'cover', 'author', 'price'])->find() : null;
        $this->success('', ['row' => $row, 'user' => $user, 'collection' => $collection]);
    }
}
