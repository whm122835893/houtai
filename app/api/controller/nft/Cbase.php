<?php

namespace app\api\controller\nft;

use app\common\controller\Frontend;
use app\common\service\nft\CException;

/**
 * C 端控制器基类（多 C 端开放 API）
 *
 * 继承会员端 Frontend（ba-user-token 鉴权，Bearer 由中间件兼容）；
 * 统一提供源站式参数错误与密码 RSA 解密能力。
 * 响应包络由 CStyleResponse 中间件按 config('nft.c_end_style') 适配。
 */
abstract class Cbase extends Frontend
{
    /** 全部动作无需后台 RBAC 鉴权（会员端控制器本无 RBAC，显式声明防误配） */
    protected array $noNeedPermission = ['*'];

    /**
     * 分页参数（源站命名 pageNo / pageSize）
     */
    protected function pageNo(): int
    {
        return max(1, (int) $this->request->param('pageNo/d', 1));
    }

    protected function pageSize(int $default = 20): int
    {
        return min(100, max(1, (int) $this->request->param('pageSize/d', $default)));
    }

    /** 当前登录会员ID（未登录返回 0，公开接口计算 ownQuantity 用） */
    protected function optionalUid(): int
    {
        try {
            return $this->auth->isLogin() ? (int) $this->auth->id : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * 解密 C 端提交的 RSA 加密密码（PKCS1）
     *
     * 兼容裸 base64 与 PEM 内容两种配置；未配置私钥且处于调试模式时按明文处理。
     */
    protected function decryptPassword(string $encrypted): string
    {
        $encrypted = trim($encrypted);
        if ($encrypted === '') {
            throw CException::missing('password');
        }

        $private = (string) config('nft.rsa_private_key', '');
        if ($private !== '') {
            $key   = openssl_pkey_get_private($this->normalizePem($private));
            $plain = '';
            // 兜底校验明文不含控制字符：防止"合法 base64 的短字节数据"被 RSA 解成乱码后流入登录比对
            if ($key
                && openssl_private_decrypt(base64_decode($encrypted), $plain, $key, OPENSSL_PKCS1_PADDING)
                && $plain !== '' && !preg_match('/[\x00-\x1f]/', $plain)
            ) {
                return $plain;
            }
        }

        // 本地调试：未配置密钥时允许明文（生产必须配置 RSA 密钥对）
        if (config('nft.sms_debug')) {
            return $encrypted;
        }

        throw new CException('密码解密失败', CException::PARAM_TYPE);
    }

    /**
     * 把裸 base64 密钥规范化为 PEM
     *
     * @param string $type PEM 头类型：PRIVATE KEY（解密侧默认）| PUBLIC KEY（下发公钥）
     */
    protected function normalizePem(string $raw, string $type = 'PRIVATE KEY'): string
    {
        if (str_contains($raw, '-----BEGIN')) {
            return $raw;
        }
        $body = wordwrap(preg_replace('/\s+/', '', $raw) ?: $raw, 64, "\n", true);
        return "-----BEGIN {$type}-----\n" . $body . "\n-----END {$type}-----";
    }
}
