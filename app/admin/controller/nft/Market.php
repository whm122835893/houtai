<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\MarketOrder as MarketOrderModel;
use app\admin\model\nft\MarketBuy as MarketBuyModel;
use app\admin\model\nft\User as UserModel;
use app\admin\model\nft\Collection as CollectionModel;
use app\admin\model\nft\Hold as HoldModel;
use app\admin\model\nft\OperLog;

/**
 * 市场寄售管理
 */
class Market extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new MarketOrderModel();
    }

    /**
     * 当前寄售手续费率（setting.sell_fee_rate，%）
     */
    public function fee(): void
    {
        $rate = (string) Db::name('setting')->where('name', 'sell_fee_rate')->value('value');
        $this->success('', ['rate' => $rate !== '' ? $rate : '5']);
    }

    /**
     * 保存寄售手续费率（仅超级管理员；范围 0~100，最多两位小数）
     */
    public function feeSave(): void
    {
        if (!$this->auth->isSuperAdmin()) {
            $this->error('仅超级管理员可修改手续费率', [], 401);
        }
        $rate = $this->request->post('rate/s', '');
        if ($rate === '' || !preg_match('/^(0|[1-9]\d{0,2})(\.\d{1,2})?$/', $rate) || (float) $rate > 100) {
            $this->error('手续费率不合法，应为 0~100 的数字（最多两位小数）');
        }
        $old = (string) Db::name('setting')->where('name', 'sell_fee_rate')->value('value');
        if ($old === '') {
            $this->error('参数不存在（sell_fee_rate）');
        }
        if ($old === $rate) {
            $this->success('手续费率未变化', ['rate' => $rate]);
        }
        Db::name('setting')->where('name', 'sell_fee_rate')->update([
            'value'      => $rate,
            'updatetime' => time(),
        ]);
        OperLog::record('市场寄售', '修改手续费率', 'sell_fee_rate', '寄售手续费率由 ' . $old . '% 变更为 ' . $rate . '%');
        $this->success('手续费率已保存，新挂单成交即按新费率结算', ['rate' => $rate]);
    }

    /**
     * 挂单列表
     */
    public function index(): void
    {
        $status = $this->request->get('status/d', -1);
        $limit  = $this->request->get('limit/d', 10);
        $page   = $this->request->get('page/d', 1);

        $where = [];
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->field(['id', 'order_no', 'seller_id', 'hold_id', 'collection_id', 'price', 'status', 'buyer_id', 'soldtime', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 拼装修饰字段
        $rows    = $list->toArray();
        $sellerIds = array_values(array_unique(array_filter(array_column($rows, 'seller_id'))));
        $colIds    = array_values(array_unique(array_filter(array_column($rows, 'collection_id'))));
        $holdIds   = array_values(array_unique(array_filter(array_column($rows, 'hold_id'))));
        $userMap   = $sellerIds ? UserModel::where('id', 'in', $sellerIds)->column('nickname', 'id') : [];
        $colMap    = $colIds ? CollectionModel::where('id', 'in', $colIds)->column('name', 'id') : [];
        // 寄售限价（C 端挂单时价格不得超上限/低于下限，供后台审核展示）
        $limitMap  = $colIds ? CollectionModel::where('id', 'in', $colIds)->column('resale_price_limit', 'id') : [];
        $minMap    = $colIds ? CollectionModel::where('id', 'in', $colIds)->column('resale_price_min', 'id') : [];
        $holdMap   = $holdIds ? HoldModel::where('id', 'in', $holdIds)->column('sn', 'id') : [];
        foreach ($list as $row) {
            $row->seller_name        = $userMap[$row->seller_id] ?? ('用户#' . $row->seller_id);
            $row->collection_name    = $colMap[$row->collection_id] ?? '-';
            $row->resale_price_limit = (int) ($limitMap[$row->collection_id] ?? 0);
            $row->resale_price_min   = (int) ($minMap[$row->collection_id] ?? 0);
            $row->price_exceeded     = ($row->resale_price_limit > 0 && $row->price > $row->resale_price_limit) ? 1 : 0;
            $row->price_under        = ($row->resale_price_min > 0 && $row->price < $row->resale_price_min) ? 1 : 0;
            $row->hold_sn            = $holdMap[$row->hold_id] ?? '-';
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 挂单下架（同步恢复持仓状态）
     */
    public function off(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ($row->status != 0) {
            $this->error('仅「在售」状态的挂单可下架');
        }

        Db::startTrans();
        try {
            $row->status = 2;
            $row->save();
            if ($row->hold_id) {
                HoldModel::where('id', $row->hold_id)->update(['status' => 0, 'sell_order_id' => 0]);
            }
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        OperLog::record('市场寄售', '下架', '挂单#' . $id, $row->order_no);
        $this->success();
    }

    /**
     * 求购单列表
     */
    public function buys(): void
    {
        $limit = $this->request->get('limit/d', 10);
        $page  = $this->request->get('page/d', 1);

        $total = MarketBuyModel::count();
        $list  = MarketBuyModel::field(['id', 'user_id', 'collection_id', 'price', 'status', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 拼装修饰字段
        $rows   = $list->toArray();
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
}
