<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\WalletLog;
use app\admin\model\nft\OperLog;

/**
 * 钱包流水管理
 */
class Wallet extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new WalletLog();
    }

    /**
     * 钱包列表（基于会员表的余额/收支视图）
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $where[] = ['nickname|username|mobile', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }

        $total = Db::name('nft_user')->where($where)->count();
        $list  = Db::name('nft_user')->where($where)
            ->field(['id', 'nickname', 'username', 'mobile', 'balance', 'frozen', 'total_income', 'total_expense', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 某会员的钱包流水分页
     */
    public function logs(): void
    {
        $userId = $this->request->get('user_id/d', 0);
        if (!$userId) {
            $this->error('缺少 user_id 参数');
        }
        $type = $this->request->get('type/s', '');
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 5);

        $where = [['user_id', '=', $userId]];
        if ($type !== '') {
            $where[] = ['type', '=', $type];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 余额调整（事务内更新余额与累计收支，并写入流水）
     */
    public function adjust(): void
    {
        $id     = $this->request->param('id/d', 0);
        $amount = $this->request->param('amount/f', 0); // 元，可为负
        $remark = $this->request->param('remark/s', '');

        $fen = (int) bcmul((string) $amount, '100'); // 元转分
        if ($fen == 0) {
            $this->error('调整金额不能为 0');
        }

        $user = Db::name('nft_user')->where('id', $id)->find();
        if (!$user) {
            $this->error(__('Record not found'));
        }
        if ($fen < 0 && $user['balance'] + $fen < 0) {
            $this->error('余额不足');
        }

        Db::startTrans();
        try {
            $before = (int) $user['balance'];
            $after  = $before + $fen;
            Db::name('nft_user')->where('id', $id)->update([
                'balance'       => $after,
                'total_income'  => $fen > 0 ? $user['total_income'] + $fen : $user['total_income'],
                'total_expense' => $fen < 0 ? $user['total_expense'] + abs($fen) : $user['total_expense'],
            ]);
            $this->model->create([
                'user_id'    => $id,
                'type'       => 'admin_adjust',
                'related_no' => '',
                'amount'     => $fen,
                'before'     => $before,
                'after'      => $after,
                'remark'     => $remark ?: '管理员调整',
            ]);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        OperLog::record('钱包流水', '余额调整', '用户#' . $id, $amount . ' 元，' . $remark);
        $this->success();
    }
}
