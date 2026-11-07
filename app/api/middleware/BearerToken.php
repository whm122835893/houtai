<?php

namespace app\api\middleware;

use think\Request;
use think\Response;

/**
 * Bearer token 兼容中间件
 *
 * C 端（zhenhai 风格）携带 `Authorization: Bearer <token>`，
 * BuildAdmin 会员鉴权读取 `ba-user-token` 头 —— 这里做一次映射，
 * 不改动框架鉴权链路（app\common\library\Auth 照常工作）。
 *
 * 注意：Request::withHeader 会整体替换 header 数组，必须先取出再合并。
 */
class BearerToken
{
    public function handle(Request $request, \Closure $next): Response
    {
        $auth = trim((string) $request->header('authorization', ''));
        if ($auth !== '' && stripos($auth, 'bearer ') === 0) {
            $token = trim(substr($auth, 7));
            if ($token !== '') {
                $request->withHeader(array_merge($request->header(), [
                    'ba-user-token' => $token,
                ]));
            }
        }
        return $next($request);
    }
}
