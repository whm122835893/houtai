<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 藏品分类验证器
 */
class Category extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name' => 'require|max:50',
    ];

    protected $message = [
        'name.require' => '分类名称不能为空',
        'name.max'     => '分类名称不能超过 50 个字符',
    ];

    protected $scene = [
        'add'  => ['name'],
        'edit' => ['name'],
    ];
}
