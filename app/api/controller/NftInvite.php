<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\common\service\NftTrade;
use think\facade\Db;

/**
 * C 端邀请体系：我的邀请码 / 好友列表 / 规则进度 / 领取奖励
 * 奖励类型 invite_rule.reward_type：1=藏品 2=抽奖次数 3=优先购资格
 * 达标模型：邀请人数每达到 invite_num 门槛一次，可领取一次该规则奖励
 */
class NftInvite extends Frontend
{
    protected array $noNeedLogin = [];
    protected array $noNeedRight = ['*'];

    /**
     * 邀请面板
     * GET /api/nft_invite/index
     */
    public function index(): void
    {
        $uid = (int) $this->auth->id;
        $me  = Db::name('user')->where('id', $uid)->field('id,invite_code')->find();
        $me['invite_code'] = NftTrade::ensureInviteCode($uid);

        $rules = Db::name('invite_rule')->where('status', 1)->order('invite_num', 'asc')->select()->toArray();
        $ruleView = [];
        foreach ($rules as $r) {
            $invited = Db::name('invite_record')->where('inviter_id', $uid)->count();
            $claimed = Db::name('invite_record')->where('inviter_id', $uid)
                ->where('rule_id', (int) $r['id'])->where('reward_status', 1)->count();
            $can = intdiv($invited, max(1, (int) $r['invite_num'])) - $claimed;
            $ruleView[] = [
                'rule_id'      => (int) $r['id'],
                'invite_num'   => (int) $r['invite_num'],
                'reward_type'  => (int) $r['reward_type'],
                'reward_num'   => (int) $r['reward_num'],
                'reward_text'  => $this->rewardText((int) $r['reward_type'], (int) $r['reward_num'], (int) $r['collection_id'], (int) $r['pre_collection_id']),
                'invited'      => $invited,
                'claimed'      => $claimed,
                'claimable'    => max(0, $can),
            ];
        }

        $friends = Db::name('invite_record')->alias('i')
            ->leftJoin('user u', 'u.id = i.invitee_id')
            ->where('i.inviter_id', $uid)
            ->field('i.invitee_id,u.nickname,u.username,i.reward_status,i.createtime')
            ->order('i.id', 'desc')->limit(200)->select()->toArray();

        $this->success('', [
            'enabled'        => NftTrade::cfg('invite_reward_enable', '0') === '1',
            'invite_code'    => $me['invite_code'] ?? '',
            'invited_total'  => Db::name('invite_record')->where('inviter_id', $uid)->count(),
            'rules'          => $ruleView,
            'friends'        => $friends,
        ]);
    }

    /**
     * 领取达标奖励
     * POST /api/nft_invite/claim {rule_id}
     */
    public function claim(): void
    {
        if (NftTrade::cfg('invite_reward_enable', '0') !== '1') {
            $this->error('邀请奖励未开启');
        }
        $uid    = (int) $this->auth->id;
        $ruleId = (int) $this->request->post('rule_id/d', 0);
        $rule   = Db::name('invite_rule')->where('id', $ruleId)->where('status', 1)->find();
        if (!$rule) {
            $this->error('规则不存在或已停用');
        }

        Db::startTrans();
        try {
            Db::name('user')->where('id', $uid)->lock(true)->find();
            $invited = Db::name('invite_record')->where('inviter_id', $uid)->count();
            $claimed = Db::name('invite_record')->where('inviter_id', $uid)
                ->where('rule_id', $ruleId)->where('reward_status', 1)->count();
            $nextTarget = ($claimed + 1) * max(1, (int) $rule['invite_num']);
            if ($invited < $nextTarget) {
                throw new \RuntimeException('还未达到领取条件（需邀请 ' . $nextTarget . ' 人，当前 ' . $invited . ' 人）');
            }

            // 标记达标的那条邀请记录
            $markRow = Db::name('invite_record')->where('inviter_id', $uid)
                ->order('id', 'asc')->page($nextTarget, 1)->find();
            if ($markRow && (int) $markRow['reward_status'] === 0) {
                Db::name('invite_record')->where('id', (int) $markRow['id'])
                    ->update(['reward_status' => 1, 'rule_id' => $ruleId]);
            }

            $fulfill = NftTrade::grantReward($uid, (int) $rule['reward_type'], (int) $rule['reward_num'],
                (int) $rule['collection_id'], (int) $rule['pre_collection_id'], 'IR' . $ruleId, $ruleId);
            Db::commit();
            $this->success('奖励领取成功', ['fulfill' => $fulfill]);
        } catch (\think\exception\HttpResponseException $e) {
            throw $e; // success()/error() 的响应直接放行（其继承 RuntimeException，必须最先捕获）
        } catch (\RuntimeException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('领取失败：' . $e->getMessage());
        }
    }

    private function rewardText(int $type, int $num, int $cid, int $preCid): string
    {
        if ($type === 1) {
            $name = Db::name('collection')->where('id', $cid)->value('name');
            return '藏品「' . ($name ?: ('#' . $cid)) . '」x1';
        }
        if ($type === 2) {
            return '抽奖次数 x' . max(1, $num);
        }
        if ($type === 3) {
            $name = Db::name('collection')->where('id', $preCid > 0 ? $preCid : $cid)->value('name');
            return '「' . ($name ?: '目标藏品') . '」优先购资格 x' . max(1, $num);
        }
        return '无';
    }
}
