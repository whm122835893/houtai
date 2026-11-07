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
    protected array $noNeedLogin = ['signin', 'signup', 'captchaimage', 'captchasms'];

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
            'publicKey' => (string) config('nft.rsa_public_key', ''),
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

    /** 校验短信验证码（当前：调试模式固定码；正式接入短信网关后替换本方法） */
    private function verifySmsCode(string $phone, string $code): bool
    {
        return config('nft.sms_debug') && $code === '123456';
    }
}
