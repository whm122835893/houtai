<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 抽奖活动验证器
 */
class LotteryActivity extends Validate
{
    protected $failException = true;

    protected $rule = [
        'title' => 'require|max:100',
    ];

    protected $message = [
        'title.require' => '活动名称不能为空',
    ];

    protected $scene = [
        'add'  => ['title'],
        'edit' => ['title'],
    ];
}
