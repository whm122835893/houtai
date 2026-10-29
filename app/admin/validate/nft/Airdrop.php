<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 定向发放任务验证器
 */
class Airdrop extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name'          => 'require|max:100',
        'collection_id' => 'require|integer|gt:0',
        'num'           => 'require|integer|gt:0',
        'rule_type'     => 'require|in:1,2,3',
    ];

    protected $message = [
        'name.require'          => '任务名称不能为空',
        'collection_id.require' => '请选择藏品',
        'num.require'           => '发放份数不能为空',
        'num.gt'                => '发放份数必须大于 0',
        'rule_type.require'     => '请选择目标规则',
        'rule_type.in'          => '目标规则错误',
    ];

    protected $scene = [
        'add' => ['name', 'collection_id', 'num', 'rule_type'],
    ];
}
