<?php

namespace app\common\service\nft;

use RuntimeException;

/**
 * C 端业务异常
 *
 * code 采用源站（zhenhai 风格）业务码，由响应适配中间件原样透传：
 *   0=成功 10100=参数缺失 10101=参数类型错误
 *   10203=未找到 11111=未登录 20000=系统/业务错误
 */
class CException extends RuntimeException
{
    public const SUCCESS       = 0;
    public const PARAM_MISSING = 10100;
    public const PARAM_TYPE    = 10101;
    public const NOT_FOUND     = 10203;
    public const UNAUTHORIZED  = 11111;
    public const SYSTEM_ERROR  = 20000;

    public function __construct(string $message = '', int $code = self::SYSTEM_ERROR)
    {
        parent::__construct($message, $code);
    }

    public static function missing(string $field): self
    {
        return new self('参数 ' . $field . ' 不能为空', self::PARAM_MISSING);
    }

    public static function notFound(string $msg = '未找到'): self
    {
        return new self($msg, self::NOT_FOUND);
    }

    public static function bad(string $msg, int $code = self::PARAM_TYPE): self
    {
        return new self($msg, $code);
    }

    public static function biz(string $msg): self
    {
        return new self($msg, self::SYSTEM_ERROR);
    }
}
