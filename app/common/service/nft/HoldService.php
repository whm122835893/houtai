<?php

namespace app\common\service\nft;

use app\admin\model\nft\Collection as CollectionModel;
use app\admin\model\nft\Hold as HoldModel;
use think\facade\Db;

/**
 * 持仓服务 —— 数字藏品凭证（hold 表）发放与查询
 *
 * hold.status：0=持有 1=寄售中 2=已锁定
 * hold.source：1=一级购 2=市场购 3=定向发放 4=抽奖 5=合成 6=邀请奖励
 */
class HoldService
{
    /**
     * 发放持仓（支付成功后发货；须处于调用方事务内）
     *
     * @return int[] 发放的 hold id 列表
     * @throws CException 库存扣减失败（并发售罄）
     */
    public function issue(int $userId, int $collectionId, int $num, int $source, int $refId = 0): array
    {
        $collection = CollectionModel::where('id', $collectionId)->find();
        if (!$collection) {
            throw CException::notFound('藏品不存在');
        }

        // 原子扣库存：sold + num <= stock，防超卖
        $hit = Db::name('collection')
            ->where('id', $collectionId)
            ->whereRaw('sold + ' . $num . ' <= stock')
            ->update(['sold' => Db::raw('sold + ' . $num)]);
        if (!$hit) {
            throw CException::biz('库存不足');
        }

        $ids  = [];
        $time = time();
        for ($i = 0; $i < $num; $i++) {
            $hold = HoldModel::create([
                'sn'            => $this->genSn($collectionId),
                'collection_id' => $collectionId,
                'user_id'       => $userId,
                'source'        => $source,
                'status'        => 0,
                'ref_id'        => $refId,
                'mint_time'     => $time,
                'createtime'    => $time,
                'updatetime'    => $time,
            ]);
            $ids[] = (int) $hold->id;
        }

        // 会员冗余计数（按实际发放数量累加）
        Db::name('user')->where('id', $userId)->inc('hold_count', max(1, $num))->update();

        return $ids;
    }

    /**
     * 我的持仓列表（源站 GET /nft 契约：裸数组）
     */
    public function myList(int $userId, string $iid = '', string $status = ''): array
    {
        $query = HoldModel::where('user_id', $userId);
        if ($iid !== '') {
            $query->where('collection_id', (int) $iid);
        }
        if ($status !== '') {
            $query->where('status', (int) $status);
        }

        $rows = $query->order('id', 'desc')->select()->toArray();
        if (!$rows) {
            return [];
        }

        $colIds = array_values(array_unique(array_column($rows, 'collection_id')));
        $cols   = CollectionModel::where('id', 'in', $colIds)->column('name,cover,price,resale', 'id');

        return array_map(fn(array $row) => $this->toNft($row, $cols[(int) $row['collection_id']] ?? []), $rows);
    }

    /**
     * 持仓详情（源站 GET /nft/{nftId} 契约：nft + item 子对象）
     */
    public function myOne(int $userId, int $nftId): array
    {
        $hold = HoldModel::where('id', $nftId)->where('user_id', $userId)->find();
        if (!$hold) {
            throw CException::notFound();
        }
        $col = CollectionModel::where('id', (int) $hold->collection_id)->find();
        $out = $this->toNft($hold->toArray(), $col ? $col->toArray() : []);
        $out['item'] = $col ? [
            'name'          => (string) $col->name,
            'issueQuantity' => (int) $col->stock,
            'content'       => (string) $col->detail,
            'imageUrl'      => $col->cover !== '' ? full_url((string) $col->cover, true) : '',
            'type'          => 1, // 1=普通藏品（盲盒为 2，暂未接入）
        ] : null;
        return $out;
    }

    /**
     * 持仓对象（对齐源站 Transformer::nft 字段）
     */
    public function toNft(array $row, array $collection = []): array
    {
        $cover = (string) ($collection['cover'] ?? '');
        $price = (int) ($collection['price'] ?? 0);

        return [
            'nftId'          => (string) ($row['id'] ?? ''),
            'uid'            => (string) ($row['user_id'] ?? ''),
            'iid'            => (string) ($row['collection_id'] ?? ''),
            'name'           => (string) ($collection['name'] ?? ''),
            'image'          => $cover,
            'imageUrl'       => $cover === '' ? '' : full_url($cover, true),
            // token = 持仓凭证编号（int）
            'token'          => (int) ($row['id'] ?? 0),
            'status'         => (int) ($row['status'] ?? 0),
            'getTime'        => (string) ((int) ($row['createtime'] ?? 0) * 1000),
            'price'          => $price,
            'buyPrice'       => $price,
            'payLock'        => (int) ($row['status'] ?? 0) !== 0,
            'payWay'         => null,
            'chainId'        => '',
            'chainCompleted' => false,
            'sn'             => (string) ($row['sn'] ?? ''),
        ];
    }

    /** 生成唯一藏品编号（C{藏品ID}-{随机10位}，冲突重试；市场过户复用） */
    public function genSn(int $collectionId): string
    {
        for ($i = 0; $i < 3; $i++) {
            $sn = 'C' . $collectionId . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
            if (!HoldModel::where('sn', $sn)->count()) {
                return $sn;
            }
        }
        return 'C' . $collectionId . '-' . uniqid();
    }
}
