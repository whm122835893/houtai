<?php

namespace app\common\service\nft;

use app\admin\model\nft\Collection as CollectionModel;
use app\admin\model\nft\Hold as HoldModel;
use think\facade\Db;

/**
 * 持仓服务 —— 数字藏品凭证（hold 表）发放与查询
 *
 * hold.status：0=持有 1=寄售中 2=已锁定 3=已转出 4=已开启(盲盒) 5=已合成(材料)
 * hold.source：1=一级购 2=市场购 3=定向发放 4=抽奖 5=合成 6=邀请奖励 7=盲盒开启
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
        $col    = CollectionModel::where('id', (int) $hold->collection_id)->find();
        $colArr = $col ? $col->toArray() : [];
        $out    = $this->toNft($hold->toArray(), $colArr);
        $out['item'] = $col ? [
            'name'          => (string) $col->name,
            'issueQuantity' => (int) $col->stock,
            'content'       => (string) $col->detail,
            'imageUrl'      => $col->cover !== '' ? full_url((string) $col->cover, true) : '',
            // 1=普通藏品 2=盲盒（盲盒凭证可开启，NftInfo.vue 按 type===2 显示「开启」）
            'type'          => (int) ($colArr['type'] ?? 1),
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

    /**
     * 盲盒开启（源站 POST /nft/box/open 契约：{imageUrl, name, token, total, nftId}）
     *
     * 校验凭证为当前用户持有态、藏品 collection.type=2；
     * 奖池复用 blindbox_prize（box_id=collection.id，万分比概率）；
     * 原凭证消耗（status=4），按概率发新藏品新凭证（source=7）。
     *
     * @throws CException
     */
    public function openBox(int $userId, int $nftId): array
    {
        if ($nftId <= 0) {
            throw CException::missing('nftId');
        }

        Db::startTrans();
        try {
            $hold = Db::name('hold')
                ->where('id', $nftId)
                ->where('user_id', $userId)
                ->where('status', 0)
                ->find();
            if (!$hold) {
                throw CException::notFound('藏品凭证不存在或不可开启');
            }

            $col = Db::name('collection')->where('id', (int) $hold['collection_id'])->find();
            if (!$col || (int) ($col['type'] ?? 1) !== 2) {
                throw CException::biz('该藏品不是盲盒');
            }

            // 只在尚有库存的奖品中随机（sent < quantity）
            $prizes = Db::name('blindbox_prize')
                ->where('box_id', (int) $col['id'])
                ->where('status', 1)
                ->where('collection_id', '>', 0)
                ->whereRaw('sent < quantity')
                ->select()
                ->toArray();
            if (!$prizes) {
                throw CException::biz('奖池已空');
            }

            $prize = $this->pickPrize($prizes);

            // 原凭证消耗（status=4 已开启）
            Db::name('hold')->where('id', $nftId)->update(['status' => 4, 'updatetime' => time()]);
            Db::name('user')->where('id', $userId)->inc('hold_count', -1)->update();

            // 奖品记数 + 发新凭证（source=7 盲盒开启）
            Db::name('blindbox_prize')->where('id', (int) $prize['id'])->inc('sent', 1)->update();
            $ids    = $this->issue($userId, (int) $prize['collection_id'], 1, 7);
            $newId  = $ids[0] ?? 0;

            Db::name('blindbox_record')->insert([
                'box_id'        => (int) $col['id'],
                'user_id'       => $userId,
                'prize_id'      => (int) $prize['id'],
                'collection_id' => (int) $prize['collection_id'],
                'prize_name'    => (string) $prize['name'],
                'hold_id'       => $newId,
                'createtime'    => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        $new   = Db::name('collection')->where('id', (int) $prize['collection_id'])->field('name,cover,stock')->find();
        $cover = (string) ($new['cover'] ?? '');
        return [
            'imageUrl' => $cover !== '' ? full_url($cover, true) : '',
            'name'     => (string) ($new['name'] ?? ''),
            'token'    => (int) $newId,
            'total'    => (int) ($new['stock'] ?? 0),
            'nftId'    => (string) $newId,
        ];
    }

    /** 盲盒万分比概率加权随机 */
    private function pickPrize(array $prizes): array
    {
        $weights = [];
        $total   = 0;
        foreach ($prizes as $p) {
            $w        = max(1, (int) $p['probability']);
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
