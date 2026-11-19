<?php

namespace app\common\library;

use ba\Captcha;
use ba\ClickCaptcha;
use Throwable;

/**
 * 验证码中心
 *
 * 分场景独立开关（存 config 表 captcha 组，get_sys_config 可读且自动清缓存）：
 *   captcha_admin_login   后台管理员登录验证码
 *   captcha_user_login    C端会员登录验证码
 *   captcha_user_register C端会员注册验证码
 *   captcha_ems_send      发送短信/邮件验证码前需先过验证码
 *
 * 验证形式（captcha_mode）：
 *   click   点选式验证码（复用 BuildAdmin 自带 ClickCaptcha 及 /api/common/clickCaptcha 接口）
 *   graphic 字符图形验证码（4 位数字，C 端经 /api/nftcaptcha/image 获取 base64 图）
 */
class CaptchaCenter
{
    /**
     * 全部场景（后台配置页按此渲染）
     */
    public const SCENES = ['admin_login', 'user_login', 'user_register', 'ems_send'];

    /**
     * 场景开关是否开启
     */
    public static function sceneOn(string $scene): bool
    {
        if (!in_array($scene, self::SCENES)) {
            return false;
        }
        return get_sys_config('captcha_' . $scene) == '1';
    }

    /**
     * 验证形式：click | graphic
     */
    public static function mode(): string
    {
        return (string) get_sys_config('captcha_mode') === 'graphic' ? 'graphic' : 'click';
    }

    /**
     * 按配置模式校验当前请求中的验证码
     * @return string 验证通过返回空串；未通过/未填写返回错误文案
     */
    public static function verifyRequest(string $scene, $request): string
    {
        if (!self::sceneOn($scene)) {
            return '';
        }

        if (self::mode() === 'click') {
            $id   = (string) $request->post('captchaId', '');
            $info = (string) $request->post('captchaInfo', '');
            if ($id === '' || $info === '') {
                return '请先完成点选式验证码';
            }
            try {
                $captcha = new ClickCaptcha();
                if ($captcha->check($id, $info)) {
                    return '';
                }
            } catch (Throwable) {
                return '验证码校验异常，请重试';
            }
            return '验证码错误或已过期，请重新验证';
        }

        $key  = (string) $request->post('captchaKey', '');
        $code = (string) $request->post('captcha', '');
        if ($key === '' || $code === '') {
            return '请填写图形验证码';
        }
        $captcha = new Captcha([
            'codeSet'  => '123456789',
            'fontSize' => 22,
            'useCurve' => false,
            'useNoise' => true,
            'length'   => 4,
            'bg'       => [255, 255, 255],
        ]);
        try {
            if ($captcha->check($code, $key, Captcha::GRAPHIC_CAPTCHA)) {
                return '';
            }
        } catch (Throwable) {
            return '验证码校验异常，请重试';
        }
        return '图形验证码错误或已过期，请重新获取';
    }
}
