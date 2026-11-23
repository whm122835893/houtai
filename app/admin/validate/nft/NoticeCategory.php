<?php

namespace app\admin\validate\nft;

use think\Validate;

/**
 * 公告分类验证器
 */
class NoticeCategory extends Validate
{
    protected $failException = true;

    protected $rule = [
        'title' => 'require|max:50',
    ];

    protected $message = [
        'title.require' => '分类名称不能为空',
        'title.max'     => '分类名称不能超过 50 个字符',
    ];

    protected $scene = [
        'save' => ['title'],
    ];
}
