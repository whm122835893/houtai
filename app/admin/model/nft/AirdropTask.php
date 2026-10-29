<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 定向发放任务模型
 */
class AirdropTask extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_airdrop_task';
}
