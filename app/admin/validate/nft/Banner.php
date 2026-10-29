<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 轮播验证器
 */
class Banner extends Validate
{
    protected $failException = true;

    protected $rule = [
        'title' => 'max:100',
        'image' => 'require|max:255',
        'link'  => 'max:255',
    ];

    protected $message = [
        'title.max'   => '标题不能超过 100 个字符',
        'image.require' => '轮播图片不能为空',
        'image.max'   => '图片地址过长',
        'link.max'    => '链接地址过长',
    ];

    protected $scene = [
        'add'  => ['title', 'image', 'link'],
        'edit' => ['title', 'image', 'link'],
    ];
}
