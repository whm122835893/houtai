<?php

namespace app\api\controller\nft;

use app\common\service\nft\CException;
use app\common\service\nft\UserService;

/**
 * 账号与鉴权（对齐源站 passport 分包）
 *
 * signin/signup 响应：{ token, uid, user:{...账号10字段} }
 */
class Passport extends Cbase
{
    protected array $noNeedLogin = ['signin', 'signup', 'captchaimage', 'captchasms', 'password'];

    /**
     * POST passport/signin —— 账号密码登录
     */
    public function signin(): void
    {
        $account  = trim((string) $this->request->param('account/s', ''));
        $password = $this->decryptPassword((string) $this->request->param('password/s', ''));
        if ($account === '') {
            throw CException::missing('account');
        }

        $this->success('', (new UserService())->login($account, $password));
    }

    /**
     * POST passport/signup —— 注册（注册即登录）
     */
    public function signup(): void
    {
        $phone     = trim((string) $this->request->param('phone/s', ''));
        $code      = trim((string) $this->request->param('code/s', ''));
        $inviter   = trim((string) $this->request->param('inviter/s', ''));
        $password  = $this->decryptPassword((string) $this->request->param('password/s', ''));

        if ($phone === '') {
            throw CException::missing('phone');
        }
        if ($code === '') {
            throw CException::missing('code');
        }
        if (!$this->verifySmsCode($phone, $code)) {
            throw CException::biz('验证码错误或已过期');
        }

        $this->success('', (new UserService())->registerByPhone($phone, $password, $inviter));
    }

    /**
     * POST passport/signout —— 退出登录
     */
    public function signout(): void
    {
        $this->auth->logout();
        $this->success('', true);
    }

    /**
     * GET passport/account —— 当前账号信息
     */
    public function account(): void
    {
        $this->success('', (new UserService())->accountOf((int) $this->auth->id));
    }

    /**
     * PUT passport/secondPassword —— 设置二级密码 {secondPassword, captcha}
     */
    public function secondPassword(): void
    {
        $plain = $this->decryptPassword((string) $this->request->param('secondPassword/s', ''));
        $code  = trim((string) $this->request->param('captcha/s', ''));
        if ($code === '') {
            throw CException::missing('captcha');
        }
        if (!$this->verifySmsCode('', $code)) {
            throw CException::biz('验证码错误或已过期');
        }
        (new UserService())->setSecondPassword((int) $this->auth->id, $plain);
        $this->success('', true);
    }

    /**
     * GET passport/captcha/image —— 图形验证码（携带 RSA 公钥下发）
     */
    public function captchaImage(): void
    {
        $this->success('', [
            'key'       => '',
            'image'     => '',
            'publicKey' => $this->normalizePem((string) config('nft.rsa_public_key', ''), 'PUBLIC KEY'),
        ]);
    }

    /**
     * POST passport/captcha/sms —— 短信验证码
     */
    public function captchaSms(): void
    {
        $phone = trim((string) $this->request->param('phone/s', ''));
        if ($phone === '') {
            throw CException::missing('phone');
        }

        if (config('nft.sms_debug')) {
            // 未接入短信网关：固定测试码，生产环境关闭 sms_debug 并对接短信服务
            $this->success('', ['phone' => $phone, 'code' => '123456', 'expire' => UserService::SMS_EXPIRE]);
        }
        $this->error('短信通道未接入', [], CException::SYSTEM_ERROR);
    }

    /**
     * PUT passport/password —— 重置密码（忘记密码，免登录）{phone, captcha, password}
     *
     * 短信 type=201；RSA 解密后落库（框架 hash_password）
     */
    public function password(): void
    {
        $phone    = trim((string) $this->request->param('phone/s', ''));
        $code     = trim((string) $this->request->param('captcha/s', ''));
        $password = $this->decryptPassword((string) $this->request->param('password/s', ''));

        if (!$this->verifySmsCode($phone, $code)) {
            throw CException::biz('验证码错误或已过期');
        }
        $this->success('', (new UserService())->resetPassword($phone, $code, $password));
    }

    /**
     * POST passport/realName —— 设置实名 {realName, idCard}
     */
    public function realName(): void
    {
        $realName = (string) $this->request->param('realName/s', '');
        $idCard   = (string) $this->request->param('idCard/s', '');
        $this->success('', (new UserService())->setRealName((int) $this->auth->id, $realName, $idCard));
    }

    /**
     * POST passport/delete —— 注销账号（软注销 + 踢 token）
     */
    public function delete(): void
    {
        (new UserService())->deleteAccount((int) $this->auth->id);
        $this->auth->logout();
        $this->success('', true);
    }

    /** 校验短信验证码（当前：调试模式固定码；正式接入短信网关后替换本方法） */
    private function verifySmsCode(string $phone, string $code): bool
    {
        return config('nft.sms_debug') && $code === '123456';
    }
}
