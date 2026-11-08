<?php
// +----------------------------------------------------------------------
// | 超时关单任务：关闭支付窗口内未支付的订单并解锁占用的寄售单
// +----------------------------------------------------------------------
// | 部署：系统 cron 每分钟执行一次
// |   * * * * * cd /path/to/houtai && php think order:close >> runtime/log/order_close.log 2>&1
// | 幂等可重入；与在线支付并发安全（关单 UPDATE 带 status=0 守卫，支付侧行锁互斥）
// +----------------------------------------------------------------------

namespace app\command;

use app\common\service\nft\OrderService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;
use Throwable;

class OrderClose extends Command
{
    protected function configure()
    {
        $this->setName('order:close')
            ->addOption('limit', null, Option::VALUE_OPTIONAL, '单次最大处理条数', 200)
            ->setDescription('关闭超时未支付订单（15 分钟窗口）并解锁占用的寄售单');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit    = max(1, (int) $input->getOption('limit'));
        $deadline = time() - OrderService::PAY_WINDOW;

        $orders = Db::name('order')
            ->where('status', 0)
            ->where('createtime', '<=', $deadline)
            ->order('id', 'asc')
            ->limit($limit)
            ->field('id, order_no, type')
            ->select()
            ->toArray();

        if (!$orders) {
            $output->writeln('[' . date('Y-m-d H:i:s') . '] no expired orders');
            return;
        }

        $closed = 0;
        foreach ($orders as $order) {
            Db::startTrans();
            try {
                // 守卫式关单：status=0 条件防与在线支付并发误关（支付侧已行锁）
                $hit = Db::name('order')
                    ->where('id', $order['id'])
                    ->where('status', 0)
                    ->update(['status' => 2, 'updatetime' => time()]);
                if (!$hit) {
                    // 已被支付/关闭，跳过
                    Db::rollback();
                    continue;
                }

                // 解锁本单占用的寄售单（市场买入/批量买入下单即锁）
                Db::name('market_order')
                    ->where('order_id', $order['id'])
                    ->where('status', 3) // 已锁定
                    ->update(['status' => 0, 'order_id' => 0, 'updatetime' => time()]);

                Db::commit();
                $closed++;
                $output->writeln('[' . date('Y-m-d H:i:s') . '] closed order #' . $order['id'] . ' (' . $order['order_no'] . ')');
            } catch (Throwable $e) {
                Db::rollback();
                $output->writeln('[' . date('Y-m-d H:i:s') . '] ERROR order #' . $order['id'] . ': ' . $e->getMessage());
            }
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] done, closed=' . $closed . '/' . count($orders));
    }
}
