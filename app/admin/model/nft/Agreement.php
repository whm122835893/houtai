<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 协议模型
 */
class Agreement extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'agreement';
}
