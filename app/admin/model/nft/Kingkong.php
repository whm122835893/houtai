<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * C端首页金刚区模型
 */
class Kingkong extends Model
{
    protected $name = 'home_kingkong';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
