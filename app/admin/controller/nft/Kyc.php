<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\common\library\Kyc as KycLib;
use app\admin\model\nft\OperLog;

/**
 * 实名审核（KYC）
 * 审核模式由运营参数 kyc_mode 控制：manual=人工 / auto=自动通过 / third=第三方实名库(失败降级人工)
 * 菜单按钮：index 查看 / audit 审核 / add 补录 / export 导出
 */
class Kyc extends Backend
{
    protected array $noNeedPermission = ['index', 'audit', 'add', 'export', 'cfg'];

    /**
     * 审核列表（status 筛选 + keyword 搜索用户昵称/手机号/姓名/证件号 + 分页 + 统计）
     */
    public function index(): void
    {
        $status  = $this->request->get('status/d', -1);
        $keyword = trim((string) $this->request->get('keyword/s', ''));
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [['deletetime', '=', 0]];
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }
        if ($keyword !== '') {
            $kw      = str_replace('%', '\%', $keyword);
            $userIds = Db::name('user')->whereLike('nickname|username|mobile', "%{$kw}%")->column('id');
            $where[] = function ($q) use ($kw, $userIds) {
                $q->where(function ($q2) use ($userIds) {
                    if ($userIds) {
                        $q2->whereOr('user_id', 'in', $userIds);
                    }
                })->whereOr('real_name|idcard', 'like', "%{$kw}%");
            };
        }

        $total = Db::name('user_kyc')->where($where)->count();
        $list  = Db::name('user_kyc')->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $userIds = array_values(array_unique(array_column($list, 'user_id')));
        $users   = $userIds ? Db::name('user')->where('id', 'in', $userIds)->column('nickname,username,mobile', 'id') : [];
        foreach ($list as &$row) {
            $u = $users[$row['user_id']] ?? [];
            $row['user_nickname'] = $u['nickname'] ?? ('用户#' . $row['user_id']);
            $row['username']      = $u['username'] ?? '';
            $row['mobile']        = $u['mobile'] ?? '';
            $row['source_cn']     = $row['source'] == 2 ? '后台补录' : '用户提交';
            $row['status_cn']     = ['待审核', '已通过', '已驳回'][$row['status']] ?? '未知';
            $row['mode_cn']       = ['' => '-', 'manual' => '人工审核', 'auto' => '自动审核', 'third' => '第三方实名库'][$row['audit_mode']] ?? '-';
        }
        unset($row);

        // 统计
        $stat = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        $rows = Db::name('user_kyc')->where('deletetime', 0)->field('status, count(*) n')->group('status')->select()->toArray();
        foreach ($rows as $r) {
            if ($r['status'] == 0) $stat['pending'] = (int) $r['n'];
            if ($r['status'] == 1) $stat['approved'] = (int) $r['n'];
            if ($r['status'] == 2) $stat['rejected'] = (int) $r['n'];
        }

        $cfg = Db::name('setting')->where('group', 'kyc')->column('value', 'name');
        $mode = (string) ($cfg['kyc_mode'] ?? 'manual');

        $this->success('', ['list' => $list, 'total' => $total, 'stat' => $stat, 'mode' => $mode, 'cfg' => $cfg]);
    }

    /**
     * 保存审核设置（仅允许 kyc 组已知参数）
     */
    public function cfg(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $data = $this->request->post('data/a', []);
        if (!$data) {
            $this->error('没有需要保存的参数');
        }
        $known = Db::name('setting')->where('group', 'kyc')->column('value', 'name');
        $now   = time();
        $changed = 0;
        foreach ($data as $name => $value) {
            if (!isset($known[$name])) {
                continue;
            }
            $value = (string) $value;
            if ($name === 'kyc_mode' && !in_array($value, ['manual', 'auto', 'third'])) {
                $this->error('审核模式取值不合法');
            }
            if (in_array($name, ['kyc_check_dup', 'kyc_block_trade']) && !in_array($value, ['0', '1'])) {
                $this->error('开关取值不合法');
            }
            if ($name === 'kyc_third_key' && $value === '••••••') {
                continue; // 密钥未修改
            }
            if ($known[$name] === $value) {
                continue;
            }
            Db::name('setting')->where('name', $name)->update(['value' => $value, 'updatetime' => $now]);
            $changed++;
        }
        OperLog::record('实名审核', '保存审核设置', '', sprintf('审核模式：%s，查重：%s，禁交易：%s，共更新 %d 项', (string) ($data['kyc_mode'] ?? $known['kyc_mode']), (string) ($data['kyc_check_dup'] ?? $known['kyc_check_dup']), (string) ($data['kyc_block_trade'] ?? $known['kyc_block_trade']), $changed));
        $this->success('审核设置已保存', ['changed' => $changed]);
    }

    /**
     * 审核（status: 1=通过 2=驳回；驳回必须填理由；通过则回写 user 表实名字段）
     */
    public function audit(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id     = $this->request->post('id/d', 0);
        $status = $this->request->post('status/d', 0);
        $remark = trim((string) $this->request->post('remark/s', ''));

        if (!in_array($status, [KycLib::STATUS_APPROVED, KycLib::STATUS_REJECTED])) {
            $this->error('审核结果不合法（1=通过 / 2=驳回）');
        }
        $row = Db::name('user_kyc')->where('id', $id)->where('deletetime', 0)->find();
        if (!$row) {
            $this->error('记录不存在');
        }
        if ($row['status'] != KycLib::STATUS_PENDING) {
            $this->error('该记录已审核，不可重复操作');
        }
        if ($status === KycLib::STATUS_REJECTED && $remark === '') {
            $this->error('驳回必须填写理由');
        }

        $now = time();
        Db::startTrans();
        try {
            Db::name('user_kyc')->where('id', $id)->update([
                'status'       => $status,
                'audit_remark' => $status === KycLib::STATUS_APPROVED ? ($remark ?: '人工审核通过') : $remark,
                'audit_mode'   => 'manual',
                'audit_uid'    => $this->auth->id,
                'audit_time'   => $now,
                'updatetime'   => $now,
            ]);
            if ($status === KycLib::STATUS_APPROVED) {
                Db::name('user')->where('id', $row['user_id'])->update([
                    'real_name'  => $row['real_name'],
                    'idcard'     => $row['idcard'],
                    'updatetime' => $now,
                ]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('审核失败：' . $e->getMessage());
        }

        $act = $status === KycLib::STATUS_APPROVED ? '实名审核通过' : '实名审核驳回';
        OperLog::record('实名审核', $act, 'user_kyc#' . $id, sprintf('用户#%d（%s / %s）%s%s', $row['user_id'], $row['real_name'], $row['idcard'], $act, $remark !== '' ? '，备注：' . $remark : ''));

        $this->success($act, ['id' => $id, 'status' => $status]);
    }

    /**
     * 后台补录（管理员直接给用户录入实名，立即生效）
     */
    public function add(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $userId = $this->request->post('user_id/d', 0);
        $name   = trim((string) $this->request->post('real_name/s', ''));
        $idcard = strtoupper(trim((string) $this->request->post('idcard/s', '')));
        $remark = trim((string) $this->request->post('remark/s', ''));

        if ($userId <= 0 || !Db::name('user')->where('id', $userId)->count()) {
            $this->error('用户不存在');
        }

        try {
            [$status, $message] = KycLib::handle($userId, $name, $idcard, 2);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        if ($status !== KycLib::STATUS_APPROVED) {
            $this->error('补录未生效（当前审核模式：' . $message . '），请检查 kyc_mode 或改走审核流程');
        }
        if ($remark !== '') {
            Db::name('user_kyc')->where('user_id', $userId)->where('status', KycLib::STATUS_APPROVED)->order('id', 'desc')->limit(1)->update(['audit_remark' => $remark]);
        }

        OperLog::record('实名审核', '后台补录实名', 'user#' . $userId, sprintf('用户#%d 补录实名：%s / %s', $userId, $name, $idcard));
        $this->success('补录成功，用户实名已生效');
    }

    /**
     * 导出（返回格式化行，前端生成 CSV）
     */
    public function export(): void
    {
        $status  = $this->request->get('status/d', -1);
        $keyword = trim((string) $this->request->get('keyword/s', ''));

        $where = [['deletetime', '=', 0]];
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }
        if ($keyword !== '') {
            $kw      = str_replace('%', '\%', $keyword);
            $userIds = Db::name('user')->whereLike('nickname|username|mobile', "%{$kw}%")->column('id');
            $where[] = function ($q) use ($kw, $userIds) {
                $q->where(function ($q2) use ($userIds) {
                    if ($userIds) {
                        $q2->whereOr('user_id', 'in', $userIds);
                    }
                })->whereOr('real_name|idcard', 'like', "%{$kw}%");
            };
        }

        $list  = Db::name('user_kyc')->where($where)->order('id', 'desc')->limit(5000)->select()->toArray();
        $users = Db::name('user')->where('id', 'in', array_column($list, 'user_id') ?: [0])->column('nickname,username,mobile', 'id');

        $rows = [['ID', '用户ID', '昵称', '账号', '手机号', '真实姓名', '身份证号', '来源', '审核方式', '状态', '审核备注', '审核时间', '提交时间']];
        foreach ($list as $r) {
            $u = $users[$r['user_id']] ?? [];
            $rows[] = [
                $r['id'], $r['user_id'], $u['nickname'] ?? '', $u['username'] ?? '', $u['mobile'] ?? '',
                $r['real_name'], $r['idcard'],
                $r['source'] == 2 ? '后台补录' : '用户提交',
                ['-', '人工审核', '自动审核', '第三方实名库'][$r['audit_mode']] ?? $r['audit_mode'],
                ['待审核', '已通过', '已驳回'][$r['status']] ?? '未知',
                $r['audit_remark'],
                $r['audit_time'] ? date('Y-m-d H:i:s', (int) $r['audit_time']) : '',
                date('Y-m-d H:i:s', (int) $r['createtime']),
            ];
        }
        $this->success('', ['rows' => $rows]);
    }
}
