<?php

namespace app\admin\model\nft;

use think\Model;
use app\admin\library\Auth;

/**
 * 管理员操作日志模型
 */
class OperLog extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_oper_log';

    /**
     * 记录一条管理员操作日志（供各控制器写操作时调用）
     * @param string $module 模块（如 用户管理）
     * @param string $action 动作（如 删除）
     * @param string $target 目标（如 用户#12）
     * @param string $detail 详情
     */
    public static function record(string $module, string $action, string $target = '', string $detail = ''): void
    {
        try {
            $auth = Auth::instance();
            self::create([
                'admin_id'   => $auth->isLogin() ? $auth->id : 0,
                'admin_name' => $auth->isLogin() ? $auth->nickname : '',
                'module'     => $module,
                'action'     => $action,
                'target'     => $target,
                'detail'     => mb_substr($detail, 0, 1000),
                'ip'         => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // 日志记录失败不影响主流程
        }
    }
}
