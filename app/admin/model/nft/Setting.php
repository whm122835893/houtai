<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 运营参数模型（键值对存储）
 */
class Setting extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_setting';
}
