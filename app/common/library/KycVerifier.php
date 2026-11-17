<?php

namespace app\common\library;

use think\facade\Db;

/**
 * 数字藏品 · 第三方实名库对接（预留）
 *
 * 配置（setting: kyc 组）：
 *   kyc_third_url  第三方实名接口地址（POST JSON：{"key":..., "name":..., "idcard":...}）
 *   kyc_third_key  接口密钥
 *
 * 约定响应（HTTP 200 且 JSON）：
 *   {"code": 1, "message": "一致"}       —— code=1 视为校验通过
 *   {"code": 0, "message": "姓名或证件号不一致"} —— code=0 视为不一致（驳回）
 *   其它/超时/异常 —— 抛出异常，由调用方降级人工审核
 *
 * 接入真实渠道时仅需按渠道文档调整请求与响应解析（doRequest 一处）。
 */
class KycVerifier
{
    /**
     * 校验姓名与身份证号是否一致
     * @return array{pass: bool, message: string, raw: mixed}
     */
    public static function verify(string $name, string $idcard): array
    {
        $url = trim((string) (Db::name('setting')->where('name', 'kyc_third_url')->value('value') ?: ''));
        $key = (string) (Db::name('setting')->where('name', 'kyc_third_key')->value('value') ?: '');

        if ($url === '') {
            throw new \RuntimeException('未配置第三方实名接口地址');
        }

        $raw = self::doRequest($url, $key, $name, $idcard);
        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('code', $data)) {
            throw new \RuntimeException('第三方接口响应格式异常');
        }

        $pass    = (int) $data['code'] === 1;
        $message = (string) ($data['message'] ?? ($pass ? '校验通过' : '校验未通过'));
        return ['pass' => $pass, 'message' => $message, 'raw' => $data];
    }

    /**
     * 实际 HTTP 请求（默认 POST JSON；对接真实渠道时在此调整）
     */
    protected static function doRequest(string $url, string $key, string $name, string $idcard): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['key' => $key, 'name' => $name, 'idcard' => $idcard], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            throw new \RuntimeException('第三方接口请求失败(' . ($err ?: ('HTTP ' . $code)) . ')');
        }
        return (string) $body;
    }
}
