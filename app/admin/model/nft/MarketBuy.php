<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 市场求购单模型
 */
class MarketBuy extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'market_buy';
}
