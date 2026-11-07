<?php

namespace app\common\service\nft;

use app\admin\model\nft\Category as CategoryModel;
use app\admin\model\nft\Collection as CollectionModel;
use think\facade\Db;

/**
 * 藏品服务 —— C 端目录（列表 / 分类 / 详情）与购买校验
 *
 * 状态机（collection.status）：0=未发售 1=已排期 2=发售中 3=已下架 4=已售罄
 * C 端展示口径 = 开售即显示：已排期(1)/发售中(2)/已售罄(4) 可见，下架(3)/未发售(0) 隐藏。
 * 购买口径 = 发售中(2) 且 now < sale_end_time（售罄仅停止购买，仍可浏览）。
 */
class CollectionService
{
    /** C 端可见状态 */
    public const VISIBLE_STATUS = [1, 2, 4];

    /**
     * 在售/排期藏品列表（源站 GET /item 契约：裸数组）
     */
    public function list(int $pageNo = 1, int $pageSize = 20, int $categoryId = 0, int $userId = 0): array
    {
        $query = CollectionModel::where('status', 'in', self::VISIBLE_STATUS);
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        $total = (clone $query)->count();
        $rows  = $query->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return array_map(
            fn(array $row) => $this->toItem($row, $this->ownQuantity($userId, (int) $row['id'])),
            $rows
        );
    }

    /**
     * 启用中的分类（源站 GET /item/category 契约）
     */
    public function categories(): array
    {
        $rows = CategoryModel::where('status', 1)->order('weigh', 'desc')->order('id', 'asc')->select()->toArray();
        return array_map(fn(array $r) => [
            'cid'  => (string) $r['id'],
            'name' => (string) $r['name'],
            'sort' => (int) ($r['weigh'] ?? 0),
        ], $rows);
    }

    /**
     * 藏品详情（源站 GET /item/{iid} 契约，含嵌套 category）
     */
    public function detail(int $id, int $userId = 0): array
    {
        $row = CollectionModel::where('id', $id)->where('status', 'in', self::VISIBLE_STATUS)->find();
        if (!$row) {
            throw CException::notFound();
        }
        $row = $row->toArray();

        $out = $this->toItem($row, $this->ownQuantity($userId, $id));

        $catId = (int) ($row['category_id'] ?? 0);
        if ($catId > 0) {
            $cat = CategoryModel::where('id', $catId)->find();
            if ($cat) {
                $out['category'] = [
                    'cid'  => (string) $cat->id,
                    'name' => (string) $cat->name,
                    'sort' => (int) $cat->weigh,
                ];
            }
        }

        return $out;
    }

    /**
     * 购买资格校验（下单前调用，逐条给业务文案）
     *
     * 优先购窗口：pre_rule_type>0 且 pre_sale_time <= now < sale_time 时，
     * 仅持有凭证藏品(pre_rule_type=1)或白名单用户(pre_rule_type=2)可购，限 pre_limit 件。
     *
     * @param array $collection 藏品行
     * @param int   $num        购买数量
     * @param int   $userId     买家
     * @param int   $now        当前时间戳
     * @throws CException
     */
    public function ensureBuyable(array $collection, int $num, int $userId, int $now = 0): void
    {
        $now = $now ?: time();
        $status = (int) ($collection['status'] ?? 0);

        if ($status !== 2) {
            throw CException::biz($status === 1 ? '尚未开售' : ($status === 4 ? '已售罄' : '暂不可购买'));
        }
        $saleTime    = (int) ($collection['sale_time'] ?? 0);
        $saleEndTime = (int) ($collection['sale_end_time'] ?? 0);
        if ($saleTime > 0 && $now < $saleTime) {
            throw CException::biz('尚未开售');
        }
        if ($saleEndTime > 0 && $now >= $saleEndTime) {
            throw CException::biz('本期发售已结束');
        }

        $stock = (int) ($collection['stock'] ?? 0);
        $sold  = (int) ($collection['sold'] ?? 0);
        if ($stock - $sold < $num) {
            throw CException::biz('库存不足');
        }

        // 优先购窗口校验（窗口内普通用户不可购）
        $preType  = (int) ($collection['pre_rule_type'] ?? 0);
        $preStart = (int) ($collection['pre_sale_time'] ?? 0);
        $inPre    = $preType > 0 && $preStart > 0 && $now >= $preStart && $now < $saleTime;
        if ($inPre) {
            $this->ensurePreQualify($collection, $userId, $num);
        }

        // 普通限购：每人累计成功购买数（订单已支付口径）
        $bought = (int) Db::name('order')
            ->where('user_id', $userId)
            ->where('collection_id', (int) $collection['id'])
            ->where('type', 1)
            ->where('status', 1)
            ->sum('num');
        $preLimit = $inPre ? max(1, (int) ($collection['pre_limit'] ?? 1)) : (int) ($collection['stock'] ?? 0);
        if ($inPre && $bought + $num > $preLimit) {
            throw CException::biz('优先购每人限购 ' . $preLimit . ' 件');
        }
    }

    /** 优先购资格：1=持有指定藏品 2=白名单用户 */
    private function ensurePreQualify(array $collection, int $userId, int $num): void
    {
        $type  = (int) $collection['pre_rule_type'];
        $value = (string) ($collection['pre_rule_value'] ?? '');

        if ($type === 1) {
            $certId  = (int) explode(',', $value)[0];
            $holding = (int) Db::name('hold')
                ->where('user_id', $userId)->where('collection_id', $certId)
                ->where('status', 0)->count();
            if ($holding <= 0) {
                throw CException::biz('优先购期间仅持有指定藏品的用户可购买');
            }
            return;
        }

        if ($type === 2) {
            $ids = array_filter(array_map('intval', explode(',', $value)));
            if (!in_array($userId, $ids, true)) {
                throw CException::biz('优先购期间仅白名单用户可购买');
            }
        }
    }

    /**
     * 用户持有某藏品的数量（未登录 = 0）
     */
    private function ownQuantity(int $userId, int $collectionId): int
    {
        if ($userId <= 0 || $collectionId <= 0) {
            return 0;
        }
        return (int) Db::name('hold')->where('user_id', $userId)->where('collection_id', $collectionId)->count();
    }

    /**
     * 藏品对象（对齐源站 GET /item 的字段子集，单位：分；时间为 13 位毫秒字符串）
     */
    public function toItem(array $row, int $ownQuantity = 0, bool $isFollow = false): array
    {
        $id         = (int) ($row['id'] ?? 0);
        $status     = (int) ($row['status'] ?? 0);
        $saleTime   = (int) ($row['sale_time'] ?? 0);
        $saleEnd    = (int) ($row['sale_end_time'] ?? 0);
        $preStart   = (int) ($row['pre_sale_time'] ?? 0);
        $cover      = (string) ($row['cover'] ?? '');
        $now        = time();
        $preType    = (int) ($row['pre_rule_type'] ?? 0);
        $inPre      = $preType > 0 && $preStart > 0 && $status === 2 && $now >= $preStart && ($saleTime === 0 || $now < $saleTime);

        return [
            'iid'                    => (string) $id,
            'cid'                    => (string) ($row['category_id'] ?? '0'),
            'authorId'               => '0',
            'contract'               => '',
            'name'                   => (string) ($row['name'] ?? ''),
            'image'                  => $cover,
            'imageUrl'               => $cover === '' ? '' : full_url($cover, true),
            'content'                => $row['detail'] ?? null,
            // 源站 price 单位为分，原样输出
            'price'                  => (int) ($row['price'] ?? 0),
            'type'                   => 0,
            'issueQuantity'          => (int) ($row['stock'] ?? 0),
            'buyQuantity'            => (int) ($row['sold'] ?? 0),
            'preStartTime'           => (string) ($preStart * 1000),
            'preEndTime'             => (string) ($saleTime * 1000),
            'startTime'              => (string) ($saleTime * 1000),
            'endTime'                => (string) ($saleEnd * 1000),
            'boxOpenTime'            => '0',
            'isPrimary'              => true,
            'isPrimaryApply'         => false,
            'isRecommend'            => false,
            'isPopular'              => false,
            'isGive'                 => false,
            'isResale'               => (bool) ($row['resale'] ?? false),
            'isSecondary'            => (bool) ($row['resale'] ?? false),
            'isSecondaryBatch'       => false,
            'isSecondaryApply'       => false,
            'isSecondaryTrust'       => false,
            'secondaryLowPrice'      => 0,
            'secondaryHighPrice'     => (int) ($row['resale_price_limit'] ?? 0),
            'secondaryApplyLowPrice' => 0,
            'secondaryApplyHighPrice'=> 0,
            'isSoldOut'              => $status === 4 || ((int) ($row['stock'] ?? 0) - (int) ($row['sold'] ?? 0)) <= 0,
            'isPre'                  => $inPre,
            'nftQuantity'            => (int) ($row['sold'] ?? 0),
            'ownQuantity'            => $ownQuantity,
            'isFollow'               => $isFollow,
            'buyTime'                => (string) ($saleTime * 1000),
        ];
    }
}
