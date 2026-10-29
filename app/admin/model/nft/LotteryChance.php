<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 抽奖次数流水模型
 */
class LotteryChance extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_lottery_chance';
}
