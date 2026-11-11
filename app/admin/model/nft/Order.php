<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 订单模型（一级购/市场购）
 */
class Order extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'order';
}
