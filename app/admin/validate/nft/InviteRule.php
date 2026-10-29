<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 邀请奖励规则验证器
 */
class InviteRule extends Validate
{
    protected $failException = true;

    protected $rule = [
        'invite_num' => 'require|integer|gt:0',
    ];

    protected $message = [
        'invite_num.require' => '邀请人数门槛不能为空',
        'invite_num.gt'      => '邀请人数门槛必须大于 0',
    ];

    protected $scene = [
        'add'  => ['invite_num'],
        'edit' => ['invite_num'],
    ];
}
