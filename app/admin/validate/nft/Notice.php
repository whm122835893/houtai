<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 公告验证器
 */
class Notice extends Validate
{
    protected $failException = true;

    protected $rule = [
        'title' => 'require|max:200',
    ];

    protected $message = [
        'title.require' => '公告标题不能为空',
        'title.max'     => '公告标题不能超过 200 个字符',
    ];

    protected $scene = [
        'add'  => ['title'],
        'edit' => ['title'],
    ];
}
