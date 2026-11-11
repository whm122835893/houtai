<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 抽奖奖池模型
 */
class LotteryPrize extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'lottery_prize';
}
