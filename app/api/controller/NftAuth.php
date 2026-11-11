<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use think\facade\Db;

/**
 * C 端用户认证
 *
 * 接口：
 *   POST /api/nftauth/register  注册（username + password）
 *   POST /api/nftauth/login     登录（username + password）
 *   GET  /api/nftauth/profile   当前用户信息（需 token）
 *
 * 鉴权方式：请求头 ba-user-token: {登录返回的 token}
 * 说明：当前版本暂不启用验证码/短信，上线前如需合规可在此接入。
 */
class NftAuth extends Frontend
{
    protected array $noNeedLogin = ['register', 'login'];
    protected array $noNeedRight = ['*'];

    /**
     * 注册
     */
    public function register(): void
    {
        $username = $this->request->post('username/s', '');
        $password = (string) $this->request->post('password/s', '');
        $nickname = trim((string) $this->request->post('nickname/s', ''));

        if ($username === '' || $password === '') {
            $this->error('请输入用户名和密码');
        }
        if (strlen((string) $password) < 6 || strlen((string) $password) > 32) {
            $this->error('密码长度需在 6 ~ 32 位之间');
        }

        $extend = [];
        if ($nickname !== '') {
            $extend['nickname'] = mb_substr($nickname, 0, 50);
        }

        $res = $this->auth->register($username, $password, '', '', 1, $extend);
        if ($res !== true) {
            $this->error($this->auth->getError() ?: '注册失败，请更换用户名重试');
        }

        $inviteBonus = $this->afterRegisterBonus((string) $this->request->post('invite_code/s', ''));

        $this->success('注册成功', [
            'token'       => $this->auth->getToken(),
            'userInfo'    => $this->auth->getUserInfo(),
            'invite_bonus'=> $inviteBonus,
        ]);
    }

    /**
     * 注册后附加：邀请码绑定 + 注册/邀请抽奖次数发放
     * 失败不影响注册本身（尽力而为）。
     */
    private function afterRegisterBonus(string $inviteCode): array
    {
        $uid = (int) $this->auth->id;
        $out = ['invited_by' => 0, 'register_chances' => 0];
        try {
            // 0. 邀请码兜底生成
            \app\common\service\NftTrade::ensureInviteCode($uid);
            // 1. 新人注册次数（source=2）
            $now  = time();
            $acts = Db::name('lottery_activity')->where('status', 1)
                ->where('start_time', '<=', $now)->where('end_time', '>=', $now)->select()->toArray();
            foreach ($acts as $act) {
                $rules = json_decode($act['chance_rules'] ?: '{}', true) ?: [];
                $reg   = is_array($rules['register'] ?? 0) ? (int) ($rules['register']['num'] ?? 0) : (int) ($rules['register'] ?? 0);
                if ($reg > 0) {
                    \app\common\service\NftTrade::addChance($uid, (int) $act['id'], $reg, 2, 'register');
                    $out['register_chances'] += $reg;
                }
            }

            // 2. 邀请码绑定
            $code = strtoupper(trim($inviteCode));
            if ($code !== '') {
                $inviter = Db::name('user')->where('invite_code', $code)->where('id', '<>', $uid)->find();
                if (!$inviter) {
                    return $out;
                }
                if (!Db::name('invite_record')->where('invitee_id', $uid)->count()) {
                    Db::name('invite_record')->insert([
                        'activity_id'  => 0,
                        'inviter_id'   => (int) $inviter['id'],
                        'invitee_id'   => $uid,
                        'rule_id'      => 0,
                        'reward_status'=> 0,
                        'createtime'   => time(),
                    ]);
                    $out['invited_by'] = (int) $inviter['id'];
                    // 3. 邀请人抽奖次数（source=4）
                    foreach ($acts as $act) {
                        $rules = json_decode($act['chance_rules'] ?: '{}', true) ?: [];
                        $inv   = is_array($rules['invite'] ?? 0) ? (int) ($rules['invite']['num'] ?? 0) : (int) ($rules['invite'] ?? 0);
                        if ($inv > 0) {
                            \app\common\service\NftTrade::addChance((int) $inviter['id'], (int) $act['id'], $inv, 4, 'invite-' . $uid);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // 附加流程失败静默，注册不受影响
        }
        return $out;
    }

    /**
     * 登录
     */
    public function login(): void
    {
        $username = $this->request->post('username/s', '');
        $password = (string) $this->request->post('password/s', '');
        if ($username === '' || $password === '') {
            $this->error('请输入用户名和密码');
        }

        $res = $this->auth->login($username, $password, false);
        if ($res !== true) {
            $this->error($this->auth->getError() ?: '用户名或密码错误');
        }
        $this->success('登录成功', [
            'token'    => $this->auth->getToken(),
            'userInfo' => $this->auth->getUserInfo(),
        ]);
    }

    /**
     * 当前用户信息（需 token）
     */
    public function profile(): void
    {
        $this->success('', ['userInfo' => $this->auth->getUserInfo()]);
    }
}
