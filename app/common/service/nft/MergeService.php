<?php

namespace app\common\service\nft;

use think\facade\Db;

/**
 * 合成服务 —— 合成兑换活动（对齐源站 /item/merge + /nft/merge/v1 契约）
 *
 * 场次：item_merge（output_collection_id = 产出物藏品），
 * 条件：item_merge_need（材料藏品 + 数量），
 * 记录：item_merge_log。材料凭证消耗置 hold.status=5。
 */
class MergeService
{
    /** 合成活动列表（源站 GET /item/merge 契约：卡片字段裸数组） */
    public function list(): array
    {
        $rows = Db::name('item_merge')->where('status', 1)->order('id', 'desc')->select()->toArray();
        $now  = time();
        return array_map(fn(array $r) => $this->toCard($r, $now), $rows);
    }

    /** 卡片对象（Compose.vue 渲染字段） */
    private function toCard(array $r, int $now): array
    {
        $start = (int) ($r['merge_time'] ?? 0);
        $end   = (int) ($r['end_time'] ?? 0);

        return [
            'id'        => (int) $r['id'],
            'coverUrl'  => $r['cover'] !== '' ? full_url((string) $r['cover'], true) : '',
            'name'      => (string) $r['name'],
            'total'     => (int) $r['total'],
            'surplus'   => (int) $r['surplus'],
            'rangeTime' => ($start && $end) ? date('Y.m.d H:i', $start) . ' ~ ' . date('Y.m.d H:i', $end) : '',
            'stateText' => $this->stateText($start, $end, $now),
            'remark'    => (string) ($r['remark'] ?? ''),
        ];
    }

    private function stateText(int $start, int $end, int $now): string
    {
        if ($end > 0 && $now > $end) {
            return '已结束';
        }
        if ($start > 0 && $now < $start) {
            return '未开始';
        }
        return '进行中';
    }

    /**
     * 合成详情（源站 GET /item/merge/{id} 契约）
     *
     * needs[i].ownQuantity 为当前登录用户持有量（未登录恒 0）；
     * endTime/mergeTime 为 13 位毫秒字符串。
     */
    public function detail(int $mergeId, int $userId = 0): array
    {
        $r = Db::name('item_merge')->where('id', $mergeId)->where('status', 1)->find();
        if (!$r) {
            throw CException::notFound();
        }

        $needs = Db::name('item_merge_need')
            ->alias('n')
            ->join('collection c', 'c.id = n.collection_id')
            ->where('n.merge_id', $mergeId)
            ->field('n.collection_id as iid, c.name, c.cover, n.quantity')
            ->select()
            ->toArray();

        $own = [];
        if ($userId > 0 && $needs) {
            $iids = array_map('intval', array_column($needs, 'iid'));
            $rows = Db::name('hold')
                ->where('user_id', $userId)
                ->where('collection_id', 'in', $iids)
                ->where('status', 0)
                ->field('collection_id, count(*) as cnt')
                ->group('collection_id')
                ->select()
                ->toArray();
            foreach ($rows as $h) {
                $own[(int) $h['collection_id']] = (int) $h['cnt'];
            }
        }

        $output = (int) ($r['output_collection_id'] ?? 0);

        return [
            'id'        => (int) $r['id'],
            'coverUrl'  => $r['cover'] !== '' ? full_url((string) $r['cover'], true) : '',
            'name'      => (string) $r['name'],
            'remark'    => (string) ($r['remark'] ?? ''),
            'endTime'   => (string) ((int) $r['end_time'] * 1000),
            'mergeTime' => (string) ((int) $r['merge_time'] * 1000),
            'needs'     => array_map(fn(array $n) => [
                'iid'         => (int) $n['iid'],
                'name'        => (string) $n['name'],
                'imageUrl'    => $n['cover'] !== '' ? full_url((string) $n['cover'], true) : '',
                'quantity'    => (int) $n['quantity'],
                'ownQuantity' => $own[(int) $n['iid']] ?? 0,
            ], $needs),
            'merges'    => $output > 0 ? [['iid' => $output]] : [],
        ];
    }

    /**
     * 合成（源站 POST /nft/merge/v1 契约 {mergeId, nftIds} → {imageUrl, name, quantity}）
     *
     * 校验：活动窗口内、剩余量充足、nftIds 全部为当前用户持有(status=0)凭证、
     * 各材料数量与 needs 完全一致。消耗材料 + 原子扣剩余量 + 发产出物。
     *
     * @throws CException
     */
    public function merge(int $userId, int $mergeId, array $nftIds): array
    {
        if ($mergeId <= 0) {
            throw CException::missing('mergeId');
        }

        $r = Db::name('item_merge')->where('id', $mergeId)->where('status', 1)->find();
        if (!$r) {
            throw CException::notFound();
        }

        $now   = time();
        $start = (int) ($r['merge_time'] ?? 0);
        $end   = (int) ($r['end_time'] ?? 0);
        if ($start && $now < $start) {
            throw CException::biz('活动未开始');
        }
        if ($end && $now > $end) {
            throw CException::biz('活动已结束');
        }
        if ((int) $r['surplus'] <= 0) {
            throw CException::biz('剩余可合成数量不足');
        }

        $needs = Db::name('item_merge_need')->where('merge_id', $mergeId)->select()->toArray();
        if (!$needs) {
            throw CException::biz('活动未配置合成材料');
        }

        $nftIds = array_values(array_unique(array_map('intval', $nftIds)));
        if (!$nftIds) {
            throw CException::missing('nftIds');
        }

        // 凭证归属校验：必须全部属于当前用户且处于持有态
        $holds = Db::name('hold')
            ->where('id', 'in', $nftIds)
            ->where('user_id', $userId)
            ->where('status', 0)
            ->column('collection_id', 'id');
        if (count($holds) !== count($nftIds)) {
            throw CException::biz('存在无效或已锁定的藏品凭证');
        }

        // 材料数量校验：所选凭证按藏品分组后须与 needs 完全一致
        $have      = array_count_values(array_map('intval', array_values($holds)));
        $totalNeed = 0;
        foreach ($needs as $n) {
            $need      = (int) $n['quantity'];
            $totalNeed += $need;
            if (($have[(int) $n['collection_id']] ?? 0) < $need) {
                throw CException::biz('材料数量不足');
            }
        }
        if (count($nftIds) !== $totalNeed) {
            throw CException::biz('所选藏品数量与合成要求不符');
        }

        Db::startTrans();
        try {
            // 原子扣剩余量：防并发超合成
            $hit = Db::name('item_merge')
                ->where('id', $mergeId)
                ->where('surplus', '>', 0)
                ->dec('surplus', 1)
                ->update();
            if (!$hit) {
                throw CException::biz('剩余可合成数量不足');
            }

            // 消耗材料凭证（status=5 已合成）
            Db::name('hold')
                ->where('id', 'in', $nftIds)
                ->where('user_id', $userId)
                ->where('status', 0)
                ->update(['status' => 5, 'updatetime' => time()]);
            Db::name('user')->where('id', $userId)->inc('hold_count', -count($nftIds))->update();

            // 发产出物（source=5 合成）
            $output = (int) ($r['output_collection_id'] ?? 0);
            $holdId = 0;
            if ($output > 0) {
                $ids    = (new HoldService())->issue($userId, $output, 1, 5);
                $holdId = $ids[0] ?? 0;
            }

            Db::name('item_merge_log')->insert([
                'user_id'    => $userId,
                'merge_id'   => $mergeId,
                'hold_id'    => $holdId,
                'createtime' => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        $out = $output > 0 ? Db::name('collection')->where('id', $output)->field('name,cover')->find() : null;
        return [
            'imageUrl' => ($out && $out['cover'] !== '') ? full_url((string) $out['cover'], true) : '',
            'name'     => $out ? (string) $out['name'] : '',
            'quantity' => 1,
        ];
    }
}
