<?php

namespace app\common\library;

/**
 * 金刚区内置 SVG 图标库（统一描边风格，stroke 1.8，24 viewBox）
 * 键名即 icon_key，C 端自定义图未上传时由 NftHome::icon 动态渲染
 */
class KkIcons
{
    /** 图标键 => path 内容（fill=none stroke=currentColor 基准） */
    public const ICONS = [
        'market'     => '<path d="M4 5h2l2.2 11.2a1.6 1.6 0 0 0 1.6 1.3h6.9a1.6 1.6 0 0 0 1.6-1.2L20 8H6.6"/><circle cx="10.5" cy="20" r="1.2"/><circle cx="17.5" cy="20" r="1.2"/>',
        'transfer'   => '<path d="M8 3 3.6 7.5 8 12"/><path d="M3.6 7.5H16a4.4 4.4 0 0 1 4.4 4.4"/><path d="M16 21l4.4-4.5L16 12"/><path d="M20.4 16.5H8a4.4 4.4 0 0 1-4.4-4.4"/>',
        'invite'     => '<circle cx="9.5" cy="8.5" r="3.5"/><path d="M3.5 20c.6-3.6 3-5.5 6-5.5s5.4 1.9 6 5.5"/><path d="M17.5 6.5v6"/><path d="M14.5 9.5h6"/>',
        'checkin'    => '<rect x="4" y="5" width="16" height="16" rx="2.5"/><path d="M4 10h16"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M8.5 14.5l2.3 2.3 4.7-4.7"/>',
        'lottery'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5"/><path d="M12 3v4.5"/><path d="M12 16.5V21"/><path d="M3 12h4.5"/><path d="M16.5 12H21"/>',
        'synthesis'  => '<path d="M12 3.5 15 8h-6z"/><path d="M4.5 14 8 18.5H3z" transform="translate(1 0)"/><path d="M17 14l3 4.5h-6z"/><path d="M12 9v4"/><path d="M6.5 18.5h4"/><path d="M14.5 18.5H18"/>',
        'blindbox'   => '<rect x="4" y="9" width="16" height="11" rx="2"/><path d="M4 9l2-4h12l2 4"/><path d="M10.5 13.5h3"/>',
        'collection' => '<rect x="4" y="6" width="16" height="14" rx="2"/><circle cx="9.5" cy="11.5" r="2"/><path d="M4.5 17.5 9.5 13l3 2.5 3.5-3 3.5 3"/>',
        'profile'    => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20.5c1-4.2 4-6.2 7.5-6.2s6.5 2 7.5 6.2"/>',
        'rank'       => '<path d="M5 21V11"/><path d="M12 21V4"/><path d="M19 21v-7"/><path d="M4 21h17"/>',
        'wallet'     => '<rect x="3.5" y="6.5" width="17" height="13" rx="2.5"/><path d="M3.5 10.5h17"/><path d="M16.5 15h1.5"/>',
        'swap'       => '<path d="M4 8.5h13l-3-3"/><path d="M20 15.5H7l3 3"/>',
    ];

    /**
     * 渲染 SVG 字符串
     * @param string $key 图标键
     * @param string $color 颜色（hex），空则 currentColor
     */
    public static function svg(string $key, string $color = ''): string
    {
        $path = self::ICONS[$key] ?? self::ICONS['collection'];
        $stroke = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : 'currentColor';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="' . $stroke . '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }

    /** 图标键是否合法 */
    public static function has(string $key): bool
    {
        return isset(self::ICONS[$key]);
    }

    /** 全部键名 */
    public static function keys(): array
    {
        return array_keys(self::ICONS);
    }
}
