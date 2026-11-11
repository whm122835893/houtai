<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 市场寄售挂单模型
 */
class MarketOrder extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'market_order';
}
