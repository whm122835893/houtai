<?php

namespace app\api\controller;

use Throwable;
use think\facade\Db;
use app\common\controller\Frontend;
use app\common\service\NftTrade;

/**
 * C 端转赠
 *
 * 接口：
 *   GET  /api/nft_transfer/records        我的转赠记录（发出+收到，需 token）
 *   POST /api/nft_transfer/create         发起转赠 {hold_id, to_mobile}（需 token）
 *   POST /api/nft_transfer/accept         接收 {transfer_no}（需 token，接收方）
 *   POST /api/nft_transfer/cancel         撤销 {transfer_no}（需 token，发起方）
 *
 * 规则：collection.transfer_enabled 单品开关 → 手续费读 setting.transfer_fee（0=免费，从发起方余额扣）
 * 流程：发起后持仓锁定（status=2），接收后过户；发起方可随时撤销。
 */
class NftTransfer extends Frontend
{
    protected array $noNeedLogin = [];
    protected array $noNeedRight = ['*'];

    /**
     * 我的转赠记录
     */
    public function records(): void
    {
        $limit = min(100, max(1, $this->request->get('limit/d', 20)));
        $page  = max(1, $this->request->get('page/d', 1));
        $uid   = $this->auth->id;
        $query = Db::name('transfer')->alias('t')
            ->leftJoin('collection c', 'c.id = t.collection_id')
            ->where('t.is_deleted', 0)
            ->whereRaw('(t.from_user_id = ' . (int) $uid . ' OR t.to_user_id = ' . (int) $uid . ')');
        $total = $query->count();
        $list  = $query
            ->field('t.id,t.transfer_no,t.collection_id,c.name as collection_name,c.cover,t.hold_id,t.from_user_id,t.to_user_id,t.to_mobile,t.status,t.done_time,t.createtime')
            ->order('t.id', 'desc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$row) {
            $row['direction'] = ((int) $row['from_user_id'] === (int) $uid) ? 'out' : 'in';
            $row['status_txt'] = ['0' => '待接收', '1' => '已完成', '2' => '已撤销', '3' => '已取消'][(string) $row['status']] ?? '未知';
        }
        unset($row);
        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 发起转赠
     */
    public function create(): void
    {
        $holdId   = $this->request->post('hold_id/d', 0);
        $toMobile = trim((string) $this->request->post('to_mobile/s', ''));

        Db::startTrans();
        try {
            $hold = Db::name('hold')->where('id', $holdId)->lock(true)->find();
            if (!$hold || (int) $hold['user_id'] !== (int) $this->auth->id) {
                throw new \RuntimeException('持仓不存在');
            }
            if ((int) $hold['status'] !== 0) {
                throw new \RuntimeException('该藏品当前不可转赠（寄售中/锁定/已回收）');
            }
            $col = Db::name('collection')->where('id', (int) $hold['collection_id'])->find();
            if (!$col || (int) $col['transfer_enabled'] !== 1) {
                throw new \RuntimeException('该藏品未开放转赠');
            }
            if (!preg_match('/^1[3-9]\d{9}$/', $toMobile)) {
                throw new \RuntimeException('请输入正确的对方手机号');
            }
            if ((int) $this->auth->id > 0) {
                $myMobile = (string) Db::name('user')->where('id', $this->auth->id)->value('mobile');
                if ($myMobile !== '' && $myMobile === $toMobile) {
                    throw new \RuntimeException('不能转赠给自己');
                }
            }
            $toUser = Db::name('user')->where('mobile', $toMobile)->find();
            if (!$toUser) {
                throw new \RuntimeException('对方手机号尚未注册本平台');
            }

            // 手续费（元 → 分），从发起方余额扣
            $feeYuan = (float) NftTrade::cfg('transfer_fee', '0');
            $feeFen  = (int) round($feeYuan * 100);
            $transferNo = 'TR' . date('YmdHis') . $this->auth->id . mt_rand(100, 999);
            while (Db::name('transfer')->where('transfer_no', $transferNo)->count() > 0) {
                $transferNo = 'TR' . date('YmdHis') . $this->auth->id . mt_rand(1000, 9999);
            }

            $now = time();
            if ($feeFen > 0) {
                NftTrade::walletMove((int) $this->auth->id, 'expense', -$feeFen, $transferNo, '转赠手续费「' . ($col['name'] ?? '') . '」', true);
            }
            Db::name('hold')->where('id', $holdId)->update(['status' => 2, 'updatetime' => $now]); // 已锁定
            Db::name('transfer')->insert([
                'transfer_no'  => $transferNo,
                'hold_id'      => $holdId,
                'collection_id'=> (int) $hold['collection_id'],
                'from_user_id' => $this->auth->id,
                'to_user_id'   => (int) $toUser['id'],
                'to_mobile'    => $toMobile,
                'status'       => 0, // 待接收
                'createtime'   => $now,
                'updatetime'   => $now,
            ]);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '转赠发起失败');
        }
        $this->success('转赠已发起，等待对方接收', [
            'transfer_no' => $transferNo ?? '',
            'fee'         => NftTrade::yuan($feeFen ?? 0),
        ]);
    }

    /**
     * 接收转赠
     */
    public function accept(): void
    {
        $no = trim((string) $this->request->post('transfer_no/s', ''));
        Db::startTrans();
        try {
            $t = Db::name('transfer')->where('transfer_no', $no)->where('is_deleted', 0)->lock(true)->find();
            if (!$t || (int) $t['to_user_id'] !== (int) $this->auth->id) {
                throw new \RuntimeException('转赠单不存在');
            }
            if ((int) $t['status'] !== 0) {
                throw new \RuntimeException('该转赠已不是待接收状态');
            }
            $hold = Db::name('hold')->where('id', (int) $t['hold_id'])->lock(true)->find();
            if (!$hold || (int) $hold['status'] !== 2) {
                throw new \RuntimeException('藏品持仓状态异常');
            }
            $now = time();
            Db::name('transfer')->where('id', (int) $t['id'])->update(['status' => 1, 'done_time' => $now, 'updatetime' => $now]);
            Db::name('hold')->where('id', (int) $hold['id'])->update(['user_id' => (int) $t['to_user_id'], 'status' => 0, 'updatetime' => $now]);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '接收失败');
        }
        $this->success('接收成功，藏品已入仓');
    }

    /**
     * 撤销转赠（发起方）
     */
    public function cancel(): void
    {
        $no = trim((string) $this->request->post('transfer_no/s', ''));
        Db::startTrans();
        try {
            $t = Db::name('transfer')->where('transfer_no', $no)->where('is_deleted', 0)->lock(true)->find();
            if (!$t || (int) $t['from_user_id'] !== (int) $this->auth->id) {
                throw new \RuntimeException('转赠单不存在');
            }
            if ((int) $t['status'] !== 0) {
                throw new \RuntimeException('仅「待接收」状态的转赠可撤销');
            }
            $now = time();
            Db::name('transfer')->where('id', (int) $t['id'])->update(['status' => 3, 'updatetime' => $now]);
            Db::name('hold')->where('id', (int) $t['hold_id'])->where('status', 2)->update(['status' => 0, 'updatetime' => $now]);
            // 已收的手续费随撤销退回
            $feeYuan = (float) NftTrade::cfg('transfer_fee', '0');
            $feeFen  = (int) round($feeYuan * 100);
            if ($feeFen > 0) {
                NftTrade::walletMove((int) $this->auth->id, 'refund', $feeFen, $no, '转赠撤销退回手续费', true);
            }
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '撤销失败');
        }
        $this->success('已撤销，藏品回到持仓');
    }
}
