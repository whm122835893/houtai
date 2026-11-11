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

    /**
     * 铸造 1 条持仓（活动奖励 / 空投式发放，直接占用库存池：sold+1）
     * 请在调用方事务内使用。返回 hold_id。
     */
    public static function mintHold(int $collectionId, int $userId, string $snPrefix, int $refId = 0): int
    {
        $col = Db::name('collection')->where('id', $collectionId)->lock(true)->find();
        if (!$col) {
            throw new \RuntimeException('藏品不存在#' . $collectionId);
        }
        if (self::poolRemain($col) < 1) {
            throw new \RuntimeException('藏品库存池不足：' . $col['name']);
        }
        $sn    = '';
        $now   = time();
        $seq   = 1 + (int) Db::name('hold')->where('collection_id', $collectionId)->count();
        for ($i = 0; $i < 20; $i++) {
            $try = $snPrefix . 'C' . $collectionId . 'U' . $userId . 'N' . ($seq + $i);
            if (!Db::name('hold')->where('sn', $try)->find()) {
                $sn = $try;
                break;
            }
        }
        if ($sn === '') {
            throw new \RuntimeException('SN 生成失败，请重试');
        }
        Db::name('collection')->where('id', $collectionId)->inc('sold')->update(['updatetime' => $now]);
        return (int) Db::name('hold')->insertGetId([
            'sn'            => $sn,
            'collection_id' => $collectionId,
            'user_id'       => $userId,
            'source'        => 3, // 活动发放
            'status'        => 0, // 持有
            'ref_id'        => $refId,
            'mint_time'     => $now,
            'createtime'    => $now,
            'updatetime'    => $now,
        ]);
    }

    /**
     * 通用奖励发放：rewardType 1=藏品 2=抽奖次数 3=优先购资格
     * 请在调用方事务内使用。
     */
    public static function grantReward(int $userId, int $rewardType, int $rewardNum, int $collectionId, int $preCollectionId, string $snPrefix, int $refId = 0): array
    {
        switch ($rewardType) {
            case 1: // 藏品
                $holdId = self::mintHold($collectionId, $userId, $snPrefix, $refId);
                return ['type' => 1, 'hold_id' => $holdId, 'collection_id' => $collectionId];
            case 2: // 抽奖次数 → 发放到最新进行中的抽奖活动
                $activityId = self::activeLotteryId();
                self::addChance($userId, $activityId, max(1, $rewardNum), 7, (string) $refId); // source=7 后台/规则发放
                return ['type' => 2, 'activity_id' => $activityId, 'num' => max(1, $rewardNum)];
            case 3: // 优先购资格 → 写白名单
                $target = $preCollectionId > 0 ? $preCollectionId : $collectionId;
                if ($target < 1) {
                    throw new \RuntimeException('优先购资格缺少目标藏品');
                }
                Db::name('whitelist')->insert([
                    'user_id'       => $userId,
                    'collection_id' => $target,
                    'max_qty'       => max(1, $rewardNum),
                    'expires'       => 0, // 长期有效
                    'remark'        => '活动奖励自动发放#' . $refId,
                    'status'        => 1,
                    'createtime'    => time(),
                    'updatetime'    => time(),
                    'is_deleted'    => 0,
                ]);
                return ['type' => 3, 'collection_id' => $target, 'max_qty' => max(1, $rewardNum)];
            default:
                throw new \RuntimeException('未知奖励类型#' . $rewardType);
        }
    }

    /**
     * 当前进行中的抽奖活动 ID（无则取最新一个，供预发放）
     */
    public static function activeLotteryId(): int
    {
        $now    = time();
        $active = Db::name('lottery_activity')
            ->where('status', 1)->where('start_time', '<=', $now)->where('end_time', '>=', $now)
            ->order('id', 'desc')->value('id');
        if ($active) {
            return (int) $active;
        }
        return (int) Db::name('lottery_activity')->order('id', 'desc')->value('id') ?: 0;
    }

    /**
     * 确保用户有邀请码（无则生成 8 位唯一码）
     */
    public static function ensureInviteCode(int $userId): string
    {
        $code = (string) Db::name('user')->where('id', $userId)->value('invite_code');
        if ($code !== '') {
            return $code;
        }
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 去易混淆字符
        for ($i = 0; $i < 10; $i++) {
            $try = '';
            for ($j = 0; $j < 8; $j++) {
                $try .= $chars[random_int(0, strlen($chars) - 1)];
            }
            if (!Db::name('user')->where('invite_code', $try)->count()) {
                Db::name('user')->where('id', $userId)->update(['invite_code' => $try]);
                return $try;
            }
        }
        return '';
    }

    /**
     * 累加抽奖次数（有则加，无则建；幂等靠 ref 去重由调用方保证）
     */
    public static function addChance(int $userId, int $activityId, int $num, int $source, string $ref = ''): void
    {
        if ($activityId < 1 || $num < 1) {
            return;
        }
        $row = Db::name('lottery_chance')
            ->where('user_id', $userId)->where('activity_id', $activityId)->where('source', $source)
            ->order('id', 'asc')->find();
        if ($row) {
            Db::name('lottery_chance')->where('id', (int) $row['id'])->inc('num')->update(['ref' => $ref !== '' ? $ref : $row['ref']]);
        } else {
            Db::name('lottery_chance')->insert([
                'user_id'     => $userId,
                'activity_id' => $activityId,
                'num'         => $num,
                'source'      => $source,
                'ref'         => $ref,
                'createtime'  => time(),
            ]);
        }
    }
}
