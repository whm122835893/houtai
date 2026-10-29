<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 合成活动验证器
 */
class Synthesis extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name'                 => 'require|max:100',
        'target_collection_id' => 'require|integer|gt:0',
    ];

    protected $message = [
        'name.require'                 => '活动名称不能为空',
        'target_collection_id.require' => '请选择目标藏品',
    ];

    protected $scene = [
        'add'  => ['name', 'target_collection_id'],
        'edit' => ['name', 'target_collection_id'],
    ];
}
