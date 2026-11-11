<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 抽奖中奖记录模型
 */
class LotteryRecord extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'lottery_record';
}
