<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 注册奖励发放记录模型
 */
class RegisterLog extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'register_log';
}
