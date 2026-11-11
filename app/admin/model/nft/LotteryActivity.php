<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 抽奖活动模型
 */
class LotteryActivity extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'lottery_activity';
}
