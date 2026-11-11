<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 轮播模型
 */
class Banner extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'banner';
}
