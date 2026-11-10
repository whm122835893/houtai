<?php

namespace app\common\service;

use think\facade\Db;

/**
 * 数藏交易共享服务：运营参数读取 / 钱包账本 / 库存池口径
 * 所有 C 端交易控制器与定时任务共用，保证口径唯一。
 */
class NftTrade
{
    /**
     * 读运营参数（setting 表，后台「运营参数」页可改）
     */
    public static function cfg(string $name, string $default = ''): string
    {
        $v = Db::name('setting')->where('name', $name)->value('value');
        return $v !== null && $v !== '' ? (string) $v : $default;
    }

    /**
     * 藏品库存池剩余（与管理端 Collection::index 同口径）：
     * stock − sold − 空投到账 − 置换发放 − 盲盒占用 − 销毁 + 回收
     */
    public static function poolRemain(array $col): int
    {
        $cid        = (int) $col['id'];
        $airdropped = (int) Db::name('airdrop_task')->where('collection_id', $cid)->sum(Db::raw('success_num - revoke_num'));
        $swapIssued = (int) Db::name('swap')->where('user_id', 0)->where('out_collection_id', 0)->where('in_collection_id', $cid)->where('status', 1)->sum('in_num');
        $bbIn       = (int) Db::name('blindbox_prize')->where('collection_id', $cid)->sum('quantity');
        $recycled   = (int) Db::name('hold')->where('collection_id', $cid)->where('status', 3)->count();
        return max(0, (int) $col['stock'] - (int) $col['sold'] - $airdropped - $swapIssued - $bbIn - (int) $col['destroyed'] + $recycled);
    }

    /**
     * 钱包流水 + 余额联动（请在调用方事务内使用）
     * type: recharge/consume/income/expense/freeze/unfreeze/refund/admin_adjust
     * $moveBalance=false 时只记账不动余额（外部支付场景，before=after）
     */
    public static function walletMove(int $userId, string $type, int $amountFen, string $relatedNo, string $remark, bool $moveBalance = true): void
    {
        $user = Db::name('user')->where('id', $userId)->lock(true)->find();
        if (!$user) {
            throw new \RuntimeException('用户不存在#' . $userId);
        }
        $before = (int) $user['balance'];
        $after  = $moveBalance ? $before + $amountFen : $before;
        if ($moveBalance && $after < 0) {
            throw new \RuntimeException('余额不足');
        }
        Db::name('user')->where('id', $userId)->update([
            'balance'       => $after,
            'total_income'  => $amountFen > 0 ? (int) $user['total_income'] + $amountFen : (int) $user['total_income'],
            'total_expense' => $amountFen < 0 ? (int) $user['total_expense'] + abs($amountFen) : (int) $user['total_expense'],
        ]);
        Db::name('wallet_log')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'related_no' => $relatedNo,
            'amount'     => $amountFen,
            'before'     => $before,
            'after'      => $after,
            'remark'     => mb_substr($remark, 0, 255),
            'createtime' => time(),
        ]);
    }

    /**
     * 分 → 元字符串
     */
    public static function yuan(int $fen): string
    {
        return bcdiv((string) $fen, '100', 2);
    }
}
