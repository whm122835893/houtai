<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 藏品分类模型
 */
class Category extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_category';
}
