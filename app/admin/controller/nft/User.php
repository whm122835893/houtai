<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\User as UserModel;
use app\admin\model\nft\WalletLog;
use app\admin\model\nft\OperLog;
use think\facade\Db;

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

        $where[] = ['deletetime', '=', 0];
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
        $count = $this->model->where('id', 'in', $ids)->update(['deletetime' => time()]);
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
     * 重置密码（后台生成随机密码）
     */
    public function resetpwd(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $newPwd = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
        $row->save(['password' => hash_password($newPwd), 'salt' => '']);
        // 密码变更后强制重新登录
        try {
            \app\common\facade\Token::clear('user', $id);
            \app\common\facade\Token::clear('user-refresh', $id);
        } catch (Throwable $e) {
        }
        OperLog::record('用户管理', '重置密码', '用户#' . $id);
        $this->success('密码已重置', ['password' => $newPwd]);
    }

    /**
     * 强制下线（清除该用户全部登录态）
     */
    public function kick(): void
    {
        $id = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        try {
            \app\common\facade\Token::clear('user', $id);
            \app\common\facade\Token::clear('user-refresh', $id);
        } catch (Throwable $e) {
            $this->error('下线失败：' . $e->getMessage());
        }
        OperLog::record('用户管理', '强制下线', '用户#' . $id);
        $this->success('已强制下线');
    }

    /**
     * 导出 CSV（按当前筛选条件）
     */
    public function export(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);
        $where = [];
        if ($keyword !== '') {
            $where[] = ['username|nickname|mobile|email', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }
        $rows = $this->model->where($where)
            ->field(['id', 'nickname', 'username', 'mobile', 'email', 'invite_code', 'balance', 'frozen', 'order_count', 'hold_count', 'status', 'last_login_time', 'createtime'])
            ->order('id', 'desc')
            ->limit(10000)
            ->select()
            ->toArray();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users-' . date('YmdHi') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM 防 Excel 乱码
        fputcsv($out, ['ID', '昵称', '账号', '手机号', '邮箱', '邀请码', '余额(元)', '冻结(元)', '订单数', '持仓数', '状态', '最近登录', '注册时间']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['nickname'], $r['username'], $r['mobile'], $r['email'], $r['invite_code'],
                number_format($r['balance'] / 100, 2, '.', ''), number_format($r['frozen'] / 100, 2, '.', ''),
                $r['order_count'], $r['hold_count'], $r['status'] == 1 ? '正常' : '冻结',
                $r['last_login_time'] ? date('Y-m-d H:i:s', (int) $r['last_login_time']) : '-',
                date('Y-m-d H:i:s', (int) $r['createtime']),
            ]);
        }
        fclose($out);
        exit;
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

    /**
     * Excel/CSV 批量导入
     * 列（与前端模板一致）：昵称* | 账号* | 手机号 | 邮箱 | 密码(可选,默认同账号) | 标签(逗号分隔)
     * 逐行校验（复用 User 验证器 add 场景），坏行跳过并回报
     */
    public function import(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $file = $this->request->file('file');
        if (!$file) {
            $this->error('请选择要导入的表格文件');
        }
        try {
            $rows = \app\common\library\ImportReader::read($file->getPathname(), $file->getOriginalName());
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
        if (count($rows) < 2) {
            $this->error('表格除表头外至少需要 1 行数据');
        }

        $validator = validate(\app\admin\validate\nft\User::class);
        $ok = 0;
        $errors = [];
        $seen = []; // 文件内账号查重
        foreach (array_slice($rows, 1) as $i => $row) {
            $line = $i + 2;
            $get = fn (int $idx): string => trim((string) ($row[$idx] ?? ''));
            [$nickname, $username, $mobile, $email, $password, $tags] = [
                $get(0), $get(1), $get(2), $get(3), $get(4), $get(5),
            ];

            if ($nickname === '' || $username === '') {
                $errors[] = "第{$line}行：昵称与账号不能为空";
                continue;
            }
            if (isset($seen[$username])) {
                $errors[] = "第{$line}行：账号「{$username}」在表格中重复（第 {$seen[$username]} 行已出现）";
                continue;
            }
            $seen[$username] = $line;
            if ($mobile !== '' && !preg_match('/^1\d{10}$/', $mobile)) {
                $errors[] = "第{$line}行：手机号格式错误";
                continue;
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "第{$line}行：邮箱格式错误";
                continue;
            }

            $data = [
                'nickname' => mb_substr($nickname, 0, 50),
                'username' => mb_substr($username, 0, 50),
                'mobile'   => $mobile,
                'email'    => $email,
                'tags'     => $tags,
                'status'   => 1,
            ];
            try {
                $validator->scene('add')->check($data);
            } catch (Throwable $e) {
                $errors[] = "第{$line}行：{$e->getMessage()}";
                continue;
            }

            $data['invite_code'] = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));
            $data['password']    = hash_password($password !== '' ? $password : $username);
            $data['salt']        = '';
            $data['join_time']   = time();
            try {
                $this->model->create($data);
                $ok++;
            } catch (Throwable $e) {
                $errors[] = "第{$line}行：写入失败（{$e->getMessage()}）";
            }
        }

        OperLog::record('用户管理', '批量导入', '', "成功 {$ok} 条，失败 " . count($errors) . ' 条');
        $this->success($ok ? "导入完成：成功 {$ok} 条" . ($errors ? "，失败 " . count($errors) . " 条" : '') : '导入失败：没有有效数据', [
            'success' => $ok,
            'fail'    => count($errors),
            'errors'  => array_slice($errors, 0, 50),
        ]);
    }
}
