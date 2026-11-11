<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 盲盒模型
 */
class Blindbox extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'blindbox';
}
