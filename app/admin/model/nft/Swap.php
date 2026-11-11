<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 置换记录模型
 */
class Swap extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'swap';
}
