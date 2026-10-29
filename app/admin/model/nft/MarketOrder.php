<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 市场寄售挂单模型
 */
class MarketOrder extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_market_order';
}
