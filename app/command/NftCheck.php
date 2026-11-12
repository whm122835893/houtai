<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\common\service\NftTrade;

/**
 * 数藏数据一致性巡检：php think nft:check
 *
 * 钱包对账：
 *   A1 流水自洽        每条 wallet_log 满足 after = before + amount
 *   A2 余额衔接        同一用户按 ID 顺序，前一条 after = 后一条 before
 *                      （外部支付记账 moveBalance=false 时 before=after，自动跳过衔接校验）
 *   A3 余额终点        user.balance = 该用户最后一条流水的 after
 *   A4 收支汇总        user.total_income / total_expense 与流水正负汇总一致
 *
 * 库存池对账（口径与 NftTrade::poolRemain 一致）：
 *   B1 原始池非负      stock - sold - 空投净发 - 置换发 - 盲盒奖池 - 已销毁 + 已回收 >= 0
 *   B2 发放与持仓      各藏品"应发总数"与 hold 实际记录数比对（差异仅提示，供人工核对）
 *
 * 数据健康：
 *   C1 孤儿持仓        hold.collection_id 指向不存在的藏品
 *   C2 负余额          user.balance < 0
 *
 * 退出码：0=全部通过，1=存在 FAIL；crontab 可据此告警。
 * 建议：crontab 每日 04:05 执行
 *   5 4 * * * cd /path/to/buildadmin && php think nft:check >> runtime/log/check.log 2>&1
 */
class NftCheck extends Command
{
    protected int $fail = 0;
    protected int $warn = 0;

    protected function configure()
    {
        $this->setName('nft:check')->setDescription('数藏数据一致性巡检：钱包账本/库存池/孤儿数据');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('========== 巡检开始 ' . date('Y-m-d H:i:s') . ' ==========');

        $this->checkWallet($output);
        $this->checkPool($output);
        $this->checkHealth($output);

        $output->writeln(sprintf('[汇总] FAIL=%d WARN=%d', $this->fail, $this->warn));
        $output->writeln('========== 巡检结束 ==========');
        return $this->fail > 0 ? 1 : 0;
    }

    protected function fail(Output $output, string $msg): void
    {
        $this->fail++;
        $output->writeln('[FAIL] ' . $msg);
    }

    protected function warn(Output $output, string $msg): void
    {
        $this->warn++;
        $output->writeln('[WARN] ' . $msg);
    }

    /** 钱包对账 */
    protected function checkWallet(Output $output): void
    {
        // A1 流水自洽
        $bad = Db::name('wallet_log')->whereRaw('`after` <> `before` + `amount`')->count();
        if ($bad > 0) {
            $this->fail($output, "A1 流水自洽：{$bad} 条流水 after ≠ before + amount");
        } else {
            $output->writeln('[OK] A1 流水自洽：全部流水满足 after = before + amount');
        }

        // A2 余额衔接 + A3 余额终点 + A4 收支汇总
        $users = Db::name('user')->field('id,balance,total_income,total_expense')->select()->toArray();
        foreach ($users as $u) {
            $uid   = (int) $u['id'];
            $logs  = Db::name('wallet_log')->where('user_id', $uid)->order('id', 'asc')
                ->field('id,amount,before,`after`,type')->select()->toArray();

            $prevAfter = null;
            $sumIn = 0;
            $sumOut = 0;
            foreach ($logs as $log) {
                $amount = (int) $log['amount'];
                $before = (int) $log['before'];
                $after  = (int) $log['after'];
                if ($prevAfter !== null && $before !== $prevAfter && !($before === $after && $amount !== 0)) {
                    $this->fail($output, "A2 余额衔接：用户#{$uid} 流水#{$log['id']} before={$before} 与上一条 after={$prevAfter} 断链");
                }
                $prevAfter = $after;
                if ($amount > 0) {
                    $sumIn += $amount;
                } else {
                    $sumOut += abs($amount);
                }
            }

            $lastAfter = $logs ? (int) end($logs)['after'] : 0;
            if ((int) $u['balance'] !== $lastAfter) {
                $this->fail($output, "A3 余额终点：用户#{$uid} balance={$u['balance']} ≠ 最后流水 after={$lastAfter}");
            }
            if ((int) $u['total_income'] !== $sumIn || (int) $u['total_expense'] !== $sumOut) {
                $this->fail($output, "A4 收支汇总：用户#{$uid} total_income={$u['total_income']}/流水正额={$sumIn}，total_expense={$u['total_expense']}/流水负额={$sumOut}");
            }
        }
        if ($this->fail === 0) {
            $output->writeln('[OK] A2/A3/A4 余额衔接、余额终点、收支汇总：' . count($users) . ' 个用户全部通过');
        }
    }

    /** 库存池对账 */
    protected function checkPool(Output $output): void
    {
        $cols = Db::name('collection')->select()->toArray();
        foreach ($cols as $col) {
            $cid = (int) $col['id'];
            $airdropped = (int) Db::name('airdrop_task')->where('collection_id', $cid)->sum(Db::raw('success_num - revoke_num'));
            $swapIssued = (int) Db::name('swap')->where('user_id', 0)->where('out_collection_id', 0)->where('in_collection_id', $cid)->where('status', 1)->sum('in_num');
            $bbIn       = (int) Db::name('blindbox_prize')->where('collection_id', $cid)->sum('quantity');
            $recycled   = (int) Db::name('hold')->where('collection_id', $cid)->where('status', 3)->count();
            $rawPool    = (int) $col['stock'] - (int) $col['sold'] - $airdropped - $swapIssued - $bbIn - (int) $col['destroyed'] + $recycled;

            if ($rawPool < 0) {
                $this->fail($output, "B1 原始池非负：藏品#{$cid} {$col['name']} 原始口径剩余 {$rawPool} 份（超发！）");
            }

            $holds = (int) Db::name('hold')->where('collection_id', $cid)->whereIn('status', [0, 1, 2])->count();
            $expect = (int) $col['sold'] + $airdropped + $swapIssued + $bbIn - $recycled;
            if ($holds !== $expect) {
                $this->warn($output, "B2 发放与持仓：藏品#{$cid} {$col['name']} 应发口径 {$expect} 份 / 实际持仓记录 {$holds} 条（差异 " . ($holds - $expect) . '，请人工核对，如市场购不占池则属正常）');
            }
        }
        if ($this->fail === 0) {
            $output->writeln('[OK] B1 原始池非负：' . count($cols) . ' 个藏品全部通过');
        }
    }

    /** 数据健康 */
    protected function checkHealth(Output $output): void
    {
        $orphan = Db::name('hold')->alias('h')
            ->leftJoin('collection c', 'c.id = h.collection_id')
            ->whereNull('c.id')->where('h.status', '<>', 3)->count();
        if ($orphan > 0) {
            $this->fail($output, "C1 孤儿持仓：{$orphan} 条持仓记录指向不存在的藏品");
        } else {
            $output->writeln('[OK] C1 孤儿持仓：无');
        }

        $neg = Db::name('user')->where('balance', '<', 0)->count();
        if ($neg > 0) {
            $this->fail($output, "C2 负余额：{$neg} 个用户 balance < 0");
        } else {
            $output->writeln('[OK] C2 负余额：无');
        }
    }
}
