<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 运营参数模型（键值对存储）
 */
class Setting extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'setting';
}
