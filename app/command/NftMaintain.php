<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;
use app\common\service\NftTrade;

/**
 * 数藏维护定时任务：php think nft:maintain
 *
 * 1. 售罄自动置位：发售中(status=2) 且（库存池归零 或 超过发售结束时间）→ status=4 已售罄
 * 2. 订单超时取消：待支付(status=0) 超过 setting.order_auto_cancel 分钟 → status=2 已取消
 *
 * 建议部署：crontab 每分钟执行一次
 *   * * * * * cd /path/to/buildadmin && php think nft:maintain >> runtime/log/maintain.log 2>&1
 */
class NftMaintain extends Command
{
    protected function configure()
    {
        $this->setName('nft:maintain')
            ->addArgument('job', Argument::OPTIONAL, '指定任务：soldout / orders（缺省全部）')
            ->setDescription('数藏维护：藏品售罄自动置位 + 待支付订单超时取消');
    }

    protected function execute(Input $input, Output $output)
    {
        $job = trim((string) $input->getArgument('job'));
        $now = time();

        if ($job === '' || $job === 'soldout') {
            $cols = Db::name('collection')->where('status', 2)->select()->toArray();
            $n = 0;
            foreach ($cols as $col) {
                $remain  = NftTrade::poolRemain($col);
                $endTs   = (int) $col['sale_end_time'];
                $expired = $endTs > 0 && $now >= $endTs;
                if ($remain <= 0 || $expired) {
                    Db::name('collection')->where('id', $col['id'])->where('status', 2)
                        ->update(['status' => 4, 'updatetime' => $now]);
                    $n++;
                    $output->writeln('[soldout] 藏品#' . $col['id'] . ' ' . $col['name'] . ' → 已售罄' . ($expired ? '（到截止时间）' : '（库存池归零）'));
                }
            }
            $output->writeln('[soldout] 本轮置位 ' . $n . ' 个');
        }

        if ($job === '' || $job === 'orders') {
            $minutes = max(1, (int) NftTrade::cfg('order_auto_cancel', '15'));
            $deadline = $now - $minutes * 60;
            $n = Db::name('order')->where('status', 0)->where('createtime', '<', $deadline)
                ->update(['status' => 2, 'updatetime' => $now]);
            $output->writeln('[orders] 取消超时待支付订单 ' . $n . ' 笔（超 ' . $minutes . ' 分钟）');
        }

        return 0;
    }
}
