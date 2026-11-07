<?php

namespace app\common\service\nft;

use Throwable;
use think\facade\Db;

/**
 * 钱包服务 —— 余额变动唯一入口（分存储）
 *
 * 规约：
 *   - 所有金额一律「分」；
 *   - 余额变动必须走本服务（事务 + 行锁 + 流水），禁止直接改 user.balance；
 *   - 流水表 wallet_log，user 表冗余 total_income / total_expense。
 */
class WalletService
{
    /** 变动前余额（changeBalance 成功后可读） */
    public int $before = 0;

    /**
     * 余额变动（内部调用请用各语义方法；负数金额须保证余额充足）
     *
     * @param int    $userId    会员ID
     * @param int    $fen       变动金额（分，正收入负支出）
     * @param string $type      流水类型：recharge/consume/income/expense/freeze/unfreeze/refund/admin_adjust
     * @param string $relatedNo 关联单号
     * @param string $remark    备注
     * @return int 变动后余额（分）
     * @throws CException 余额不足 / 用户不存在
     */
    public function changeBalance(int $userId, int $fen, string $type, string $relatedNo = '', string $remark = ''): int
    {
        if ($fen === 0) {
            throw CException::bad('变动金额不能为 0');
        }

        Db::startTrans();
        try {
            // for update 行锁，防并发双花
            $user = Db::name('user')->where('id', $userId)->lock(true)->find();
            if (!$user) {
                throw CException::notFound('用户不存在');
            }
            $balance = (int) ($user['balance'] ?? 0);
            if ($fen < 0 && $balance + $fen < 0) {
                throw CException::biz('余额不足');
            }

            $after = $balance + $fen;
            Db::name('user')->where('id', $userId)->update([
                'balance'       => $after,
                'total_income'  => $fen > 0 ? (int) $user['total_income'] + $fen : (int) $user['total_income'],
                'total_expense' => $fen < 0 ? (int) $user['total_expense'] + abs($fen) : (int) $user['total_expense'],
            ]);
            Db::name('wallet_log')->insert([
                'user_id'    => $userId,
                'type'       => $type,
                'related_no' => $relatedNo,
                'amount'     => $fen,
                'before'     => $balance,
                'after'      => $after,
                'remark'     => $remark,
                'createtime' => time(),
            ]);
            Db::commit();

            $this->before = $balance;
            return $after;
        } catch (Throwable $e) {
            Db::rollback();
            if ($e instanceof CException) {
                throw $e;
            }
            throw CException::biz($e->getMessage());
        }
    }

    /** 后台调整（充值/扣减，可为负） */
    public function adjust(int $userId, int $fen, string $remark = ''): int
    {
        return $this->changeBalance($userId, $fen, 'admin_adjust', '', $remark ?: '管理员调整');
    }

    /** 充值 */
    public function recharge(int $userId, int $fen, string $relatedNo = '', string $remark = ''): int
    {
        return $this->changeBalance($userId, abs($fen), 'recharge', $relatedNo, $remark ?: '充值');
    }

    /** 消费（余额支付，金额为正数分） */
    public function consume(int $userId, int $fen, string $relatedNo = '', string $remark = ''): int
    {
        return $this->changeBalance($userId, -abs($fen), 'consume', $relatedNo, $remark ?: '消费支出');
    }

    /** 收款（市场卖出等，金额为正数分） */
    public function income(int $userId, int $fen, string $relatedNo = '', string $remark = ''): int
    {
        return $this->changeBalance($userId, abs($fen), 'income', $relatedNo, $remark ?: '交易收款');
    }

    /** 退款 */
    public function refund(int $userId, int $fen, string $relatedNo = '', string $remark = ''): int
    {
        return $this->changeBalance($userId, abs($fen), 'refund', $relatedNo, $remark ?: '订单退款');
    }

    /**
     * 托管（求购/委托预付：余额 → 冻结，不改 total_expense）
     *
     * @throws CException 余额不足
     */
    public function escrow(int $userId, int $fen, string $relatedNo = '', string $remark = ''): int
    {
        $fen = abs($fen);
        Db::startTrans();
        try {
            $user = Db::name('user')->where('id', $userId)->lock(true)->find();
            if (!$user) {
                throw CException::notFound('用户不存在');
            }
            if ((int) $user['balance'] < $fen) {
                throw CException::biz('余额不足');
            }

            Db::name('user')->where('id', $userId)->update([
                'balance' => (int) $user['balance'] - $fen,
                'frozen'  => (int) $user['frozen'] + $fen,
            ]);
            $this->log($userId, 'freeze', $relatedNo, $fen, (int) $user['balance'], (int) $user['balance'] - $fen, $remark ?: '求购/委托托管');
            Db::commit();
            return (int) $user['balance'] - $fen;
        } catch (Throwable $e) {
            Db::rollback();
            if ($e instanceof CException) {
                throw $e;
            }
            throw CException::biz($e->getMessage());
        }
    }

    /**
     * 释放托管（成交：买方冻结扣减，资金划转由卖方 income 落账，须与调用方同事务）
     *
     * @throws CException 冻结金额不足
     */
    public function releaseEscrow(int $userId, int $fen, string $relatedNo = '', string $remark = ''): void
    {
        $fen = abs($fen);
        Db::startTrans();
        try {
            $user = Db::name('user')->where('id', $userId)->lock(true)->find();
            if (!$user) {
                throw CException::notFound('用户不存在');
            }
            if ((int) $user['frozen'] < $fen) {
                throw CException::biz('冻结金额不足');
            }

            Db::name('user')->where('id', $userId)->update([
                'frozen' => (int) $user['frozen'] - $fen,
            ]);
            $this->log($userId, 'unfreeze', $relatedNo, $fen, (int) $user['balance'], (int) $user['balance'], $remark ?: '托管释放');
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            if ($e instanceof CException) {
                throw $e;
            }
            throw CException::biz($e->getMessage());
        }
    }

    /** 写流水（须处于调用方事务内时由内部方法使用） */
    private function log(int $userId, string $type, string $relatedNo, int $amount, int $before, int $after, string $remark): void
    {
        Db::name('wallet_log')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'related_no' => $relatedNo,
            'amount'     => $amount,
            'before'     => $before,
            'after'      => $after,
            'remark'     => $remark,
            'createtime' => time(),
        ]);
    }
}
