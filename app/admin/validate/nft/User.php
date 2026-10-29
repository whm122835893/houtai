<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 平台会员验证器
 */
class User extends Validate
{
    protected $failException = true;

    protected $rule = [
        'nickname' => 'require|max:50',
        'username' => 'require|max:50|unique:nft_user',
        'mobile'   => 'mobile',
        'email'    => 'email',
    ];

    protected $message = [
        'nickname.require' => '昵称不能为空',
        'username.require' => '账号不能为空',
        'username.unique'  => '账号已存在',
        'mobile.mobile'    => '手机号格式错误',
        'email.email'      => '邮箱格式错误',
    ];

    protected $scene = [
        'add'  => ['nickname', 'username', 'mobile', 'email'],
        'edit' => ['nickname', 'mobile', 'email'],
    ];
}
