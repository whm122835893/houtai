<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\common\service\NftTrade;
use think\facade\Db;

/**
 * C 端持仓查询：我的藏品列表 / 详情
 */
class NftHold extends Frontend
{
    protected array $noNeedLogin = [];
    protected array $noNeedRight = ["*"];

    /**
     * 我的持仓列表
     * GET /api/nft_hold/index?page=&limit=&status=
     */
    public function index(): void
    {
        $page   = max(1, (int) $this->request->get('page/d', 1));
        $limit  = min(50, max(1, (int) $this->request->get('limit/d', 10)));
        $status = $this->request->get('status', '');

        $where = [['h.user_id', '=', $this->auth->id]];
        if ($status !== '' && $status !== null) {
            $where[] = ['h.status', '=', (int) $status];
        }

        $query = Db::name('hold')->alias('h')
            ->leftJoin('collection c', 'c.id = h.collection_id')
            ->where($where);
        $total = (clone $query)->count();
        $list  = $query
            ->field('h.id,h.sn,h.collection_id,h.status,h.mint_time,h.createtime,c.name,c.cover,c.price,c.resale')
            ->order('h.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        foreach ($list as &$row) {
            $row['price_yuan'] = NftTrade::yuan((int) $row['price']);
            $row['status_text'] = [0 => '持有', 1 => '寄售中', 2 => '已锁定', 3 => '已回收'][(int) $row['status']] ?? '未知';
            $row['can_sell']    = (int) $row['resale'] === 1 && (int) $row['status'] === 0;
        }
        unset($row);

        $this->success('', [
            'total' => $total,
            'list'  => $list,
        ]);
    }

    /**
     * 持仓详情
     * GET /api/nft_hold/detail?id=
     */
    public function detail(): void
    {
        $id = (int) $this->request->get('id/d', 0);
        $hold = Db::name('hold')->where('id', $id)->where('user_id', $this->auth->id)->find();
        if (!$hold) {
            $this->error('持仓不存在');
        }
        $col = Db::name('collection')->where('id', (int) $hold['collection_id'])->field('id,name,cover,author,publisher,price,resale,resale_price_min,resale_price_limit')->find();
        $hold['collection']  = $col ?: null;
        $hold['price_yuan']  = $col ? NftTrade::yuan((int) $col['price']) : '0.00';
        $hold['status_text'] = [0 => '持有', 1 => '寄售中', 2 => '已锁定', 3 => '已回收'][(int) $hold['status']] ?? '未知';
        $this->success('', $hold);
    }
}
