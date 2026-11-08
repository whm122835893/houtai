<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\User as UserModel;
use app\admin\model\nft\WalletLog;
use app\admin\model\nft\OperLog;

/**
 * 平台会员管理
 */
class User extends Backend
{
    protected object $model;

    protected string|array $quickSearchField = ['id', 'username', 'nickname', 'mobile'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new UserModel();
    }

    /**
     * 会员列表
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $where[] = ['username|nickname|mobile|email', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->field(['id', 'nickname', 'username', 'mobile', 'email', 'real_name', 'idcard', 'invite_code', 'balance', 'frozen', 'order_count', 'hold_count', 'status', 'last_login_time', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 会员详情（含钱包/持仓/订单概况）
     */
    public function detail(): void
    {
        $id = $this->request->get('id/d', 0);
        $row = $this->model->where('id', $id)->withoutField('updatetime')->find();
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $this->success('', ['row' => $row]);
    }

    /**
     * 编辑会员
     */
    public function edit(): void
    {
        $id = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ($this->request->isPost()) {
            $data = $this->request->only(['nickname', 'mobile', 'email', 'real_name', 'idcard', 'tags', 'status'], 'post');
            try {
                validate(\app\admin\validate\nft\User::class)->scene('edit')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $row->save($data);
            OperLog::record('用户管理', '编辑', '用户#' . $id, json_encode($data, JSON_UNESCAPED_UNICODE));
            $this->success();
        }
        $this->success('', ['row' => $row]);
    }

    /**
     * 删除（支持批量）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        $count = $this->model->where('id', 'in', $ids)->delete();
        OperLog::record('用户管理', '删除', '用户#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }

    /**
     * 禁用/启用
     */
    public function status(): void
    {
        $id     = $this->request->param('id/d', 0);
        $status = $this->request->param('status/d', 1);
        $row    = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $row->save(['status' => $status ? 1 : 0]);
        OperLog::record('用户管理', $status ? '启用' : '禁用', '用户#' . $id);
        $this->success();
    }

    /**
     * 新增会员（后台补录）
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['nickname', 'username', 'mobile', 'email', 'tags', 'status'], 'post');
            $data['invite_code'] = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));
            try {
                validate(\app\admin\validate\nft\User::class)->scene('add')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $this->model->save($data);
            OperLog::record('用户管理', '添加', '用户#' . $this->model->id, $data['username']);
            $this->success();
        }
        $this->success('', []);
    }

    /**
     * 钱包调整（充值/扣减）
     */
    public function adjust(): void
    {
        $id     = $this->request->param('id/d', 0);
        $amount = $this->request->param('amount/f', 0); // 元，可为负
        $remark = $this->request->param('remark/s', '');
        $row    = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $fen = (int) round($amount * 100);
        if ($fen == 0) {
            $this->error('调整金额不能为 0');
        }
        if ($fen < 0 && $row->balance + $fen < 0) {
            $this->error('余额不足');
        }
        $row->startTrans();
        try {
            $before = $row->balance;
            $row->balance = $before + $fen;
            if ($fen > 0) {
                $row->total_income = $row->total_income + $fen;
            } else {
                $row->total_expense = $row->total_expense + abs($fen);
            }
            $row->save();
            WalletLog::create([
                'user_id'    => $id,
                'type'       => 'admin_adjust',
                'related_no' => '',
                'amount'     => $fen,
                'before'     => $before,
                'after'      => $row->balance,
                'remark'     => $remark ?: '管理员调整',
            ]);
            $row->commit();
        } catch (Throwable $e) {
            $row->rollback();
            $this->error($e->getMessage());
        }
        OperLog::record('用户管理', '钱包调整', '用户#' . $id, $amount . ' 元，' . $remark);
        $this->success();
    }
}
