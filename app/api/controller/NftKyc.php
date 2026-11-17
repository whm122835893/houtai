<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use think\facade\Db;
use app\common\library\Kyc;

/**
 * C 端实名认证
 *
 * 接口（需登录，请求头 ba-user-token）：
 *   GET  /api/nftkyc/status   当前实名状态（none/pending/approved/rejected + 驳回理由）
 *   POST /api/nftkyc/submit   提交实名（real_name + idcard [+ idcard_front/idcard_back 照片]）
 *
 * 审核模式由后台运营参数 kyc_mode 控制：
 *   manual 人工审核（默认） / auto 自动通过 / third 第三方实名库（失败自动降级人工）
 * 未实名禁交易：后台开关 kyc_block_trade=1 时，下单/寄售/转赠前会被 Kyc::ensureTradeAllowed 拦截
 */
class NftKyc extends Frontend
{
    protected array $noNeedLogin = [];
    protected array $noNeedRight = ['*'];

    /**
     * 当前用户实名状态
     */
    public function status(): void
    {
        $st = Kyc::userStatus((int) $this->auth->id);
        $block = (string) (Db::name('setting')->where('name', 'kyc_block_trade')->value('value') ?: '0');
        $this->success('', [
            'status'       => $st['status'],
            'real_name'    => $st['status'] === 'approved' ? $st['real_name'] : '',
            'remark'       => $st['status'] === 'rejected' ? $st['remark'] : '',
            'block_trade'  => $block === '1',
        ]);
    }

    /**
     * 提交实名认证
     */
    public function submit(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $userId = (int) $this->auth->id;
        $st = Kyc::userStatus($userId);
        if ($st['status'] === 'approved') {
            $this->error('您已完成实名认证，无需重复提交');
        }
        if ($st['status'] === 'pending') {
            $this->error('已有实名申请正在审核中，请耐心等待');
        }

        $name   = trim((string) $this->request->post('real_name/s', ''));
        $idcard = trim((string) $this->request->post('idcard/s', ''));
        $front  = trim((string) $this->request->post('idcard_front/s', ''));
        $back   = trim((string) $this->request->post('idcard_back/s', ''));

        try {
            [$status, $message] = Kyc::handle($userId, $name, $idcard, 1, ['front' => $front, 'back' => $back]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        $this->success($message, ['status' => $status === Kyc::STATUS_APPROVED ? 'approved' : ($status === Kyc::STATUS_PENDING ? 'pending' : 'rejected')]);
    }
}
