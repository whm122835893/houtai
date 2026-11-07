<?php

namespace app\common\service\nft;

use app\admin\model\nft\Banner as BannerModel;
use app\admin\model\nft\Notice as NoticeModel;
use think\facade\Db;

/**
 * 基础运营服务 —— 应用品牌 / 轮播 / 公告（basic 分包契约）
 *
 * 品牌文案取 config/nft.php 的 brand 段（每 C 端实例可通过环境变量差异化）。
 */
class BasicService
{
    /**
     * 应用信息（源站 GET /basic/app 契约；前端启动即拉，data 直接解构）
     */
    public function appInfo(): array
    {
        $brand = (array) config('nft.brand');
        $banner = Db::name('banner')->where('status', 1)->order('weigh', 'desc')->order('id', 'asc')->value('image');

        return [
            'app_name'      => (string) ($brand['app_name'] ?? ''),
            'app_logo'      => (string) ($brand['app_logo'] ?? ''),
            'home_banner'   => $banner ? full_url((string) $banner, true) : (string) ($brand['home_banner'] ?? ''),
            'hero_title_1'  => (string) ($brand['hero_title_1'] ?? ''),
            'hero_title_2'  => (string) ($brand['hero_title_2'] ?? ''),
            'hero_subtitle' => (string) ($brand['hero_subtitle'] ?? ''),
        ];
    }

    /**
     * 轮播列表（源站 GET /basic/banner 契约）
     */
    public function banners(): array
    {
        $rows = BannerModel::where('status', 1)->order('weigh', 'desc')->order('id', 'asc')->select()->toArray();
        return array_map(fn(array $r) => [
            'id'    => (string) $r['id'],
            'title' => (string) ($r['title'] ?? ''),
            'image' => ($r['image'] ?? '') !== '' ? full_url((string) $r['image'], true) : '',
            'link'  => (string) ($r['link'] ?? ''),
        ], $rows);
    }

    /**
     * 公告分类（源站 GET /basic/notice/category 契约：[{cid, name}]）
     */
    public function noticeCategories(): array
    {
        $rows = Db::name('notice_category')->where('status', 1)
            ->order('weigh', 'desc')->order('id', 'asc')->select()->toArray();
        return array_map(fn(array $r) => [
            'cid'  => (string) $r['id'],
            'name' => (string) $r['name'],
        ], $rows);
    }

    /**
     * 公告列表（源站 GET /basic/notice 契约：pageNo / cid / iid）
     */
    public function notices(int $pageNo = 1, int $pageSize = 10, int $cid = 0, int $iid = 0): array
    {
        $query = NoticeModel::where('status', 1);
        if ($cid > 0) {
            $query->where('category', $cid);
        }
        if ($iid > 0) {
            $query->where(function ($q) use ($iid) {
                $q->where('iid', $iid)->whereOr('iid', 0);
            });
        }
        $rows = $query->order('is_top', 'desc')->order('publish_time', 'desc')->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();
        return array_map(fn(array $r) => $this->toNotice($r, false), $rows);
    }

    /**
     * 公告详情（源站 GET /basic/notice/{id} 与 /basic/notice/detail 契约）
     */
    public function noticeDetail(int $noticeId): array
    {
        $row = NoticeModel::where('id', $noticeId)->where('status', 1)->find();
        if (!$row) {
            throw CException::notFound();
        }
        return $this->toNotice($row->toArray(), true);
    }

    /** 公告对象（对齐源站 Transformer::notice 字段） */
    private function toNotice(array $row, bool $withContent = false): array
    {
        $cid    = (int) ($row['category'] ?? 0);
        $catRow = $cid > 0 ? Db::name('notice_category')->where('id', $cid)->find() : null;

        $time = (int) ($row['publish_time'] ?? 0) > 0 ? (int) $row['publish_time'] : (int) ($row['createtime'] ?? 0);

        $out = [
            'id'         => (string) $row['id'],
            'title'      => (string) ($row['title'] ?? ''),
            'createTime' => (string) ($time * 1000),
            'category'   => $catRow ? ['cid' => (string) $catRow['id'], 'name' => (string) $catRow['name']] : null,
            // items = 关联藏品图位（暂无关联数据，恒空数组，前端有空值守卫）
            'items'      => [],
        ];
        if ($withContent) {
            $out['content'] = (string) ($row['content'] ?? '');
        }
        return $out;
    }
}
