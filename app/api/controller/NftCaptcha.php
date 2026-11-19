<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\common\library\CaptchaCenter;
use ba\Captcha;
use ba\Random;
use Throwable;

/**
 * C 端验证码服务
 *
 * 接口：
 *   GET  /api/nftcaptcha/enabled?scene=login|register  场景开关探测（sinan 风格 fail-open 友好）
 *   GET  /api/nftcaptcha/image                          获取字符图形验证码（base64）
 *
 * 点选式验证码直接复用 BuildAdmin 公开接口：
 *   GET  /api/common/clickCaptcha?id=xxx   取点选图
 *   POST /api/common/checkClickCaptcha    预校验
 */
class NftCaptcha extends Frontend
{
    protected array $noNeedLogin = ['*'];
    protected array $noNeedRight = ['*'];

    /**
     * 场景开关探测：返回 {enabled, mode}
     */
    public function enabled(): void
    {
        $scene = $this->request->get('scene/s', 'login');
        if ($scene !== 'login' && $scene !== 'register') {
            $this->error('不支持的验证码场景');
        }
        $this->success('', [
            'enabled' => CaptchaCenter::sceneOn($scene === 'login' ? 'user_login' : 'user_register'),
            'mode'    => CaptchaCenter::mode(),
        ]);
    }

    /**
     * 字符图形验证码（graphic 模式使用）
     * 返回 key + base64 图片，提交时携带 captchaKey + captcha
     */
    public function image(): void
    {
        $key = Random::uuid();
        $captcha = new Captcha([
            'codeSet'  => '123456789',
            'fontSize' => 22,
            'useCurve' => false,
            'useNoise' => true,
            'length'   => 4,
            'bg'       => [255, 255, 255],
        ]);
        try {
            $response = $captcha->entry($key);
            $base64   = base64_encode($response->getContent());
        } catch (Throwable) {
            $this->error('验证码生成失败，请重试');
        }
        $this->success('', [
            'key'   => $key,
            'image' => 'data:image/png;base64,' . $base64,
        ]);
    }
}
