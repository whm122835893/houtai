<?php

namespace app\api\controller;

use app\common\controller\Frontend;

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
        $this->success('注册成功', [
            'token'    => $this->auth->getToken(),
            'userInfo' => $this->auth->getUserInfo(),
        ]);
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
