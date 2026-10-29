<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 藏品验证器
 */
class Collection extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name'         => 'require|max:100',
        'category_id'  => 'require|number|gt:0',
        'price'        => 'require',
        'stock'        => 'require|number',
    ];

    protected $message = [
        'name.require'        => '藏品名称不能为空',
        'name.max'            => '藏品名称不能超过 100 个字符',
        'category_id.require' => '请选择分类',
        'category_id.gt'      => '请选择分类',
        'price.require'       => '售价不能为空',
        'stock.require'       => '发行量不能为空',
        'stock.number'        => '发行量必须是数字',
    ];

    protected $scene = [
        'add'  => ['name', 'category_id', 'price', 'stock'],
        'edit' => ['name', 'category_id', 'price', 'stock'],
    ];
}
