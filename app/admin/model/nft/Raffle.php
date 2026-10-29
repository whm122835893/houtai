<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 抽签发售活动模型
 */
class Raffle extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_raffle';
}
