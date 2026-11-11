<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 签到记录模型
 */
class CheckinRecord extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'checkin_record';
}
