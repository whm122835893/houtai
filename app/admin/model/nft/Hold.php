<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * NFT 持仓模型
 */
class Hold extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_hold';
}
