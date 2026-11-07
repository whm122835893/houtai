<?php

namespace app\api\middleware;

use app\common\service\nft\CException;
use think\Request;
use think\Response;
use Throwable;

/**
 * C 端响应契约适配中间件
 *
 * BuildAdmin 统一响应为 {code, msg, time, data}（code==1 成功），
 * zhenhai 风格 C 端契约要求 {status, code, message, data}（恒 HTTP 200，code==0 成功）。
 *
 * 由 config/nft.php 的 c_end_style 控制：
 *   zhenhai  = 转换为源站包络（默认）
 *   standard = 原样输出 BuildAdmin 包络
 *
 * 职责：
 *   1. 转换 success()/error() 产生的 BuildAdmin 包络；
 *   2. 捕获 CException 并直接渲染源站包络（业务异常带 message 与业务码，
 *      不经框架异常处理器，避免部署模式下 message 被掩码）。
 *
 * 业务码映射：
 *   1 → 0（成功） | 303/409/401/403 → 11111（未登录/登录过期）
 *   10100/10101/10203/11111/20000 原样透传 | 其余 → 20000
 */
class CStyleResponse
{
    /** 源站原样透传的业务码 */
    private const PASS_THROUGH = [
        CException::PARAM_MISSING,
        CException::PARAM_TYPE,
        CException::NOT_FOUND,
        CException::UNAUTHORIZED,
        CException::SYSTEM_ERROR,
    ];

    /** BuildAdmin「未登录/需登录/令牌过期」类 code */
    private const AUTH_CODES = [303, 409, 401, 403];

    public function handle(Request $request, \Closure $next): Response
    {
        if (config('nft.c_end_style', 'zhenhai') !== 'zhenhai') {
            try {
                return $next($request);
            } catch (CException $e) {
                // standard 风格：按 BuildAdmin 包络渲染业务异常
                return json([
                    'code' => $e->getCode() ?: CException::SYSTEM_ERROR,
                    'msg'  => $e->getMessage() ?: '系统错误',
                    'time' => time(),
                    'data' => null,
                ]);
            }
        }

        try {
            $response = $next($request);
        } catch (CException $e) {
            return $this->bizError($e);
        }

        $data = method_exists($response, 'getData') ? $response->getData() : null;
        // 仅转换 BuildAdmin 包络（含 code 键的数组），其余（如安装接口）原样放行
        if (!is_array($data) || !array_key_exists('code', $data) || !array_key_exists('msg', $data)) {
            return $response;
        }

        $code = (int) ($data['code'] ?? 0);
        $msg  = (string) ($data['msg'] ?? '');

        if ($code === 1) {
            return json([
                'status'  => true,
                'code'    => CException::SUCCESS,
                'message' => null,
                'data'    => $data['data'] ?? null,
            ]);
        }

        return json([
            'status'  => false,
            'code'    => $this->toBizCode($code),
            'message' => $msg !== '' ? $msg : '系统错误',
            'data'    => null,
        ]);
    }

    /** 渲染业务异常为源站包络（恒 HTTP 200） */
    private function bizError(CException $e): Response
    {
        return json([
            'status'  => false,
            'code'    => $e->getCode() ?: CException::SYSTEM_ERROR,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : '系统错误',
            'data'    => null,
        ]);
    }

    private function toBizCode(int $code): int
    {
        if (in_array($code, self::AUTH_CODES, true)) {
            return CException::UNAUTHORIZED;
        }
        if (in_array($code, self::PASS_THROUGH, true)) {
            return $code;
        }
        return CException::SYSTEM_ERROR;
    }
}
