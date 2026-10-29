<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 签到阶梯奖励规则模型
 */
class CheckinRule extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_checkin_rule';
}
