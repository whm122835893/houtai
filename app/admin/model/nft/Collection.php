<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 藏品模型
 */
class Collection extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_collection';
}
