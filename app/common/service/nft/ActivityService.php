<?php

namespace app\common\service\nft;

use think\facade\Db;

/**
 * 活动服务 —— 每日签到 / 积分 / 大转盘抽奖（对齐源站 activity 分包契约）
 *
 * 积分口径：user.points（nft.sql 第八章），签到固定每日 SIGN_POINTS 分。
 * 抽奖：probability 为百分比，加权随机（万分比权重）；中奖藏品自动发凭证。
 */
class ActivityService
{
    /** 每日签到固定积分 */
    public const SIGN_POINTS = 10;

    /**
     * 签到状态（源站 GET /activity/sign 契约）
     * records = 当月天数布尔数组（索引 i = 第 i+1 日，true=已签）
     */
    public function signInfo(int $userId): array
    {
        $month    = date('Y-m');
        $signDays = (int) Db::name('activity_sign')->where('user_id', $userId)->count();
        $dates    = Db::name('activity_sign')
            ->where('user_id', $userId)
            ->whereLike('sign_date', $month . '%')
            ->column('sign_date');
        $dates = array_map('strval', $dates);

        $records = [];
        $days    = (int) date('t');
        for ($i = 1; $i <= $days; $i++) {
            $records[] = in_array(sprintf('%s-%02d', $month, $i), $dates, true);
        }
        return ['signDays' => $signDays, 'records' => $records];
    }

    /**
     * 每日签到（源站 POST /activity/sign 契约，成功返回 true）
     *
     * @throws CException 当日已签到
     */
    public function sign(int $userId): bool
    {
        $today  = date('Y-m-d');
        $exists = Db::name('activity_sign')
            ->where('user_id', $userId)
            ->where('sign_date', $today)
            ->count();
        if ($exists) {
            throw CException::biz('今日已签到');
        }

        Db::startTrans();
        try {
            $days = (int) Db::name('activity_sign')->where('user_id', $userId)->count() + 1;
            Db::name('activity_sign')->insert([
                'user_id'    => $userId,
                'sign_date'  => $today,
                'day'        => $days,
                'points'     => self::SIGN_POINTS,
                'createtime' => time(),
            ]);
            Db::name('user')->where('id', $userId)->inc('points', self::SIGN_POINTS)->update();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return true;
    }

    /** 积分余额（源站 GET /activity/points/balance 契约：{balance}） */
    public function pointsBalance(int $userId): array
    {
        $balance = (int) Db::name('user')->where('id', $userId)->value('points');
        return ['balance' => $balance];
    }

    /**
     * 进行中的抽奖活动（源站 GET /activity/lottery/active 契约）
     *
     * 无启用活动返回 null（前端 lotteryInfo 保持 {} → 提示「敬请期待」）
     */
    public function activeLottery(): ?array
    {
        $lottery = Db::name('activity_lottery')->where('status', 1)->order('id', 'desc')->find();
        if (!$lottery) {
            return null;
        }

        $prizes = Db::name('activity_lottery_prize')
            ->where('lottery_id', (int) $lottery['id'])
            ->where('status', 1)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return [
            'id'      => (int) $lottery['id'],
            'name'    => (string) $lottery['name'],
            'points'  => (int) $lottery['points'],
            'content' => (string) ($lottery['content'] ?? ''),
            'prizes'  => array_map(fn(array $p) => [
                'id'          => (int) $p['id'],
                'name'        => (string) $p['name'],
                'imageUrl'    => $p['image'] !== '' ? full_url((string) $p['image'], true) : '',
                'probability' => (float) $p['probability'],
            ], $prizes),
        ];
    }

    /**
     * 抽奖（源站 POST /activity/lottery/start/{id} 契约：{id:中奖奖品id}）
     *
     * 按奖品 probability 百分比加权随机；单抽扣 lottery.points 积分；
     * 奖品配置了 collection_id 时自动发藏品凭证（source=4 抽奖）。
     *
     * @throws CException 积分不足 / 奖池为空 / 发放失败（整体回滚积分不扣）
     */
    public function draw(int $userId, int $lotteryId): array
    {
        $lottery = Db::name('activity_lottery')->where('id', $lotteryId)->where('status', 1)->find();
        if (!$lottery) {
            throw CException::notFound('抽奖活动不存在');
        }

        $prizes = Db::name('activity_lottery_prize')
            ->where('lottery_id', $lotteryId)
            ->where('status', 1)
            ->order('id', 'asc')
            ->select()
            ->toArray();
        if (!$prizes) {
            throw CException::biz('奖池为空');
        }

        Db::startTrans();
        try {
            // 原子扣积分：WHERE points >= cost 防并发扣成负数
            $hit = Db::name('user')
                ->where('id', $userId)
                ->where('points', '>=', (int) $lottery['points'])
                ->dec('points', (int) $lottery['points'])
                ->update();
            if (!$hit) {
                throw CException::biz('积分不足');
            }

            $prize  = $this->weightedPick($prizes);
            $holdId = 0;
            if ((int) $prize['collection_id'] > 0) {
                $ids    = (new HoldService())->issue($userId, (int) $prize['collection_id'], 1, 4);
                $holdId = $ids[0] ?? 0;
            }

            Db::name('activity_lottery_log')->insert([
                'user_id'     => $userId,
                'lottery_id'  => $lotteryId,
                'prize_id'    => (int) $prize['id'],
                'prize_name'  => (string) $prize['name'],
                'prize_image' => (string) $prize['image'],
                'hold_id'     => $holdId,
                'createtime'  => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return ['id' => (int) $prize['id']];
    }

    /**
     * 抽奖记录（源站 GET /activity/lottery/log 契约：裸数组 [{imageUrl,name,createTime}]）
     */
    public function logOf(int $userId, int $pageNo = 1, int $pageSize = 10): array
    {
        $rows = Db::name('activity_lottery_log')
            ->where('user_id', $userId)
            ->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return array_map(fn(array $r) => [
            'id'         => (int) $r['id'],
            'name'       => (string) $r['prize_name'],
            'imageUrl'   => $r['prize_image'] !== '' ? full_url((string) $r['prize_image'], true) : '',
            'createTime' => (string) ((int) $r['createtime'] * 1000),
        ], $rows);
    }

    /** 按 probability 百分比加权随机（概率总和不足 100 时按权重等比缩放） */
    private function weightedPick(array $prizes): array
    {
        $weights = [];
        $total   = 0;
        foreach ($prizes as $p) {
            $w        = max(1, (int) round(((float) $p['probability']) * 100));
            $weights[] = $w;
            $total    += $w;
        }

        $roll = mt_rand(1, $total);
        foreach ($prizes as $i => $p) {
            $roll -= $weights[$i];
            if ($roll <= 0) {
                return $p;
            }
        }
        return $prizes[0];
    }
}
