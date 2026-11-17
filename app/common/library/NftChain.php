<?php

namespace app\common\library;

use think\facade\Db;

/**
 * 数字藏品 · 区块链上链公共库
 *
 * 设计：
 *   - 业务动作（下单/转赠/合成/置换等）在关键节点调用 NftChain::enqueue() 生成「待上链」记录
 *   - 真实上链由「上链适配器」消费队列（对接具体链后实现 push()，当前预留）
 *   - chain_record.status：0=待上链 1=成功(含管理员补录) 2=失败(可重试)
 *
 * 配置（setting: chain 组）：chain_enabled 开关 / chain_name / chain_rpc / chain_contract 等
 */
class NftChain
{
    /** 支持上链的业务类型 */
    public const BIZ_TYPES = [
        'order'     => '订单购买',
        'transfer'  => '转赠',
        'synthesis' => '合成',
        'swap'      => '置换',
        'mint'      => '铸造/发行',
        'airdrop'   => '定向发放',
    ];

    /**
     * 上链开关是否开启
     */
    public static function enabled(): bool
    {
        return (string) (Db::name('setting')->where('name', 'chain_enabled')->value('value') ?: '0') === '1';
    }

    /**
     * 业务单据入队「待上链」记录（开关关闭时不产生记录；异常不影响主业务）
     */
    public static function enqueue(string $bizType, int $bizId, string $address = '', string $remark = ''): void
    {
        if (!isset(self::BIZ_TYPES[$bizType]) || $bizId <= 0 || !self::enabled()) {
            return;
        }
        try {
            $chain = trim((string) (Db::name('setting')->where('name', 'chain_name')->value('value') ?: '')) ?: 'default';
            $now   = time();
            Db::name('chain_record')->insert([
                'biz_type'   => $bizType,
                'biz_id'     => $bizId,
                'chain'      => $chain,
                'address'    => $address,
                'tx_hash'    => '',
                'block_no'   => 0,
                'status'     => 0,
                'remark'     => $remark,
                'createtime' => $now,
                'updatetime' => $now,
            ]);
        } catch (\Throwable $e) {
            // 上链记录失败不阻断业务主流程，仅记错误日志
            \think\facade\Log::error('[NftChain] enqueue failed: ' . $e->getMessage());
        }
    }

    /**
     * 上链适配器（预留）：消费待上链记录，调用具体链 SDK 发起交易并回填 tx_hash / block_no
     * 接入真实链后实现本方法，并由定时任务/队列驱动：
     *   $rows = Db::name('chain_record')->where('status', 0)->limit(50)->select();
     */
    public static function push(int $limit = 50): int
    {
        return 0; // 待接入真实链后实现
    }
}
