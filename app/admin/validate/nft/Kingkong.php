<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 金刚区验证器
 */
class Kingkong extends Validate
{
    protected $failException = true;

    protected $rule = [
        'title'      => 'require|max:50',
        'icon'       => 'max:255',
        'icon_key'   => 'require|max:50',
        'link_type'  => 'in:page,url,none',
        'link_value' => 'max:255',
        'badge_type' => 'in:none,dot,num',
        'platform'   => 'in:all,h5,mp,app',
    ];

    protected $message = [
        'title.require' => '入口名称不能为空',
        'title.max'     => '入口名称不能超过 50 个字符',
        'icon_key.require' => '内置图标键不能为空',
        'link_type.in'  => '跳转类型不合法',
        'badge_type.in' => '角标类型不合法',
        'platform.in'   => '适用端不合法',
    ];

    protected $scene = [
        'save' => ['title', 'icon', 'icon_key', 'link_type', 'link_value', 'badge_type', 'platform'],
    ];
}
