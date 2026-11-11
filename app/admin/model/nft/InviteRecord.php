<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 邀请记录模型
 */
class InviteRecord extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'invite_record';
}
