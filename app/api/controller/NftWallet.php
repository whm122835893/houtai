<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\common\service\NftTrade;
use think\facade\Db;

/**
 * C 端钱包查询：余额概览 / 流水列表
 */
class NftWallet extends Frontend
{
    protected array $noNeedLogin = [];
    protected array $noNeedRight = ["*"];

    /**
     * 钱包概览
     * GET /api/nft_wallet/index
     */
    public function index(): void
    {
        $user = Db::name('user')->where('id', $this->auth->id)->field('balance,total_income,total_expense')->find();
        $this->success('', [
            'balance_yuan'  => NftTrade::yuan((int) $user['balance']),
            'income_yuan'   => NftTrade::yuan((int) $user['total_income']),
            'expense_yuan'  => NftTrade::yuan((int) $user['total_expense']),
            'balance_fen'   => (int) $user['balance'],
        ]);
    }

    /**
     * 流水列表
     * GET /api/nft_wallet/logs?page=&limit=&type=
     */
    public function logs(): void
    {
        $page  = max(1, (int) $this->request->get('page/d', 1));
        $limit = min(50, max(1, (int) $this->request->get('limit/d', 10)));
        $type  = (string) $this->request->get('type/s', '');

        $where = [['user_id', '=', $this->auth->id]];
        if ($type !== '') {
            $where[] = ['type', '=', $type];
        }

        $total = Db::name('wallet_log')->where($where)->count();
        $list  = Db::name('wallet_log')->where($where)
            ->field('id,type,related_no,amount,before,after,remark,createtime')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        foreach ($list as &$row) {
            $row['amount_yuan'] = NftTrade::yuan((int) $row['amount']);
            $row['after_yuan']  = NftTrade::yuan((int) $row['after']);
            $row['type_text']   = [
                'recharge' => '充值', 'consume' => '消费', 'income' => '收款', 'expense' => '支出',
                'freeze' => '冻结', 'unfreeze' => '解冻', 'refund' => '退款', 'admin_adjust' => '后台调整',
            ][$row['type']] ?? $row['type'];
        }
        unset($row);

        $this->success('', ['total' => $total, 'list' => $list]);
    }
}
