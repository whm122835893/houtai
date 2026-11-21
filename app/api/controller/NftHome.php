<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\common\library\KkIcons;
use think\facade\Db;

/**
 * C 端首页装修配置
 *
 * 接口（均公开免登录，供不同 C 端客户端对接）：
 *   GET /api/nfthome/config?platform=h5   主题配色 + 金刚区入口清单
 *   GET /api/nfthome/icon?key=market&color=%234a8ff7  内置图标动态 SVG（image/svg+xml）
 */
class NftHome extends Frontend
{
    protected array $noNeedLogin = ['*'];
    protected array $noNeedRight = ['*'];

    /** C 端首页装修配置 */
    public function config(): void
    {
        $platform = $this->request->get('platform/s', 'all');
        if (!in_array($platform, ['all', 'h5', 'mp', 'app'], true)) {
            $platform = 'all';
        }

        $rows = Db::name('setting')->where('name', 'in', ['site_theme', 'site_accent', 'site_bg_mode', 'kk_icon_shape'])
            ->column('value', 'name');

        $hex = fn ($v) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? (string) $v : '';

        $kingkong = Db::name('home_kingkong')
            ->where('status', 1)
            ->where(function ($q) use ($platform) {
                $q->where('platform', 'all')->whereOr('platform', $platform);
            })
            ->field(['id', 'title', 'icon', 'icon_key', 'link_type', 'link_value', 'badge_type', 'badge_value'])
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        foreach ($kingkong as &$item) {
            // icon 相对路径补全；icon_key 兜底校验
            if ($item['icon'] !== '') {
                $item['icon'] = full_url($item['icon']);
            } elseif (!KkIcons::has((string) $item['icon_key'])) {
                $item['icon_key'] = 'collection';
            }
        }
        unset($item);

        $this->success('', [
            'theme' => [
                'primary' => $hex($rows['site_theme'] ?? '') ?: '#4a8ff7',
                'accent'  => $hex($rows['site_accent'] ?? ''),
                'bg_mode' => in_array($rows['site_bg_mode'] ?? '', ['light', 'dark', 'system'], true) ? $rows['site_bg_mode'] : 'light',
            ],
            'kk_icon_shape' => in_array($rows['kk_icon_shape'] ?? '', ['circle', 'squircle', 'plain'], true) ? $rows['kk_icon_shape'] : 'squircle',
            'kingkong'      => $kingkong,
        ]);
    }

    /** 内置图标动态 SVG（按主题色渲染，不同 C 端直接 <image src> 加载） */
    public function icon(): void
    {
        $key   = (string) $this->request->get('key/s', 'collection');
        $color = (string) $this->request->get('color/s', '');
        if (!KkIcons::has($key)) {
            $key = 'collection';
        }
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        echo KkIcons::svg($key, $color);
        exit;
    }
}
