<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 邀请奖励规则模型
 */
class InviteRule extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'invite_rule';
}
